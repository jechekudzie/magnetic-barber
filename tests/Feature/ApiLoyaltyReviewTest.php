<?php

use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Review;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ClientAccountService;
use App\Services\LoyaltyService;
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

    $this->barber = User::factory()->create(['name' => 'Tapiwa Nyoni']);
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($this->barber, ['is_primary' => true]);

    $this->cut = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1000, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);

    $this->client = app(ClientAccountService::class)
        ->register('Tendai Moyo', '0781879820', $this->branch);

    $this->appointment = app(BookingService::class)->book($this->branch, [
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->cut->id],
        'staff_id' => $this->barber->id,
        'start' => CarbonImmutable::now($this->branch->timezone)->addWeek()->setTime(10, 0)->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);
});

function completeIt(): void
{
    test()->appointment->update([
        'status' => AppointmentStatus::Completed,
        'completed_at' => now(),
    ]);

    app(LoyaltyService::class)->awardForVisit(test()->appointment->refresh());
}

/* ------------------------------------------------------------- loyalty */

it('reports a balance and what earned it', function () {
    completeIt();
    Sanctum::actingAs($this->client, ['client']);

    $response = $this->getJson('/api/v1/loyalty');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['points', 'redeemable', 'threshold', 'value', 'reward', 'to_next'],
            'history' => [['points', 'balance_after', 'description', 'reference', 'at']],
        ]);

    expect($response->json('data.points'))->toBeGreaterThan(0)
        // Every line says what it was for.
        ->and($response->json('history.0.reference'))->toBe($this->appointment->reference);
});

it('starts a new client on nothing, without failing', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->getJson('/api/v1/loyalty')
        ->assertOk()
        ->assertJsonPath('data.points', 0)
        ->assertJsonPath('data.redeemable', false);
});

it('says how many points are still needed to spend any', function () {
    Sanctum::actingAs($this->client, ['client']);

    $response = $this->getJson('/api/v1/loyalty');

    expect($response->json('data.to_next'))->toBe($response->json('data.threshold'));
});

it('keeps one client\'s points away from another', function () {
    completeIt();

    $other = app(ClientAccountService::class)->register('Rudo', '0782000099', $this->branch);
    Sanctum::actingAs($other, ['client']);

    $this->getJson('/api/v1/loyalty')
        ->assertOk()
        ->assertJsonPath('data.points', 0)
        ->assertJsonPath('history', []);
});

it('turns an anonymous caller away from points', function () {
    $this->getJson('/api/v1/loyalty')->assertStatus(401);
});

/* ------------------------------------------------------------- ratings */

it('rates a cut that has happened', function () {
    completeIt();
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", [
        'rating' => 5,
        'comment' => 'Sharpest fade in the Avenues.',
    ])->assertCreated()->assertJsonPath('data.rating', 5);

    $review = Review::sole();

    expect($review->appointment_id)->toBe($this->appointment->id)
        // Tied to the barber, so a score can be attributed later.
        ->and($review->staff_id)->toBe($this->barber->id)
        // The shop decides what goes on its own website.
        ->and($review->is_public)->toBeFalse();
});

it('refuses to rate a cut that has not happened yet', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", ['rating' => 5])
        ->assertStatus(422);

    expect(Review::count())->toBe(0);
});

it('refuses a rating outside one to five', function () {
    completeIt();
    Sanctum::actingAs($this->client, ['client']);

    foreach ([0, 6, -1] as $rating) {
        $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", ['rating' => $rating])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }
});

it('lets a client change their mind rather than leaving two reviews', function () {
    completeIt();
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", ['rating' => 2])
        ->assertCreated();
    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", ['rating' => 5])
        ->assertCreated();

    expect(Review::count())->toBe(1)
        ->and(Review::sole()->rating)->toBe(5);
});

it('hides somebody else\'s visit from rating', function () {
    completeIt();

    $other = app(ClientAccountService::class)->register('Rudo', '0782000099', $this->branch);
    Sanctum::actingAs($other, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", ['rating' => 1])
        ->assertStatus(404);
});

it('hands back a rating already given, so nobody is asked twice', function () {
    completeIt();
    Sanctum::actingAs($this->client, ['client']);

    $this->getJson("/api/v1/bookings/{$this->appointment->ulid}/review")
        ->assertOk()
        ->assertJsonPath('data', null);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/review", ['rating' => 4]);

    $this->getJson("/api/v1/bookings/{$this->appointment->ulid}/review")
        ->assertOk()
        ->assertJsonPath('data.rating', 4);
});
