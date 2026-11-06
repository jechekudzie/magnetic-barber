<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'slug' => 'harare-avenues', 'code' => 'MB', 'timezone' => 'Africa/Harare',
        'opens_at' => '08:00', 'closes_at' => '19:00', 'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->barber = staffer('barber', 'Blessing Ncube', bookable: true);
    $this->owner = staffer('owner', 'The Owner');

    $this->cut = Service::factory()->create([
        'name' => 'Standard Haircut', 'default_duration_minutes' => 45, 'buffer_minutes' => 0,
    ]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1000, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);
});

function staffer(string $role, string $name, bool $bookable = false): User
{
    $user = User::factory()->create(['name' => $name]);
    StaffProfile::factory()->for($user)->create([
        'is_bookable' => $bookable, 'display_name' => $name,
    ]);
    test()->branch->staff()->attach($user, ['is_primary' => true]);

    setPermissionsTeamId(test()->branch->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

/** A finished cut, with the clock having run for a given number of minutes. */
function cutTaking(int $minutes, ?User $barber = null, string $phone = '0781879820'): Appointment
{
    static $offset = 0;

    $slot = CarbonImmutable::now(test()->branch->timezone)
        ->addWeek()->setTime(9, 0)->addHours(++$offset);

    $appointment = app(BookingService::class)->book(test()->branch, [
        'name' => 'Tendai Moyo',
        'phone' => $phone,
        'service_ids' => [test()->cut->id],
        'staff_id' => ($barber ?? test()->barber)->id,
        'start' => $slot->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);

    $appointment->update([
        'status' => AppointmentStatus::Completed,
        // ->utc(), or Eloquent writes the wall clock digits and reads them
        // back as UTC.
        'completed_at' => CarbonImmutable::now()->utc(),
        'actual_minutes' => $minutes,
    ]);

    return $appointment;
}

it('shows the owner what the floor earned', function () {
    cutTaking(40);
    cutTaking(50, phone: '0782000011');

    $this->actingAs($this->owner)
        ->get('/admin/earnings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/earnings')
            // 5% of two $10 cuts.
            ->where('totals.earned.cents', 100)
            ->where('totals.took.cents', 2000)
            ->where('totals.visits', 2)
            ->has('staff', 1)
            ->where('staff.0.name', 'Blessing Ncube')
            ->where('staff.0.rate', 5));
});

it('puts the time a cut took beside what it earned', function () {
    cutTaking(40);
    cutTaking(80, phone: '0782000011');

    $this->actingAs($this->owner)
        ->get('/admin/earnings')
        ->assertInertia(fn ($page) => $page
            ->has('timing', 1)
            ->where('timing.0.name', 'Standard Haircut')
            ->where('timing.0.cuts', 2)
            ->where('timing.0.booked', 45)
            // The average of 40 and 80, against 45 allowed.
            ->where('timing.0.actual', 60));
});

it('leaves untimed cuts out of the timing rather than counting them as zero', function () {
    $untimed = cutTaking(40);
    $untimed->update(['actual_minutes' => null]);

    $this->actingAs($this->owner)
        ->get('/admin/earnings')
        ->assertInertia(fn ($page) => $page->has('timing', 0)
            // It still earned, it just was not clocked.
            ->where('totals.earned.cents', 50));
});

it('ranks barbers by what they earned', function () {
    $other = staffer('barber', 'Rudo Chikafu', bookable: true);

    cutTaking(30);
    cutTaking(30, phone: '0782000011');
    cutTaking(30, $other, '0782000012');

    $this->actingAs($this->owner)
        ->get('/admin/earnings')
        ->assertInertia(fn ($page) => $page
            ->where('staff.0.name', 'Blessing Ncube')
            ->where('staff.0.earned.cents', 100)
            ->where('staff.1.name', 'Rudo Chikafu')
            ->where('staff.1.earned.cents', 50));
});

it('keeps a barber out of the floor earnings', function () {
    $this->actingAs($this->barber)->get('/admin/earnings')->assertForbidden();
});

it('defaults to the last seven days', function () {
    $this->actingAs($this->owner)
        ->get('/admin/earnings')
        ->assertInertia(fn ($page) => $page->where('range.days', 7));
});

it('refuses to scan more than ninety days in one go', function () {
    $this->actingAs($this->owner)
        ->get('/admin/earnings?from=2020-01-01&to=2026-01-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('range.days', 93));
});

it('turns a backwards range the right way round', function () {
    $this->actingAs($this->owner)
        ->get('/admin/earnings?from=2026-03-10&to=2026-03-01')
        ->assertInertia(fn ($page) => $page
            ->where('range.from', '2026-03-01')
            ->where('range.to', '2026-03-10'));
});

/* ----------------------------------------- the clock, from the desk */

it('starts a cut from the diary', function () {
    $appointment = cutTaking(0);
    $appointment->update([
        'status' => AppointmentStatus::Confirmed,
        'started_at' => null,
        'completed_at' => null,
        'actual_minutes' => null,
    ]);

    $this->actingAs($this->owner)
        ->put("/admin/bookings/{$appointment->ulid}/start")
        ->assertRedirect();

    $fresh = $appointment->fresh();

    expect($fresh->status)->toBe(AppointmentStatus::InProgress)
        ->and($fresh->started_at)->not->toBeNull();
});

it('finishes a cut and keeps what it took', function () {
    $start = CarbonImmutable::now();
    $this->travelTo($start);

    $appointment = cutTaking(0);
    $appointment->update([
        'status' => AppointmentStatus::Confirmed,
        'started_at' => null,
        'completed_at' => null,
        'actual_minutes' => null,
    ]);

    $this->actingAs($this->owner)->put("/admin/bookings/{$appointment->ulid}/start");

    $this->travelTo($start->addMinutes(37));

    $this->actingAs($this->owner)
        ->put("/admin/bookings/{$appointment->ulid}/finish")
        ->assertRedirect();

    $fresh = $appointment->fresh();

    expect($fresh->status)->toBe(AppointmentStatus::Completed)
        ->and($fresh->actual_minutes)->toBe(37);
});

it('will not finish a cut nobody started', function () {
    $appointment = cutTaking(0);
    $appointment->update([
        'status' => AppointmentStatus::Confirmed,
        'started_at' => null,
        'completed_at' => null,
    ]);

    $this->actingAs($this->owner)
        ->put("/admin/bookings/{$appointment->ulid}/finish")
        ->assertStatus(422);
});

it('keeps the first start time when the button is pressed twice', function () {
    $start = CarbonImmutable::now();
    $this->travelTo($start);

    $appointment = cutTaking(0);
    $appointment->update([
        'status' => AppointmentStatus::Confirmed,
        'started_at' => null,
        'completed_at' => null,
    ]);

    $this->actingAs($this->owner)->put("/admin/bookings/{$appointment->ulid}/start");

    $this->travelTo($start->addMinutes(12));
    $this->actingAs($this->owner)->put("/admin/bookings/{$appointment->ulid}/start");

    expect($appointment->fresh()->started_at->diffInMinutes($start))->toBeLessThan(1);
});
