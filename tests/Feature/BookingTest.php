<?php

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\SlotTakenException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Models\User;
use App\Services\BookingService;

beforeEach(function () {
    $this->branch = Branch::factory()->create([
        'code' => 'MB',
        'opens_at' => '08:00',
        'closes_at' => '19:00',
        'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->barber = User::factory()->create();
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($this->barber, ['is_primary' => true]);

    $this->service = Service::factory()->create([
        'default_duration_minutes' => 45,
        'buffer_minutes' => 0,
    ]);
    $this->branch->services()->attach($this->service, [
        'price_cents' => 1200,
        'currency' => 'USD',
        'duration_minutes' => 45,
        'is_active' => true,
    ]);

    $this->booking = app(BookingService::class);

    $this->slot = now()->addDay()->setTime(10, 0)->utc();
});

function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [test()->service->id],
        'staff_id' => test()->barber->id,
        'start' => test()->slot->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ], $overrides);
}

it('creates a client and an appointment on a first booking', function () {
    $appointment = $this->booking->book($this->branch, bookingPayload());

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->reference)->toStartWith('MB-A')
        ->and($appointment->total_cents)->toBe(1200)
        ->and($appointment->duration_minutes)->toBe(45);

    $client = $appointment->client;

    expect($client->phone)->toBe('+263781879820')
        ->and($client->clientProfile->account_number)->toBe('MB-0001');
});

it('reuses the existing client when they book again from a different phone format', function () {
    $this->booking->book($this->branch, bookingPayload());
    $second = $this->booking->book($this->branch, bookingPayload([
        'phone' => '+263 78 187 9820',
        'start' => $this->slot->copy()->addHours(3)->toIso8601String(),
    ]));

    expect(User::whereNotNull('phone')->where('phone', '+263781879820')->count())->toBe(1)
        ->and($second->client->clientProfile->account_number)->toBe('MB-0001');
});

it('captures the price at booking time so a later change cannot rewrite it', function () {
    $appointment = $this->booking->book($this->branch, bookingPayload());

    $this->branch->services()->updateExistingPivot($this->service->id, ['price_cents' => 9900]);

    $line = $appointment->services()->first();

    expect($line->price_cents)->toBe(1200)
        ->and($line->name_snapshot)->toBe($this->service->name);
});

it('refuses a second booking that overlaps the same barber', function () {
    $this->booking->book($this->branch, bookingPayload());

    expect(fn () => $this->booking->book($this->branch, bookingPayload([
        'phone' => '0782222222',
        // Starts 15 minutes in, so it overlaps without being identical.
        'start' => $this->slot->copy()->addMinutes(15)->toIso8601String(),
    ])))->toThrow(SlotTakenException::class);
});

it('lets a cancelled booking free its slot again', function () {
    $first = $this->booking->book($this->branch, bookingPayload());
    $first->update(['status' => AppointmentStatus::Cancelled]);

    $second = $this->booking->book($this->branch, bookingPayload(['phone' => '0782222222']));

    expect($second->id)->not->toBe($first->id);
});

it('refuses a booking in the past', function () {
    expect(fn () => $this->booking->book($this->branch, bookingPayload([
        'start' => now()->subHour()->toIso8601String(),
    ])))->toThrow(SlotTakenException::class);
});

it('assigns any free barber when none is requested', function () {
    $appointment = $this->booking->book($this->branch, bookingPayload(['staff_id' => null]));

    expect($appointment->staff_id)->toBe($this->barber->id);
});

it('refuses when every barber is already busy at that time', function () {
    $this->booking->book($this->branch, bookingPayload());

    expect(fn () => $this->booking->book($this->branch, bookingPayload([
        'phone' => '0782222222',
        'staff_id' => null,
    ])))->toThrow(SlotTakenException::class);
});

it('recognises a returning client by any format of their number', function () {
    $this->booking->book($this->branch, bookingPayload());

    $found = $this->booking->lookup('078 187 9820');

    expect($found['found'])->toBeTrue()
        ->and($found['first_name'])->toBe('Tendai')
        ->and($found['account_number'])->toBe('MB-0001');
});

it('gives away nothing about a number it does not know', function () {
    $unknown = $this->booking->lookup('0789999999');

    expect($unknown['found'])->toBeFalse()
        ->and($unknown['first_name'])->toBeNull()
        ->and($unknown['account_number'])->toBeNull();
});

it('returns only the first name, never the full one', function () {
    $this->booking->book($this->branch, bookingPayload(['name' => 'Tendai Farai Moyo']));

    expect($this->booking->lookup('0781879820')['first_name'])->toBe('Tendai');
});

it('lists a client\'s upcoming bookings but not their past ones', function () {
    $appointment = $this->booking->book($this->branch, bookingPayload());

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $appointment->client_id,
        'scheduled_start_at' => now()->subWeek(),
        'scheduled_end_at' => now()->subWeek()->addMinutes(45),
    ]);

    expect($this->booking->upcomingFor('0781879820'))->toHaveCount(1);
});

/* ------------------------------------------------------------ HTTP surface */

it('books end to end through the site and lands on the confirmation', function () {
    $response = $this->post('/book', [
        'type' => 'scheduled',
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->service->ulid],
        'staff' => $this->barber->ulid,
        'start' => $this->slot->toIso8601String(),
    ]);

    $appointment = Appointment::firstOrFail();

    $response->assertRedirect("/booked/{$appointment->ulid}");

    $this->get("/booked/{$appointment->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('site/booked')
            ->where('appointment.reference', $appointment->reference));
});

it('rejects a booking with a number that is not a Zimbabwean mobile', function () {
    $this->post('/book', [
        'type' => 'scheduled',
        'name' => 'Tendai Moyo',
        'phone' => '12345',
        'service_ids' => [$this->service->ulid],
        'start' => $this->slot->toIso8601String(),
    ])->assertSessionHasErrors('phone');
});

it('answers the lookup endpoint without exposing the full record', function () {
    $this->booking->book($this->branch, bookingPayload());

    $response = $this->postJson('/book/lookup', ['phone' => '0781879820'])->assertOk();

    expect($response->json('data.found'))->toBeTrue()
        ->and($response->json('data.first_name'))->toBe('Tendai')
        ->and($response->json())->not->toHaveKey('data.notes');
});

it('never exposes a sequential service id to the booking form', function () {
    $this->get('/book')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('categories.0.services.0.id', $this->service->ulid));
});

/* ---------------------------------------------------------------- house calls */

it('books a house call with an address and a travel fee', function () {
    $this->branch->update(['house_call_enabled' => true, 'house_call_fee_cents' => 500]);
    $this->barber->staffProfile->update(['accepts_house_calls' => true]);

    $appointment = $this->booking->book($this->branch, bookingPayload([
        'type' => AppointmentType::HouseCall,
        'address' => [
            'address_line' => '12 Northolt Drive',
            'area' => 'Mount Pleasant',
            'directions_note' => 'Green gate, second house',
        ],
    ]));

    expect($appointment->isHouseCall())->toBeTrue()
        ->and($appointment->travel_fee_cents)->toBe(500)
        // Service is $12, travel is $5.
        ->and($appointment->total_cents)->toBe(1700)
        ->and($appointment->houseCall->address_line)->toBe('12 Northolt Drive')
        ->and($appointment->houseCall->directions_note)->toBe('Green gate, second house');
});

it('adds no travel fee to a booking at the shop', function () {
    $appointment = $this->booking->book($this->branch, bookingPayload());

    expect($appointment->travel_fee_cents)->toBe(0)
        ->and($appointment->total_cents)->toBe(1200)
        ->and($appointment->houseCall()->exists())->toBeFalse();
});

it('refuses a house call without an address', function () {
    $this->branch->update(['house_call_enabled' => true]);

    expect(fn () => $this->booking->book($this->branch, bookingPayload([
        'type' => AppointmentType::HouseCall,
        'address' => null,
    ])))->toThrow(SlotTakenException::class);
});

it('refuses a house call at a branch that does not travel', function () {
    $this->branch->update(['house_call_enabled' => false]);

    expect(fn () => $this->booking->book($this->branch, bookingPayload([
        'type' => AppointmentType::HouseCall,
        'address' => ['address_line' => '12 Northolt Drive', 'area' => null, 'directions_note' => null],
    ])))->toThrow(SlotTakenException::class);
});

it('will not assign a house call to a barber who does not travel', function () {
    $this->branch->update(['house_call_enabled' => true]);
    $this->barber->staffProfile->update(['accepts_house_calls' => false]);

    expect(fn () => $this->booking->book($this->branch, bookingPayload([
        'type' => AppointmentType::HouseCall,
        'staff_id' => null,
        'address' => ['address_line' => '12 Northolt Drive', 'area' => null, 'directions_note' => null],
    ])))->toThrow(SlotTakenException::class);
});

it('requires an address over http when the type is a house call', function () {
    $this->branch->update(['house_call_enabled' => true]);

    $this->post('/book', [
        'type' => 'house_call',
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->service->ulid],
        'start' => $this->slot->toIso8601String(),
    ])->assertSessionHasErrors('address_line');
});

/* ------------------------------------------------------- booking from the gallery */

it('carries a style picked in the gallery into the booking wizard', function () {
    $style = Style::factory()->create([
        'code' => '01',
        'name' => 'Low Fade',
        'service_id' => $this->service->id,
    ]);

    $this->get("/book?style={$style->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preselectedStyle.code', '01')
            ->where('preselectedStyle.name', 'Low Fade')
            // The service it is booked as comes through priced, so the wizard
            // can preselect it rather than making the client find it again.
            ->where('preselectedStyle.service.id', $this->service->ulid)
            ->where('preselectedStyle.service.price.cents', 1200));
});

it('opens the wizard with no preselection when no style was picked', function () {
    $this->get('/book')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('preselectedStyle', null));
});

it('ignores a style that does not exist rather than erroring', function () {
    $this->get('/book?style=nope')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('preselectedStyle', null));
});

it('books a style through and records it against the appointment', function () {
    $style = Style::factory()->create([
        'code' => '02',
        'service_id' => $this->service->id,
    ]);

    $this->post('/book', [
        'type' => 'scheduled',
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->service->ulid],
        'staff' => $this->barber->ulid,
        'start' => $this->slot->toIso8601String(),
        'style' => $style->ulid,
    ])->assertRedirect();

    expect(Appointment::firstOrFail()->style_id)->toBe($style->id);
});
