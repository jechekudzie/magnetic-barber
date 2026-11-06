<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\ClientAccountService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->branch = Branch::factory()->create([
        'slug' => 'harare-avenues', 'code' => 'MB', 'timezone' => 'Africa/Harare',
        'opens_at' => '08:00', 'closes_at' => '19:00', 'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->barber = User::factory()->create(['name' => 'Tapiwa Nyoni']);
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true, 'display_name' => 'Tapiwa Nyoni']);
    $this->branch->staff()->attach($this->barber, ['is_primary' => true]);
    setPermissionsTeamId($this->branch->id);
    $this->barber->assignRole('barber');

    $this->owner = User::factory()->create(['name' => 'The Owner']);
    StaffProfile::factory()->for($this->owner)->create(['is_bookable' => false]);
    $this->branch->staff()->attach($this->owner, ['is_primary' => true]);
    $this->owner->assignRole('owner');
    setPermissionsTeamId(null);

    $this->cut = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1000, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);

    $this->client = app(ClientAccountService::class)
        ->register('Tendai Moyo', '0781879820', $this->branch);

    $this->slot = CarbonImmutable::now($this->branch->timezone)
        ->addWeek()->next(CarbonImmutable::TUESDAY)->setTime(10, 0);

    $this->appointment = app(BookingService::class)->book($this->branch, [
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->cut->id],
        'staff_id' => $this->barber->id,
        'start' => $this->slot->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);
});

function branchHeaders(): array
{
    return ['X-Branch-Id' => (string) test()->branch->id];
}

/* --------------------------------------------- a client paying themselves */

it('secures the chair the moment proof is sent, before anyone checks it', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'ecocash',
        'amount_cents' => 1000,
        'reference' => 'MP260101.1234.A56789',
        'proof' => UploadedFile::fake()->image('ecocash.jpg'),
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    $appointment = $this->appointment->fresh();

    expect($appointment->isSecured())->toBeTrue()
        // Secured is not paid. The shop has a screenshot, not settlement.
        ->and($appointment->isPaidInFull())->toBeFalse()
        ->and($appointment->amount_paid_cents)->toBe(0);
});

it('keeps the screenshot and hands back a link to it', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'innbucks',
        'amount_cents' => 1000,
        'reference' => 'INN-99881',
        'proof' => UploadedFile::fake()->image('innbucks.png'),
    ])->assertCreated()->assertJsonPath('data.proof_url', fn ($url) => is_string($url));

    expect(Storage::disk('public')->files('payment-proofs'))->toHaveCount(1);
});

it('insists on a transaction reference', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'ecocash',
        'amount_cents' => 1000,
    ])->assertStatus(422)->assertJsonValidationErrors('reference');
});

it('will not let a client pay with cash from their phone', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'cash',
        'amount_cents' => 1000,
        'reference' => 'n/a',
    ])->assertStatus(422)->assertJsonValidationErrors('method');
});

it('hides somebody else\'s booking from payment', function () {
    $other = app(ClientAccountService::class)->register('Rudo', '0782000099', $this->branch);
    Sanctum::actingAs($other, ['client']);

    $this->getJson("/api/v1/bookings/{$this->appointment->ulid}/payment")->assertStatus(404);
});

it('tells the client what is owed and how it can be sent', function () {
    Sanctum::actingAs($this->client, ['client']);

    $response = $this->getJson("/api/v1/bookings/{$this->appointment->ulid}/payment");

    $response->assertOk()->assertJsonPath('due.cents', 1000);

    expect(collect($response->json('methods'))->pluck('value')->all())
        ->toBe(['ecocash', 'innbucks', 'bank_transfer']);
});

/* ----------------------------------------------------------- the desk */

it('confirms cash on the spot, because the desk is holding it', function () {
    Sanctum::actingAs($this->owner, ['staff']);

    $this->withHeaders(branchHeaders())
        ->postJson("/api/v1/staff/appointments/{$this->appointment->ulid}/payments", [
            'method' => 'cash',
            'amount_cents' => 1000,
        ])->assertCreated()->assertJsonPath('data.status', 'confirmed');

    expect($this->appointment->fresh()->isPaidInFull())->toBeTrue();
});

it('still holds EcoCash taken at the desk for a check', function () {
    Sanctum::actingAs($this->owner, ['staff']);

    $this->withHeaders(branchHeaders())
        ->postJson("/api/v1/staff/appointments/{$this->appointment->ulid}/payments", [
            'method' => 'ecocash',
            'amount_cents' => 1000,
            'reference' => 'MP260101.1234.A56789',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
});

it('refuses mobile money with no reference', function () {
    Sanctum::actingAs($this->owner, ['staff']);

    $this->withHeaders(branchHeaders())
        ->postJson("/api/v1/staff/appointments/{$this->appointment->ulid}/payments", [
            'method' => 'ecocash',
            'amount_cents' => 1000,
        ])->assertStatus(422);
});

it('counts the money once the desk confirms it', function () {
    Sanctum::actingAs($this->client, ['client']);
    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'ecocash', 'amount_cents' => 1000, 'reference' => 'MP-1',
    ])->assertCreated();

    $payment = Payment::sole();

    Sanctum::actingAs($this->owner, ['staff']);
    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/payments/{$payment->ulid}/confirm")
        ->assertOk()->assertJsonPath('data.status', 'confirmed');

    expect($this->appointment->fresh()->isPaidInFull())->toBeTrue();
});

it('keeps a rejected payment on the record and unsecures the chair', function () {
    Sanctum::actingAs($this->client, ['client']);
    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'ecocash', 'amount_cents' => 1000, 'reference' => 'MP-FAKE',
    ])->assertCreated();

    $payment = Payment::sole();

    Sanctum::actingAs($this->owner, ['staff']);
    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/payments/{$payment->ulid}/reject", ['reason' => 'No such transaction'])
        ->assertOk();

    $appointment = $this->appointment->fresh();

    expect(Payment::count())->toBe(1)
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Rejected)
        ->and($appointment->isSecured())->toBeFalse();
});

it('stops a barber voiding money', function () {
    Sanctum::actingAs($this->client, ['client']);
    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'ecocash', 'amount_cents' => 1000, 'reference' => 'MP-1',
    ])->assertCreated();

    Sanctum::actingAs($this->barber, ['staff']);

    $this->withHeaders(branchHeaders())
        ->putJson('/api/v1/staff/payments/'.Payment::sole()->ulid.'/reject', ['reason' => 'nope'])
        ->assertStatus(403);
});

it('lists what the desk still has to check', function () {
    Sanctum::actingAs($this->client, ['client']);
    $this->postJson("/api/v1/bookings/{$this->appointment->ulid}/payment", [
        'method' => 'ecocash', 'amount_cents' => 1000, 'reference' => 'MP-1',
    ])->assertCreated();

    Sanctum::actingAs($this->owner, ['staff']);

    $response = $this->withHeaders(branchHeaders())->getJson('/api/v1/staff/payments/awaiting');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('adds part payments up rather than replacing them', function () {
    Sanctum::actingAs($this->owner, ['staff']);

    foreach ([400, 600] as $amount) {
        $this->withHeaders(branchHeaders())
            ->postJson("/api/v1/staff/appointments/{$this->appointment->ulid}/payments", [
                'method' => 'cash', 'amount_cents' => $amount,
            ])->assertCreated();
    }

    expect($this->appointment->fresh()->amount_paid_cents)->toBe(1000);
});

/* ------------------------------------------------- the barber's own clock */

it('starts and finishes a cut, and keeps what it took', function () {
    $start = CarbonImmutable::now();
    $this->travelTo($start);

    Sanctum::actingAs($this->barber, ['staff']);

    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/appointments/{$this->appointment->ulid}/start")
        ->assertOk()->assertJsonPath('data.status', 'in_progress');

    $this->travelTo($start->addMinutes(38));

    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/appointments/{$this->appointment->ulid}/finish")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.minutes_so_far', 38);

    expect($this->appointment->fresh()->actual_minutes)->toBe(38);
});

it('will not finish a cut that never started', function () {
    Sanctum::actingAs($this->barber, ['staff']);

    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/appointments/{$this->appointment->ulid}/finish")
        ->assertStatus(422);
});

it('keeps the first start time when the button is tapped twice', function () {
    $start = CarbonImmutable::now();
    $this->travelTo($start);

    Sanctum::actingAs($this->barber, ['staff']);
    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/appointments/{$this->appointment->ulid}/start")->assertOk();

    $this->travelTo($start->addMinutes(10));
    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/appointments/{$this->appointment->ulid}/start")->assertOk();

    expect($this->appointment->fresh()->started_at->diffInMinutes($start))->toBeLessThan(1);
});

it('stops a barber starting a cut that is not theirs', function () {
    $other = User::factory()->create();
    StaffProfile::factory()->for($other)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($other, ['is_primary' => true]);
    setPermissionsTeamId($this->branch->id);
    $other->assignRole('barber');
    setPermissionsTeamId(null);

    Sanctum::actingAs($other, ['staff']);

    $this->withHeaders(branchHeaders())
        ->putJson("/api/v1/staff/appointments/{$this->appointment->ulid}/start")
        ->assertStatus(403);
});

/* --------------------------------- an overrunning cut occupies the chair */

it('keeps a barber busy while a cut runs past its slot', function () {
    // Mid cut, and already ten minutes past where the diary said it ends.
    $this->travelTo($this->slot->addMinutes(55)->utc());

    $this->appointment->update([
        'status' => AppointmentStatus::InProgress,
        'started_at' => $this->slot->utc(),
    ]);

    $grid = app(AvailabilityService::class)->forDate(
        $this->branch,
        $this->slot,
        [$this->cut->id],
        $this->barber->id,
    );

    $labels = collect($grid['staff'])->flatMap(fn ($row) => $row['slots'])->pluck('start');

    // Nothing inside the overrun window is offered.
    expect($labels->filter(fn (string $iso): bool => CarbonImmutable::parse($iso)
        ->isBetween($this->slot->utc(), $this->slot->addMinutes(65)->utc()))->all())
        ->toBeEmpty();
});

it('leaves a cut that finished on time out of the way', function () {
    $this->travelTo($this->slot->addMinutes(55)->utc());

    $this->appointment->update([
        'status' => AppointmentStatus::Completed,
        'started_at' => $this->slot->utc(),
        'completed_at' => $this->slot->addMinutes(40)->utc(),
        'actual_minutes' => 40,
    ]);

    $grid = app(AvailabilityService::class)->forDate(
        $this->branch,
        $this->slot,
        [$this->cut->id],
        $this->barber->id,
    );

    // A finished visit holds nothing: the chair is free again.
    expect(collect($grid['staff'])->flatMap(fn ($row) => $row['slots']))->not->toBeEmpty();
});
