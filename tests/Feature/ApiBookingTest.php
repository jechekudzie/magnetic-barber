<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClientAccountService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'slug' => 'harare-avenues',
        'code' => 'MB',
        'timezone' => 'Africa/Harare',
        'opens_at' => '08:00',
        'closes_at' => '19:00',
        'days_open' => [0, 1, 2, 3, 4, 5, 6],
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

    // A weekday mid-morning, well inside opening hours.
    $this->slot = CarbonImmutable::now($this->branch->timezone)
        ->addWeek()->next(CarbonImmutable::TUESDAY)->setTime(10, 0);
});

function asClient(?User $user = null): User
{
    $user = $user ?? test()->client;
    Sanctum::actingAs($user, ['client']);

    return $user;
}

function bookPayload(array $overrides = []): array
{
    return array_merge([
        'branch' => test()->branch->slug,
        'service_ids' => [test()->cut->ulid],
        'staff' => test()->barber->ulid,
        'start' => test()->slot->toIso8601String(),
        'type' => 'scheduled',
    ], $overrides);
}

/* ------------------------------------------------- booking without login */

function guestPayload(array $overrides = []): array
{
    return array_merge(bookPayload(), [
        'name' => 'Farai Sibanda',
        'phone' => '0782000111',
    ], $overrides);
}

it('books without an account, the way the website does', function () {
    $response = $this->postJson('/api/v1/bookings/guest', guestPayload());

    $response->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.total.cents', 1000);

    expect(Appointment::count())->toBe(1);
});

it('creates the account on that first booking', function () {
    expect(User::where('phone', '+263782000111')->exists())->toBeFalse();

    $this->postJson('/api/v1/bookings/guest', guestPayload())->assertCreated();

    $client = User::where('phone', '+263782000111')->sole();

    expect($client->name)->toBe('Farai Sibanda')
        ->and($client->clientProfile)->not->toBeNull()
        ->and($client->clientProfile->account_number)->toStartWith('MB-');
});

it('lands a returning number on the record it already has', function () {
    $this->postJson('/api/v1/bookings/guest', guestPayload())->assertCreated();

    $this->postJson('/api/v1/bookings/guest', guestPayload([
        'start' => $this->slot->addHours(3)->toIso8601String(),
    ]))->assertCreated();

    // One client with two bookings, not two clients with one each.
    expect(User::where('phone', '+263782000111')->count())->toBe(1)
        ->and(Appointment::count())->toBe(2);
});

it('books a signed in client against their existing account', function () {
    asClient();

    $this->postJson('/api/v1/bookings/guest', guestPayload([
        'phone' => $this->client->phone,
        'name' => 'Whatever They Typed',
    ]))->assertCreated();

    expect(Appointment::sole()->client_id)->toBe($this->client->id)
        // The name on file is not overwritten by what a booking form said.
        ->and($this->client->fresh()->name)->toBe('Tendai Moyo');
});

it('refuses a guest booking with no name', function () {
    $this->postJson('/api/v1/bookings/guest', guestPayload(['name' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('refuses a guest booking with a number that is not a mobile', function () {
    $this->postJson('/api/v1/bookings/guest', guestPayload(['phone' => '12345']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('still refuses a slot somebody already holds', function () {
    $this->postJson('/api/v1/bookings/guest', guestPayload())->assertCreated();

    $this->postJson('/api/v1/bookings/guest', guestPayload(['phone' => '0782000222']))
        ->assertStatus(409);
});

it('does not hand a guest booking any way to read the account', function () {
    $response = $this->postJson('/api/v1/bookings/guest', guestPayload());

    // No token: making a booking must not be a way to take over a number.
    expect($response->json())->not->toHaveKey('token');

    $this->getJson('/api/v1/bookings')->assertStatus(401);
});

/* ---------------------------------------------------------- availability */

it('shows the slot grid without anyone signing in', function () {
    $this->getJson('/api/v1/availability?'.http_build_query([
        'branch' => $this->branch->slug,
        'date' => $this->slot->toDateString(),
        'service_ids' => [$this->cut->ulid],
    ]))->assertOk()->assertJsonStructure(['date', 'duration_minutes', 'staff', 'any_staff']);
});

it('opens the picker on a day that can actually take a booking', function () {
    // The shop shuts at 19:00. At 18:30 a 45 minute cut no longer fits, so
    // today is the wrong day to open on and the answer must be tomorrow.
    $closingTime = CarbonImmutable::now($this->branch->timezone)->setTime(18, 30);
    $this->travelTo($closingTime->utc());

    $response = $this->getJson('/api/v1/availability/first?'.http_build_query([
        'branch' => $this->branch->slug,
        'service_ids' => [$this->cut->ulid],
    ]));

    $response->assertOk()
        ->assertJsonPath('today', $closingTime->toDateString())
        ->assertJsonPath('has_availability', true)
        ->assertJsonPath('date', $closingTime->addDay()->toDateString());
});

it('opens on today when there is still time to sit down', function () {
    $morning = CarbonImmutable::now($this->branch->timezone)->setTime(9, 0);
    $this->travelTo($morning->utc());

    $this->getJson('/api/v1/availability/first?'.http_build_query([
        'branch' => $this->branch->slug,
        'service_ids' => [$this->cut->ulid],
    ]))->assertOk()->assertJsonPath('date', $morning->toDateString());
});

it('says plainly when nothing is bookable rather than returning an empty grid', function () {
    // No bookable staff at all is the "call the shop" case.
    StaffProfile::where('user_id', $this->barber->id)->update(['is_bookable' => false]);

    $this->getJson('/api/v1/availability/first?'.http_build_query([
        'branch' => $this->branch->slug,
        'service_ids' => [$this->cut->ulid],
    ]))->assertOk()
        ->assertJsonPath('has_availability', false)
        ->assertJsonPath('date', null);
});

it('tells the app which price card each service is on', function () {
    $response = $this->getJson("/api/v1/branches/{$this->branch->slug}/services");

    $response->assertOk();

    $audiences = collect($response->json('data'))
        ->flatMap(fn (array $category) => $category['services'] ?? [])
        ->pluck('audience')
        ->unique()
        ->values();

    expect($audiences)->not->toBeEmpty()
        ->and($audiences->every(fn ($a) => in_array($a, ['men', 'women', 'unisex', 'kids'], true)))
        ->toBeTrue();
});

it('refuses a branch that does not exist', function () {
    $this->getJson('/api/v1/availability?branch=nowhere&date='.$this->slot->toDateString())
        ->assertStatus(422)->assertJsonValidationErrors('branch');
});

/* -------------------------------------------------------------- booking */

it('books a chair for a signed in client', function () {
    asClient();

    $response = $this->postJson('/api/v1/bookings', bookPayload());

    $response->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.total.cents', 1000)
        ->assertJsonStructure(['data' => ['id', 'reference', 'when_label', 'services']]);

    expect(Appointment::where('client_id', $this->client->id)->count())->toBe(1);
});

it('attributes the booking to the app, and to the barber for commission', function () {
    asClient();

    $this->postJson('/api/v1/bookings', bookPayload())->assertCreated();

    $appointment = Appointment::sole();
    $line = $appointment->services()->sole();

    expect($appointment->source)->toBe('app')
        ->and($line->staff_id)->toBe($this->barber->id)
        ->and($line->commission_cents)->toBe(50);
});

it('takes the phone number from the account, not the request body', function () {
    asClient();

    // A body that tries to book as somebody else entirely.
    $this->postJson('/api/v1/bookings', bookPayload([
        'name' => 'Someone Else',
        'phone' => '0782222222',
    ]))->assertCreated();

    expect(Appointment::sole()->client_id)->toBe($this->client->id)
        ->and(User::where('phone', '+263782222222')->exists())->toBeFalse();
});

it('refuses a second booking in a slot already taken', function () {
    asClient();

    $this->postJson('/api/v1/bookings', bookPayload())->assertCreated();

    $other = asClient(app(ClientAccountService::class)
        ->register('Rudo Chikafu', '0782000099', $this->branch));

    // 409 Conflict, not 422: the request was well formed, the world moved.
    $this->postJson('/api/v1/bookings', bookPayload())->assertStatus(409);
    expect($other->appointments()->count())->toBe(0);
});

it('refuses a house call with no address', function () {
    asClient();

    $this->postJson('/api/v1/bookings', bookPayload(['type' => 'house_call']))
        ->assertStatus(422)->assertJsonValidationErrors('address_line');
});

it('turns an anonymous booking away', function () {
    $this->postJson('/api/v1/bookings', bookPayload())->assertStatus(401);
});

it('refuses a staff token on the client surface', function () {
    Sanctum::actingAs($this->barber, ['staff']);

    $this->postJson('/api/v1/bookings', bookPayload())->assertStatus(403);
});

/* ------------------------------------------------------- reading my book */

it('lists only my own bookings', function () {
    asClient();
    $this->postJson('/api/v1/bookings', bookPayload())->assertCreated();

    $other = app(ClientAccountService::class)->register('Rudo Chikafu', '0782000099', $this->branch);
    asClient($other);
    $this->postJson('/api/v1/bookings', bookPayload([
        'start' => $this->slot->addHours(3)->toIso8601String(),
    ]))->assertCreated();

    asClient();
    $response = $this->getJson('/api/v1/bookings');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('hides someone else\'s booking behind a 404, not a 403', function () {
    asClient();
    $mine = $this->postJson('/api/v1/bookings', bookPayload())->json('data.id');

    $other = app(ClientAccountService::class)->register('Rudo Chikafu', '0782000099', $this->branch);
    asClient($other);

    $this->getJson("/api/v1/bookings/{$mine}")->assertStatus(404);
});

it('separates upcoming from past', function () {
    asClient();
    $this->postJson('/api/v1/bookings', bookPayload())->assertCreated();

    Appointment::sole()->forceFill([
        'scheduled_start_at' => now()->subWeek(),
        'scheduled_end_at' => now()->subWeek()->addMinutes(45),
    ])->save();

    expect($this->getJson('/api/v1/bookings?scope=upcoming')->json('data'))->toHaveCount(0)
        ->and($this->getJson('/api/v1/bookings?scope=past')->json('data'))->toHaveCount(1);
});

/* ---------------------------------------------------------- cancellation */

it('lets a client cancel in good time and frees the slot', function () {
    asClient();
    $id = $this->postJson('/api/v1/bookings', bookPayload())->json('data.id');

    $this->postJson("/api/v1/bookings/{$id}/cancel", ['reason' => 'Something came up'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $appointment = Appointment::sole();

    expect($appointment->slot_key)->toBeNull();

    // The freed slot is bookable again.
    $this->postJson('/api/v1/bookings', bookPayload())->assertCreated();
});

it('refuses a late cancellation and sends them to the phone', function () {
    asClient();
    $id = $this->postJson('/api/v1/bookings', bookPayload())->json('data.id');

    Appointment::sole()->forceFill([
        'scheduled_start_at' => now()->addMinutes(30),
    ])->save();

    $this->postJson("/api/v1/bookings/{$id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('message', 'It is too late to cancel this online. Please call the shop.');

    expect(Appointment::sole()->status)->toBe(AppointmentStatus::Confirmed);
});

it('tells the app whether a booking can still be cancelled', function () {
    asClient();
    $this->postJson('/api/v1/bookings', bookPayload())->assertCreated();

    expect($this->getJson('/api/v1/bookings')->json('data.0.client_may_cancel'))->toBeTrue();
});

it('will not let a client cancel a visit that already happened', function () {
    asClient();
    $id = $this->postJson('/api/v1/bookings', bookPayload())->json('data.id');

    Appointment::sole()->update(['status' => AppointmentStatus::Completed, 'completed_at' => now()]);

    $this->postJson("/api/v1/bookings/{$id}/cancel")->assertStatus(422);
});
