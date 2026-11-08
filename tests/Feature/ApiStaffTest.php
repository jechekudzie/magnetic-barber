<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ClientAccountService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'slug' => 'harare-avenues', 'code' => 'MB', 'timezone' => 'Africa/Harare',
        'opens_at' => '08:00', 'closes_at' => '19:00', 'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->barber = staffWithRole('barber', 'Tapiwa Nyoni');
    $this->owner = staffWithRole('owner', 'Magnetic Owner');

    $this->cut = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1000, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);

    $this->slot = CarbonImmutable::now($this->branch->timezone)
        ->addWeek()->next(CarbonImmutable::TUESDAY)->setTime(10, 0);
});

function staffWithRole(string $role, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    // The resource prefers the profile's display name, so it has to match.
    StaffProfile::factory()->for($user)->create(['is_bookable' => true, 'display_name' => $name]);
    test()->branch->staff()->attach($user, ['is_primary' => true]);

    setPermissionsTeamId(test()->branch->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

function asStaff(User $user): User
{
    Sanctum::actingAs($user, ['staff']);

    return $user;
}

function bookWith(User $barber, ?CarbonImmutable $at = null, string $phone = '0781879820'): Appointment
{
    return app(BookingService::class)->book(test()->branch, [
        'name' => 'Tendai Moyo',
        'phone' => $phone,
        'service_ids' => [test()->cut->id],
        'staff_id' => $barber->id,
        'start' => ($at ?? test()->slot)->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);
}

/**
 * A finished visit, completed at a chosen moment.
 *
 * The booking itself has to sit in the future — the shop refuses to book a
 * time that has already passed — so the slot and the completion are set
 * separately. Earnings are read from completed_at, which is the one that
 * matters here. Each call takes its own slot so two never collide.
 */
function completedBy(User $barber, CarbonImmutable $completedAt, string $phone): Appointment
{
    static $offset = 0;

    $appointment = bookWith($barber, test()->slot->addHours(++$offset), $phone);

    $appointment->update([
        'status' => AppointmentStatus::Completed,
        // ->utc() because Eloquent writes whatever timezone the instance
        // carries and reads it back as UTC.
        'completed_at' => $completedAt->utc(),
    ]);

    return $appointment;
}

/** Every staff request names the branch it is acting in. */
function branchHeader(): array
{
    return ['X-Branch-Id' => (string) test()->branch->id];
}

/* ---------------------------------------------------------------- diary */

it('shows the owner the whole floor', function () {
    bookWith($this->barber);
    bookWith($this->owner, $this->slot->addHours(2), '0782000099');

    asStaff($this->owner);

    $response = $this->withHeaders(branchHeader())
        ->getJson('/api/v1/staff/diary?date='.$this->slot->toDateString());

    $response->assertOk()->assertJsonPath('counts.total', 2);
    expect($response->json('data'))->toHaveCount(2);
});

it('shows a barber only their own column', function () {
    bookWith($this->barber);
    bookWith($this->owner, $this->slot->addHours(2), '0782000099');

    asStaff($this->barber);

    $response = $this->withHeaders(branchHeader())
        ->getJson('/api/v1/staff/diary?date='.$this->slot->toDateString());

    $response->assertOk()->assertJsonPath('counts.total', 1);
    expect($response->json('data.0.staff.name'))->toBe('Tapiwa Nyoni');
});

it('defaults the diary to today', function () {
    asStaff($this->owner);

    $this->withHeaders(branchHeader())->getJson('/api/v1/staff/diary')
        ->assertOk()
        ->assertJsonPath('date', CarbonImmutable::now($this->branch->timezone)->toDateString());
});

it('refuses a client token on the staff surface', function () {
    $client = app(ClientAccountService::class)->register('Tendai Moyo', '0781879820', $this->branch);
    Sanctum::actingAs($client, ['client']);

    $this->withHeaders(branchHeader())->getJson('/api/v1/staff/diary')->assertStatus(403);
});

/* --------------------------------------------------------- moving a job */

it('lets a barber complete their own booking, which earns the commission', function () {
    $appointment = bookWith($this->barber);
    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect(Appointment::sole()->completed_at)->not->toBeNull();
});

it('stops a barber touching another barber\'s booking', function () {
    $appointment = bookWith($this->owner);
    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/status", ['status' => 'completed'])
        ->assertStatus(403);
});

it('stops a barber cancelling, which they hold no permission for', function () {
    $appointment = bookWith($this->barber);
    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/status", ['status' => 'cancelled'])
        ->assertStatus(403);
});

it('lets the owner cancel and frees the slot', function () {
    $appointment = bookWith($this->barber);
    asStaff($this->owner);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/status", [
            'status' => 'cancelled', 'reason' => 'Client called',
        ])->assertOk();

    expect(Appointment::sole()->slot_key)->toBeNull();
});

/* ------------------------------------------------------------- earnings */

it('tells a barber what they earned today', function () {
    completedBy($this->barber, CarbonImmutable::now($this->branch->timezone), '0781879820');

    asStaff($this->barber);

    $this->withHeaders(branchHeader())->getJson('/api/v1/staff/earnings/today')
        ->assertOk()
        ->assertJsonPath('rate', 5)
        ->assertJsonPath('data.services', 1)
        ->assertJsonPath('data.earned.cents', 50)
        ->assertJsonPath('data.took.cents', 1000);
});

it('reports a run of days with a total', function () {
    $today = CarbonImmutable::now($this->branch->timezone)->setTime(10, 0);

    foreach ([0, 1] as $daysAgo) {
        completedBy($this->barber, $today->subDays($daysAgo), '078187982'.$daysAgo);
    }

    asStaff($this->barber);

    $response = $this->withHeaders(branchHeader())->getJson('/api/v1/staff/earnings');

    $response->assertOk()->assertJsonPath('total_cents', 100);
    expect($response->json('data'))->toHaveCount(2);
});

it('refuses a range longer than ninety days', function () {
    asStaff($this->barber);

    $this->withHeaders(branchHeader())->getJson('/api/v1/staff/earnings?'.http_build_query([
        'from' => '2026-01-01', 'to' => '2026-12-31',
    ]))->assertStatus(422);
});

it('shows the owner the whole team\'s earnings, ranked', function () {
    $today = CarbonImmutable::now($this->branch->timezone);

    completedBy($this->barber, $today, '0781879820');
    completedBy($this->barber, $today, '0781879821');
    completedBy($this->owner, $today, '0781879822');

    asStaff($this->owner);

    $response = $this->withHeaders(branchHeader())->getJson('/api/v1/staff/earnings/branch');

    $response->assertOk()
        ->assertJsonPath('total_earned_cents', 150)
        ->assertJsonPath('data.0.name', 'Tapiwa Nyoni')
        ->assertJsonPath('data.0.earned.cents', 100)
        ->assertJsonPath('data.1.earned.cents', 50);
});

it('stops a barber pricing the rest of the team\'s day', function () {
    asStaff($this->barber);

    $this->withHeaders(branchHeader())->getJson('/api/v1/staff/earnings/branch')
        ->assertStatus(403);
});

it('reports a quiet day as zero rather than failing', function () {
    asStaff($this->barber);

    $this->withHeaders(branchHeader())->getJson('/api/v1/staff/earnings/today')
        ->assertOk()
        ->assertJsonPath('data.earned.cents', 0);
});

/* ------------------------------------------- the desk, from a handset */

/*
 * The shop as it actually runs: the receptionist is out, the client is
 * waiting, and the barber standing there is not the one the booking was
 * auto-assigned to. The alternative is cutting under somebody else's name,
 * which pays the wrong barber.
 */
it('lets a barber look up whoever walked in', function () {
    $appointment = bookWith($this->owner, $this->slot->addHours(3), '0781879820');

    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->getJson('/api/v1/staff/lookup?q=0781879820')
        ->assertOk()
        ->assertJsonPath('data.0.reference', $appointment->reference)
        // Offered as claimable, so the app can show "take this one" without
        // a second round trip.
        ->assertJsonPath('meta.claimable.0', $appointment->ulid);
});

it('refuses a lookup too short to mean anything', function () {
    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->getJson('/api/v1/staff/lookup?q=07')
        ->assertStatus(422);
});

it('lets a barber claim a booking and start it in one tap', function () {
    $appointment = bookWith($this->owner, $this->slot->addHours(4), '0772000222');

    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $appointment->refresh();

    expect($appointment->staff_id)->toBe($this->barber->id)
        ->and($appointment->assigned_by)->toBe($this->barber->id)
        ->and($appointment->started_by)->toBe($this->barber->id)
        ->and($appointment->started_at)->not->toBeNull();

    // The money follows the work, or the owner is paid for the barber's cut.
    expect($appointment->services->pluck('staff_id')->unique()->all())
        ->toBe([$this->barber->id]);
});

it('will not let a barber claim a cut somebody already started', function () {
    $appointment = bookWith($this->owner, $this->slot->addHours(5), '0772000333');
    $appointment->update([
        'status' => AppointmentStatus::InProgress,
        'started_at' => now(),
    ]);

    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/claim")
        ->assertForbidden();

    expect($appointment->refresh()->staff_id)->toBe($this->owner->id);
});

/*
 * This used to differ between the two surfaces: finishing at the till
 * recorded the visit against the client, finishing on a handset did not, so
 * a client's visit count depended on which screen closed their last cut.
 */
it('records the visit against the client when a barber finishes on his phone', function () {
    $appointment = bookWith($this->barber, $this->slot->addHours(6), '0772000444');

    asStaff($this->barber);

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/start")
        ->assertOk();

    $this->withHeaders(branchHeader())
        ->putJson("/api/v1/staff/appointments/{$appointment->ulid}/finish")
        ->assertOk();

    $profile = $appointment->refresh()->client->clientProfile;

    expect($profile->visit_count)->toBe(1)
        ->and($profile->last_visit_at)->not->toBeNull()
        ->and($profile->lifetime_value_cents)->toBe($appointment->total_cents);
});
