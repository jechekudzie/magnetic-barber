<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\LoyaltyLedger;
use App\Models\LoyaltyRule;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\LoyaltyService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create();

    $this->owner = User::factory()->create();
    $this->owner->branches()->attach($this->branch, ['is_primary' => true]);

    setPermissionsTeamId($this->branch->id);
    $this->owner->assignRole('owner');
    setPermissionsTeamId(null);

    $this->client = User::factory()->client()->create();
    ClientProfile::factory()->for($this->client)->create([
        'visit_count' => 0,
        'home_branch_id' => $this->branch->id,
    ]);

    $this->loyalty = app(LoyaltyService::class);
});

function completedVisit(array $overrides = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'branch_id' => test()->branch->id,
        'client_id' => test()->client->id,
        'status' => AppointmentStatus::Completed,
        'total_cents' => 1200,
    ], $overrides));
}

it('awards the per visit points when a booking is completed', function () {
    $entry = $this->loyalty->awardForVisit(completedVisit());

    expect($entry)->not->toBeNull()
        ->and($entry->points)->toBe(5)
        ->and($this->loyalty->balanceFor($this->client))->toBe(5);
});

it('awards nothing for a booking that is not completed', function () {
    $appointment = completedVisit(['status' => AppointmentStatus::Confirmed]);

    expect($this->loyalty->awardForVisit($appointment))->toBeNull()
        ->and($this->loyalty->balanceFor($this->client))->toBe(0);
});

/**
 * The failure this guards against costs real money: a double click on
 * "Complete" paying the client twice.
 */
it('never pays out twice for the same visit', function () {
    $appointment = completedVisit();

    $this->loyalty->awardForVisit($appointment);
    $this->loyalty->awardForVisit($appointment);
    $this->loyalty->awardForVisit($appointment);

    expect(LoyaltyLedger::count())->toBe(1)
        ->and($this->loyalty->balanceFor($this->client))->toBe(5);
});

it('adds points per dollar on top when the rule says so', function () {
    LoyaltyRule::create([
        'name' => 'Spend based',
        'points_per_visit' => 5,
        'points_per_currency_unit' => 1,
        'redemption_threshold' => 50,
        'redemption_value_cents' => 500,
        'is_active' => true,
    ]);

    // $12 spent at 1 point per dollar, plus 5 for the visit.
    expect($this->loyalty->awardForVisit(completedVisit())->points)->toBe(17);
});

it('is the sum of the ledger, not a stored column', function () {
    $this->loyalty->awardForVisit(completedVisit());
    $this->loyalty->adjust($this->client, 20, 'Goodwill');
    $this->loyalty->adjust($this->client, -10, 'Redeemed a wash');

    expect($this->loyalty->balanceFor($this->client))->toBe(15)
        ->and(LoyaltyLedger::count())->toBe(3);
});

it('leaves expired points out of the balance', function () {
    LoyaltyLedger::create([
        'client_id' => $this->client->id,
        'type' => 'earn',
        'points' => 40,
        'balance_after' => 40,
        'expires_at' => now()->subDay(),
    ]);

    expect($this->loyalty->balanceFor($this->client))->toBe(0);
});

it('says what a balance is worth and how far off redeeming it is', function () {
    $this->loyalty->adjust($this->client, 30, 'Seed');

    $summary = $this->loyalty->summaryFor($this->client);

    expect($summary['points'])->toBe(30)
        ->and($summary['redeemable'])->toBeFalse()
        ->and($summary['to_next'])->toBe(20)
        ->and($summary['value']['cents'])->toBe(0);

    $this->loyalty->adjust($this->client, 25, 'More');

    $summary = $this->loyalty->summaryFor($this->client);

    expect($summary['redeemable'])->toBeTrue()
        ->and($summary['value']['formatted'])->toBe('$5');
});

/* ------------------------------------------------------------------ admin */

it('earns points when a manager marks a booking complete', function () {
    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $this->client->id,
        'status' => AppointmentStatus::Confirmed,
        'total_cents' => 1200,
    ]);

    $this->actingAs($this->owner)
        ->put("/admin/bookings/{$appointment->ulid}/status", ['status' => 'completed'])
        ->assertRedirect();

    expect($this->loyalty->balanceFor($this->client))->toBe(5);

    // Completing a visit is also what makes someone a returning client.
    $profile = $this->client->clientProfile->fresh();

    expect($profile->visit_count)->toBe(1)
        ->and($profile->lifetime_value_cents)->toBe(1200)
        ->and($profile->last_visit_at)->not->toBeNull();
});

it('saves loyalty rules and the new rate applies to the next visit', function () {
    $this->actingAs($this->owner)
        ->put('/admin/loyalty', [
            'name' => 'Generous',
            'points_per_visit' => 20,
            'points_per_currency_unit' => 0,
            'redemption_threshold' => 100,
            'redemption_value' => 12.5,
            'points_expiry_months' => null,
        ])
        ->assertRedirect();

    expect(LoyaltyRule::current()->points_per_visit)->toBe(20)
        ->and(LoyaltyRule::current()->redemption_value_cents)->toBe(1250)
        ->and($this->loyalty->awardForVisit(completedVisit())->points)->toBe(20);
});

it('logs a manual adjustment against whoever made it', function () {
    $this->actingAs($this->owner)
        ->post('/admin/loyalty/adjust', [
            'client' => $this->client->ulid,
            'points' => 15,
            'reason' => 'Goodwill after a late start',
        ])
        ->assertRedirect();

    $entry = LoyaltyLedger::firstOrFail();

    expect($entry->points)->toBe(15)
        ->and($entry->created_by)->toBe($this->owner->id)
        ->and($entry->description)->toBe('Goodwill after a late start');
});

it('refuses a pointless zero adjustment', function () {
    $this->actingAs($this->owner)
        ->post('/admin/loyalty/adjust', [
            'client' => $this->client->ulid,
            'points' => 0,
            'reason' => 'Nothing',
        ])
        ->assertSessionHasErrors('points');
});

/* ------------------------------------------------------------- the diary */

it('shows the day a booking is on, not the day you happen to open', function () {
    $day = now()->addDay();

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $this->client->id,
        'status' => AppointmentStatus::Confirmed,
        'scheduled_start_at' => $day->copy()->setTime(10, 0),
        'scheduled_end_at' => $day->copy()->setTime(10, 45),
    ]);

    $this->actingAs($this->owner);

    // The grid opens on today, so tomorrow's booking is correctly absent.
    $this->get('/admin/bookings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/bookings')
            ->where('view', 'day')
            ->has('bookings', 0));

    $this->get('/admin/bookings?date='.$day->toDateString())
        ->assertInertia(fn ($page) => $page
            ->has('bookings', 1)
            ->where('summary.confirmed', 1));
});

it('gives the day grid its chairs and its opening hours', function () {
    $barber = User::factory()->create();
    StaffProfile::factory()->for($barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($barber);

    $this->branch->update(['opens_at' => '08:00', 'closes_at' => '19:00']);

    $this->actingAs($this->owner)
        ->get('/admin/bookings')
        ->assertInertia(fn ($page) => $page
            ->has('grids', 1)
            ->where('grids.0.opens_minutes', 480)
            ->where('grids.0.closes_minutes', 1140)
            ->has('grids.0.columns', 1)
            ->where('grids.0.columns.0.id', $barber->ulid));
});

it('places a booking on the grid by the minute', function () {
    $barber = User::factory()->create();
    StaffProfile::factory()->for($barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($barber);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $this->client->id,
        'staff_id' => $barber->id,
        'scheduled_start_at' => now($this->branch->timezone)->setTime(10, 30)->utc(),
        'scheduled_end_at' => now($this->branch->timezone)->setTime(11, 15)->utc(),
    ]);

    $this->actingAs($this->owner)
        ->get('/admin/bookings')
        ->assertInertia(fn ($page) => $page
            ->where('bookings.0.start_minutes', 630)
            ->where('bookings.0.end_minutes', 675)
            ->where('bookings.0.staff_id', $barber->ulid));
});

it('covers the whole week in the week view', function () {
    $this->actingAs($this->owner)
        ->get('/admin/bookings?view=week')
        ->assertInertia(fn ($page) => $page
            ->where('view', 'week')
            ->has('days', 7));
});

it('filters the list by status', function () {
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $this->client->id,
        'status' => AppointmentStatus::Confirmed,
        'scheduled_start_at' => now()->addDay()->setTime(10, 0),
        'scheduled_end_at' => now()->addDay()->setTime(10, 45),
    ]);

    $this->actingAs($this->owner);

    $this->get('/admin/bookings?view=list')
        ->assertInertia(fn ($page) => $page->has('bookings', 1));

    $this->get('/admin/bookings?view=list&status=completed')
        ->assertInertia(fn ($page) => $page->has('bookings', 0));
});

/**
 * An owner watches the group; a manager only sees the branch they run.
 */
it('offers every branch to an owner and only their own to a manager', function () {
    $other = Branch::factory()->create();

    $manager = User::factory()->create();
    $manager->branches()->attach($this->branch, ['is_primary' => true]);
    setPermissionsTeamId($this->branch->id);
    $manager->assignRole('branch-manager');
    setPermissionsTeamId(null);

    // Every branch, plus the "All branches" entry.
    $expected = Branch::count() + 1;

    $this->actingAs($this->owner)
        ->get('/admin/bookings')
        ->assertInertia(fn ($page) => $page->has('scopes', $expected));

    // The manager works at one branch, so sees that and nothing else.
    $this->actingAs($manager)
        ->get('/admin/bookings')
        ->assertInertia(fn ($page) => $page
            ->has('scopes', 2)
            ->where('scopes.1.value', $this->branch->slug));

    expect($other->slug)->not->toBe($this->branch->slug);
});
