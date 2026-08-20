<?php

use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\ClientProfile;
use App\Models\ReminderSchedule;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\ReminderService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'code' => 'MB',
        'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->reception = User::factory()->create();
    $this->reception->branches()->attach($this->branch, ['is_primary' => true]);

    $this->barber = User::factory()->create();
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($this->barber, ['is_primary' => true]);

    setPermissionsTeamId($this->branch->id);
    $this->reception->assignRole('receptionist');
    $this->barber->assignRole('barber');
    setPermissionsTeamId(null);

    $this->service = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->service, [
        'price_cents' => 1200,
        'currency' => 'USD',
        'duration_minutes' => 45,
        'is_active' => true,
    ]);

    $this->style = Style::factory()->create(['code' => '01', 'service_id' => $this->service->id]);

    $this->tomorrow = now($this->branch->timezone)->addDay();
});

function deskPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Walk In Client',
        'phone' => '0781879820',
        'service_ids' => [test()->service->ulid],
        'style' => test()->style->ulid,
        'staff' => test()->barber->ulid,
        'date' => test()->tomorrow->toDateString(),
        'time' => '10:00',
        'note' => 'Grade 1 on the sides',
    ], $overrides);
}

it('renders the desk booking form with the menu and the gallery', function () {
    $this->actingAs($this->reception)
        ->get('/admin/bookings/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/booking-form')
            ->has('categories.0.services', 1)
            ->has('styles', 1)
            ->has('barbers', 1));
});

it('records a booking for a new client and opens their account', function () {
    $this->actingAs($this->reception)
        ->post('/admin/bookings', deskPayload())
        ->assertRedirect();

    $appointment = Appointment::firstOrFail();

    expect($appointment->type)->toBe(AppointmentType::Scheduled)
        // Attributed to the desk, so the channel report stays honest.
        ->and($appointment->source)->toBe('reception')
        ->and($appointment->style_id)->toBe($this->style->id)
        ->and($appointment->client_note)->toBe('Grade 1 on the sides')
        ->and($appointment->total_cents)->toBe(1200)
        ->and($appointment->client->clientProfile->account_number)->toBe('MB-0001');
});

it('records the chosen cut against the booking', function () {
    $this->actingAs($this->reception)->post('/admin/bookings', deskPayload());

    expect(Appointment::firstOrFail()->style->code)->toBe('01');
});

it('books an existing client without letting reception rename them', function () {
    $client = User::factory()->client()->create(['name' => 'Tendai Moyo']);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    $this->actingAs($this->reception)
        ->post('/admin/bookings', deskPayload([
            'client' => $client->ulid,
            'name' => 'Someone Else',
            'phone' => '0789999999',
        ]))
        ->assertRedirect();

    $appointment = Appointment::firstOrFail();

    expect($appointment->client_id)->toBe($client->id)
        ->and($client->fresh()->name)->toBe('Tendai Moyo')
        ->and(User::count())->toBe(3);
});

it('refuses a booking with neither a client nor a name', function () {
    $this->actingAs($this->reception)
        ->post('/admin/bookings', deskPayload(['name' => null, 'phone' => null]))
        ->assertSessionHasErrors(['name', 'phone']);
});

it('reports a clash on the time field rather than throwing', function () {
    $this->actingAs($this->reception)->post('/admin/bookings', deskPayload());

    $this->actingAs($this->reception)
        ->post('/admin/bookings', deskPayload(['phone' => '0782222222']))
        ->assertSessionHasErrors('time');

    expect(Appointment::count())->toBe(1);
});

it('finds a returning client from a partial name or number', function () {
    $client = User::factory()->client()->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263781879820',
    ]);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    $this->actingAs($this->reception);

    expect($this->getJson('/admin/bookings/clients?q=Tend')->json('data'))->toHaveCount(1);
    // The last digits are what reception actually gets told at the desk.
    expect($this->getJson('/admin/bookings/clients?q=9820')->json('data'))->toHaveCount(1);
    expect($this->getJson('/admin/bookings/clients?q=zzzz')->json('data'))->toBeEmpty();
});

it('stops chasing a client the moment reception books them', function () {
    $client = User::factory()->client()->create(['phone' => '+263781879820']);
    ClientProfile::factory()->for($client)->create([
        'home_branch_id' => $this->branch->id,
        'visit_count' => 3,
        'last_visit_at' => now()->subDays(40),
    ]);

    app(ReminderService::class)->schedule($this->branch);

    expect(ReminderSchedule::query()->pending()->count())->toBe(1);

    $this->actingAs($this->reception)
        ->post('/admin/bookings', deskPayload(['client' => $client->ulid]))
        ->assertRedirect();

    expect(ReminderSchedule::query()->pending()->count())->toBe(0);
});

/**
 * The rule the spec asks to be defended: a barber may look a client up and
 * see who is in their chair, but never their phone number. It is how a shop
 * loses its client list when a barber leaves.
 */
it('hides client phone numbers from a barber', function () {
    $client = User::factory()->client()->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263781879820',
    ]);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    $asBarber = $this->actingAs($this->barber)
        ->getJson('/admin/bookings/clients?q=Tendai')
        ->json('data.0.phone');

    expect($asBarber)->not->toBe('+263781879820')
        ->and($asBarber)->toBe('+2637****820');

    // Reception is trusted with it, because they have to chase no shows.
    expect(
        $this->actingAs($this->reception)
            ->getJson('/admin/bookings/clients?q=Tendai')
            ->json('data.0.phone')
    )->toBe('+263781879820');
});

/**
 * Stronger than masking: a barber cannot open the branch diary at all. They
 * hold appointment.view.own, not appointment.view.branch.
 */
it('keeps the whole branch diary away from a barber', function () {
    $this->actingAs($this->barber)
        ->get('/admin/bookings')
        ->assertForbidden();
});

it('still gives reception the number they need to chase a no show', function () {
    $client = User::factory()->client()->create(['phone' => '+263781879820']);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $client->id,
        'staff_id' => $this->barber->id,
        'scheduled_start_at' => now($this->branch->timezone)->setTime(10, 0)->utc(),
        'scheduled_end_at' => now($this->branch->timezone)->setTime(10, 45)->utc(),
    ]);

    $this->actingAs($this->reception)
        ->get('/admin/bookings')
        ->assertInertia(fn ($page) => $page
            ->where('bookings.0.client.phone', '+263781879820'));
});

it('lets a barber take a booking, because they may book their own chair', function () {
    $this->actingAs($this->barber)
        ->get('/admin/bookings/create')
        ->assertOk();
});

it('finds a client from the number the way reception hears it', function () {
    $client = User::factory()->client()->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263781879820',
    ]);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    // Local, spaced local, and full international all reach the same person.
    foreach (['0781879820', '078 187 9820', '+263781879820', '781879820'] as $typed) {
        $this->actingAs($this->reception)
            ->getJson('/admin/bookings/clients?q='.urlencode($typed))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tendai Moyo');
    }
});

it('records a booking from just a name, a number, a cut and a time', function () {
    // The desk minimum. No barber, no style, no note: reception is on the
    // phone and the client has said four things.
    $this->actingAs($this->reception)
        ->post('/admin/bookings', [
            'name' => 'Farai Chikwanha',
            'phone' => '0782223344',
            'service_ids' => [$this->service->ulid],
            'date' => now($this->branch->timezone)->addDay()->toDateString(),
            'time' => '10:00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $appointment = Appointment::firstOrFail();

    // No barber named means the shop picks one, rather than leaving a
    // booking nobody owns.
    expect($appointment->staff_id)->not->toBeNull()
        ->and($appointment->style_id)->toBeNull()
        ->and($appointment->client->name)->toBe('Farai Chikwanha')
        ->and($appointment->client->phone)->toBe('+263782223344');
});

it('books an existing client even when the barber only sees a masked number', function () {
    $client = User::factory()->client()->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263781879820',
    ]);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    // What a barber's screen actually holds after picking from live search.
    $this->actingAs($this->barber)
        ->post('/admin/bookings', [
            'client' => $client->ulid,
            'name' => 'Tendai Moyo',
            'phone' => '+2637****820',
            'service_ids' => [$this->service->ulid],
            'date' => now($this->branch->timezone)->addDay()->toDateString(),
            'time' => '11:00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Appointment::firstOrFail()->client->phone)->toBe('+263781879820');
});

/* ------------------------------------------------------------- loyalty */

it('takes a reward off the bill when reception spends the points', function () {
    $client = User::factory()->client()->create(['phone' => '+263781879820']);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    // 50 points is one $5 reward under the default rule.
    app(LoyaltyService::class)->adjust($client, 50, 'Opening balance');

    $this->actingAs($this->reception)
        ->post('/admin/bookings', [
            'client' => $client->ulid,
            'service_ids' => [$this->service->ulid],
            'date' => now($this->branch->timezone)->addDay()->toDateString(),
            'time' => '10:00',
            'redeem_points' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $appointment = Appointment::firstOrFail();

    expect($appointment->subtotal_cents)->toBe(1200)
        ->and($appointment->discount_cents)->toBe(500)
        ->and($appointment->total_cents)->toBe(700)
        // Spent, not merely displayed: the balance has actually moved.
        ->and(app(LoyaltyService::class)->balanceFor($client))->toBe(0);
});

it('leaves the bill alone when reception does not spend the points', function () {
    $client = User::factory()->client()->create(['phone' => '+263781879820']);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);
    app(LoyaltyService::class)->adjust($client, 50, 'Opening balance');

    $this->actingAs($this->reception)
        ->post('/admin/bookings', [
            'client' => $client->ulid,
            'service_ids' => [$this->service->ulid],
            'date' => now($this->branch->timezone)->addDay()->toDateString(),
            'time' => '10:00',
        ])->assertRedirect();

    expect(Appointment::firstOrFail()->discount_cents)->toBe(0)
        ->and(app(LoyaltyService::class)->balanceFor($client))->toBe(50);
});

it('never discounts more than the bill', function () {
    $client = User::factory()->client()->create(['phone' => '+263781879820']);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);

    // Five rewards ($25) against a $12 cut. Points buy a cut, not credit.
    app(LoyaltyService::class)->adjust($client, 250, 'Long standing regular');

    $this->actingAs($this->reception)
        ->post('/admin/bookings', [
            'client' => $client->ulid,
            'service_ids' => [$this->service->ulid],
            'date' => now($this->branch->timezone)->addDay()->toDateString(),
            'time' => '10:00',
            'redeem_points' => true,
        ])->assertRedirect();

    $appointment = Appointment::firstOrFail();

    expect($appointment->discount_cents)->toBe(1000)
        ->and($appointment->total_cents)->toBe(200)
        // Only the two blocks actually used are spent.
        ->and(app(LoyaltyService::class)->balanceFor($client))->toBe(150);
});

it('cannot spend points a new client does not have', function () {
    $this->actingAs($this->reception)
        ->post('/admin/bookings', [
            'name' => 'Farai Chikwanha',
            'phone' => '0782223344',
            'service_ids' => [$this->service->ulid],
            'date' => now($this->branch->timezone)->addDay()->toDateString(),
            'time' => '10:00',
            'redeem_points' => true,
        ])->assertRedirect();

    expect(Appointment::firstOrFail()->discount_cents)->toBe(0);
});

it('shows reception what a returning client has saved up', function () {
    $client = User::factory()->client()->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263781879820',
    ]);
    ClientProfile::factory()->for($client)->create(['home_branch_id' => $this->branch->id]);
    app(LoyaltyService::class)->adjust($client, 60, 'Opening balance');

    $this->actingAs($this->reception)
        ->getJson('/admin/bookings/clients?q=0781879820')
        ->assertOk()
        ->assertJsonPath('data.0.loyalty.points', 60)
        ->assertJsonPath('data.0.loyalty.redeemable', true)
        ->assertJsonPath('data.0.loyalty.value.formatted', '$5');
});
