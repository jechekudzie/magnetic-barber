<?php

use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\VisitService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * The front desk: finding whoever walked in, and putting them in a chair.
 *
 * The cases that matter are the ones a day grid cannot answer — a client who
 * booked for another day, and a barber working with nobody on the desk.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00', 'Africa/Harare'));

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'slug' => 'harare-avenues', 'code' => 'MB', 'timezone' => 'Africa/Harare',
        'opens_at' => '08:00', 'closes_at' => '19:00', 'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->taps = deskStaff('Tapiwa Nyoni', 'barber', bookable: true);
    $this->blessing = deskStaff('Blessing Chari', 'barber', bookable: true);
    $this->girl = deskStaff('Rudo Dube', 'receptionist');

    $this->cut = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1500, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);
});

function deskStaff(string $name, string $role, bool $bookable = false): User
{
    $user = User::factory()->create(['name' => $name]);
    StaffProfile::factory()->for($user)->create(['is_bookable' => $bookable]);
    test()->branch->staff()->attach($user, ['is_primary' => true]);

    setPermissionsTeamId(test()->branch->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

function deskBooking(User $barber, string $phone = '0781879820', int $daysAhead = 0)
{
    return app(BookingService::class)->book(test()->branch, [
        'name' => 'Tendai Moyo',
        'phone' => $phone,
        'service_ids' => [test()->cut->id],
        'staff_id' => $barber->id,
        // Tomorrow morning by default, so the suite is not sensitive to the
        // hour it happens to run at.
        'start' => CarbonImmutable::now(test()->branch->timezone)
            ->addDays($daysAhead)
            ->setTime(10, 0)
            ->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);
}

/* -------------------------------------------------------------- the search */

it('finds a booking by phone number however it was typed', function (string $typed) {
    $appointment = deskBooking($this->taps);

    $hits = app(VisitService::class)->search([$this->branch->id], $typed);

    expect($hits->pluck('reference')->all())->toContain($appointment->reference);
})->with([
    '0781879820',
    '+263781879820',
    '263781879820',
    // Only the tail, for a client who cannot remember the rest.
    '781879820',
]);

it('finds a booking by its reference', function () {
    $appointment = deskBooking($this->taps);

    $hits = app(VisitService::class)->search([$this->branch->id], strtolower($appointment->reference));

    expect($hits->pluck('id')->all())->toContain($appointment->id);
});

it('finds a booking by the client name', function () {
    $appointment = deskBooking($this->taps);

    $hits = app(VisitService::class)->search([$this->branch->id], 'tendai');

    expect($hits->pluck('id')->all())->toContain($appointment->id);
});

/*
 * The case the diary cannot answer. Searching a day grid for somebody who
 * booked Thursday and walked in on Wednesday finds nothing.
 */
it('finds a booking made for another day', function () {
    $appointment = deskBooking($this->taps, daysAhead: 3);

    $hits = app(VisitService::class)->search([$this->branch->id], '0781879820');

    expect($hits->pluck('id')->all())->toContain($appointment->id);
});

it('does not reach into another branch', function () {
    $appointment = deskBooking($this->taps);
    $other = Branch::factory()->create(['slug' => 'other', 'code' => 'XX']);

    $hits = app(VisitService::class)->search([$other->id], '0781879820');

    expect($hits)->toBeEmpty()
        ->and($appointment->branch_id)->not->toBe($other->id);
});

it('shows the desk search to a receptionist', function () {
    $appointment = deskBooking($this->taps);

    $this->actingAs($this->girl)
        ->get('/admin/desk?search=0781879820')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/desk')
            ->where('results.0.reference', $appointment->reference)
            // Reception chases no-shows, so they get the real number.
            ->where('results.0.client.phone', '+263781879820')
        );
});

/* ------------------------------------------------------------- the chair */

it('moves a booking to another barber and takes the commission with it', function () {
    $appointment = deskBooking($this->taps);

    $this->actingAs($this->girl)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $this->blessing->ulid])
        ->assertRedirect();

    $appointment->refresh();

    expect($appointment->staff_id)->toBe($this->blessing->id)
        ->and($appointment->assigned_by)->toBe($this->girl->id)
        ->and($appointment->assigned_at)->not->toBeNull();

    // The whole point: the money has to follow the work, or Taps is paid
    // for a cut Blessing gave.
    expect($appointment->services->pluck('staff_id')->unique()->all())
        ->toBe([$this->blessing->id]);
});

it('will not hand a booking to a barber who is already booked then', function () {
    $appointment = deskBooking($this->taps);
    deskBooking($this->blessing, phone: '0772000111');

    $this->actingAs($this->girl)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $this->blessing->ulid])
        ->assertStatus(422);

    expect($appointment->refresh()->staff_id)->toBe($this->taps->id);
});

it('will not change the chair on a finished visit', function () {
    $appointment = deskBooking($this->taps);
    $appointment->update([
        'status' => AppointmentStatus::Completed,
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);

    $this->actingAs($this->girl)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $this->blessing->ulid])
        ->assertStatus(422);
});

it('will not hand a booking to someone who does not work at the branch', function () {
    $appointment = deskBooking($this->taps);

    $outsider = User::factory()->create(['name' => 'Someone Else']);
    StaffProfile::factory()->for($outsider)->create(['is_bookable' => true]);

    $this->actingAs($this->girl)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $outsider->ulid])
        ->assertStatus(422);
});

/* ------------------------------------------- the desk being unattended */

it('lets a barber take a booking onto himself and start it', function () {
    $appointment = deskBooking($this->taps);

    $this->actingAs($this->blessing)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $this->blessing->ulid])
        ->assertRedirect();

    $appointment->refresh();

    expect($appointment->staff_id)->toBe($this->blessing->id)
        // Recorded as the barber's doing, not reception's.
        ->and($appointment->assigned_by)->toBe($this->blessing->id);
});

it('will not let a barber hand a booking to a different barber', function () {
    $appointment = deskBooking($this->taps);

    $this->actingAs($this->blessing)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $this->taps->ulid])
        ->assertForbidden();
});

it('will not let a barber take a cut that has already started', function () {
    $appointment = deskBooking($this->taps);
    app(VisitService::class)->start($appointment, $this->taps);

    $this->actingAs($this->blessing)
        ->put("/admin/desk/{$appointment->ulid}/barber", ['staff_id' => $this->blessing->ulid])
        ->assertForbidden();

    expect($appointment->refresh()->staff_id)->toBe($this->taps->id);
});

/*
 * A barber holds appointment.view.own, deliberately, so that one barber
 * cannot read another's book. The desk must respect that in the list it
 * shows unasked, even though search is allowed to cross it.
 */
it('shows a barber only his own column in the expected list', function () {
    deskBooking($this->taps);
    deskBooking($this->blessing, phone: '0772000111');

    $this->actingAs($this->blessing)
        ->get('/admin/desk')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('expected', 1)
            ->where('expected.0.staff_id', $this->blessing->ulid)
            // And without the client's number.
            ->where('expected.0.client.phone', '+2637****111')
        );
});

it('records who started the cut', function () {
    $appointment = deskBooking($this->taps);

    $this->actingAs($this->taps)
        ->put("/admin/bookings/{$appointment->ulid}/start")
        ->assertRedirect();

    expect($appointment->refresh()->started_by)->toBe($this->taps->id);
});
