<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

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
        'price_cents' => 1500, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);

    // Today, so the "owing today" panel can see it.
    $this->appointment = app(BookingService::class)->book($this->branch, [
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->cut->id],
        'staff_id' => $this->barber->id,
        'start' => CarbonImmutable::now($this->branch->timezone)->addHours(2)->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);
});

function deskUser(string $role): User
{
    $user = User::factory()->create();
    StaffProfile::factory()->for($user)->create(['is_bookable' => false]);
    test()->branch->staff()->attach($user, ['is_primary' => true]);

    setPermissionsTeamId(test()->branch->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

/** A client's own claim from the app: pending, holding the chair. */
function claimFromApp(int $cents = 1500): Payment
{
    return app(PaymentService::class)->record(
        test()->appointment,
        [
            'method' => PaymentMethod::EcoCash,
            'amount' => Money::ofCents($cents),
            'reference' => 'MP260101.1234.A56789',
        ],
        recordedBy: null,
    );
}

it('shows the desk what is owed and what is waiting to be checked', function () {
    claimFromApp();

    $this->actingAs(deskUser('receptionist'))
        ->get('/admin/till')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/till')
            ->has('awaiting', 1)
            ->has('owing', 1)
            ->where('awaiting.0.reference', 'MP260101.1234.A56789')
            // Recorded by nobody at the shop, which is why it needs checking.
            ->where('awaiting.0.self_recorded', true));
});

it('takes cash and settles the booking there and then', function () {
    $this->actingAs(deskUser('receptionist'))
        ->post("/admin/till/{$this->appointment->ulid}", [
            'method' => 'cash',
            'amount' => '15.00',
        ])
        ->assertRedirect();

    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($this->appointment->fresh()->isPaidInFull())->toBeTrue();
});

it('will not take mobile money without its reference', function () {
    $this->actingAs(deskUser('receptionist'))
        ->post("/admin/till/{$this->appointment->ulid}", [
            'method' => 'ecocash',
            'amount' => '15.00',
        ])
        ->assertSessionHasErrors('reference');

    expect(Payment::count())->toBe(0);
});

it('confirms a claim, which is when the money starts counting', function () {
    $payment = claimFromApp();

    expect($this->appointment->fresh()->isPaidInFull())->toBeFalse();

    $this->actingAs(deskUser('receptionist'))
        ->put("/admin/till/{$payment->ulid}/confirm")
        ->assertRedirect();

    expect($this->appointment->fresh()->isPaidInFull())->toBeTrue();
});

it('lets a manager say the money never arrived, and frees the chair', function () {
    $payment = claimFromApp();

    expect($this->appointment->fresh()->isSecured())->toBeTrue();

    $this->actingAs(deskUser('branch-manager'))
        ->put("/admin/till/{$payment->ulid}/reject", ['reason' => 'No such transaction'])
        ->assertRedirect();

    $appointment = $this->appointment->fresh();

    expect($appointment->isSecured())->toBeFalse()
        // The row stays: a client who insists they paid is who it is for.
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Rejected)
        ->and(Payment::sole()->rejection_reason)->toBe('No such transaction');
});

it('stops a receptionist voiding money', function () {
    $payment = claimFromApp();

    $this->actingAs(deskUser('receptionist'))
        ->put("/admin/till/{$payment->ulid}/reject", ['reason' => 'nope'])
        ->assertForbidden();
});

it('keeps a barber out of the till entirely', function () {
    $this->actingAs(deskUser('barber'))->get('/admin/till')->assertForbidden();
});

it('refuses to check the same payment twice', function () {
    $payment = claimFromApp();
    $desk = deskUser('branch-manager');

    $this->actingAs($desk)->put("/admin/till/{$payment->ulid}/confirm")->assertRedirect();

    $this->actingAs($desk)
        ->put("/admin/till/{$payment->ulid}/confirm")
        ->assertStatus(422);
});

it('hides a payment belonging to another branch', function () {
    $payment = claimFromApp();

    $elsewhere = Branch::factory()->create(['slug' => 'borrowdale', 'code' => 'BD']);
    $payment->update(['branch_id' => $elsewhere->id]);

    $this->actingAs(deskUser('receptionist'))
        ->put("/admin/till/{$payment->ulid}/confirm")
        ->assertNotFound();
});

it('adds up what came in today, by method', function () {
    $desk = deskUser('receptionist');

    $this->actingAs($desk)->post("/admin/till/{$this->appointment->ulid}", [
        'method' => 'cash', 'amount' => '10.00',
    ]);
    $this->actingAs($desk)->post("/admin/till/{$this->appointment->ulid}", [
        'method' => 'card', 'amount' => '5.00',
    ]);

    $this->actingAs($desk)
        ->get('/admin/till')
        ->assertInertia(fn ($page) => $page
            ->where('takings.total.cents', 1500)
            ->where('takings.count', 2)
            ->has('takings.by_method', 2));
});

it('badges the unchecked count for whoever can work the till', function () {
    claimFromApp();

    $this->actingAs(deskUser('receptionist'))
        ->get('/admin/till')
        ->assertInertia(fn ($page) => $page->where('counts.till', 1));
});

it('badges nothing for somebody who cannot take money', function () {
    claimFromApp();

    // Pricing, because a barber may read prices but not the branch diary.
    $this->actingAs(deskUser('barber'))
        ->get('/admin/pricing')
        ->assertInertia(fn ($page) => $page->where('counts.till', 0));
});
