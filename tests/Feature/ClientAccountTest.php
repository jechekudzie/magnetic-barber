<?php

use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * A client's own page on the website.
 *
 * Before this existed, signing in on the site put a client on the staff
 * dashboard: an admin shell they had no permission for, with a sidebar of
 * links that all refused them.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'slug' => 'harare-avenues', 'code' => 'MB', 'timezone' => 'Africa/Harare',
        'opens_at' => '08:00', 'closes_at' => '19:00', 'days_open' => [0, 1, 2, 3, 4, 5, 6],
        'is_active' => true,
    ]);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->barber = User::factory()->create(['name' => 'Tapiwa Nyoni']);
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($this->barber, ['is_primary' => true]);

    $this->cut = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1500, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);
});

function clientBooking(string $phone = '0781879820')
{
    // One barber cannot take two clients at ten o'clock, so each call takes
    // its own hour.
    static $hour = 0;

    return app(BookingService::class)->book(test()->branch, [
        'name' => 'Tendai Moyo',
        'phone' => $phone,
        'service_ids' => [test()->cut->id],
        'staff_id' => test()->barber->id,
        'start' => CarbonImmutable::now(test()->branch->timezone)
            ->addDays(2)->setTime(10 + $hour++, 0)->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);
}

/* ------------------------------------------------- the wrong door */

it('sends a client away from the staff dashboard', function () {
    $appointment = clientBooking();

    $this->actingAs($appointment->client)
        ->get('/dashboard')
        ->assertRedirect('/account');
});

it('sends a client away from the admin screens too', function (string $path) {
    $appointment = clientBooking();

    $this->actingAs($appointment->client)
        ->get($path)
        ->assertRedirect('/account');
})->with(['/admin/bookings', '/admin/till', '/admin/earnings', '/admin/desk']);

it('still lets staff through to the dashboard', function () {
    setPermissionsTeamId($this->branch->id);
    $this->barber->assignRole('barber');
    setPermissionsTeamId(null);

    $this->actingAs($this->barber)->get('/dashboard')->assertOk();
});

/* ------------------------------------------------- the right door */

it('shows a client their own bookings and points', function () {
    $appointment = clientBooking();

    $this->actingAs($appointment->client)
        ->get('/account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('site/account')
            ->where('client.name', 'Tendai Moyo')
            ->has('upcoming', 1)
            ->where('upcoming.0.reference', $appointment->reference)
            ->where('upcoming.0.barber', fn ($name) => $name !== null)
            ->has('loyalty.points')
        );
});

it('shows a client nothing of anybody else', function () {
    $mine = clientBooking('0781879820');
    clientBooking('0772000111');

    $this->actingAs($mine->client)
        ->get('/account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('upcoming', 1)
            ->where('upcoming.0.reference', $mine->reference)
        );
});

it('lets a client call off their own booking', function () {
    $appointment = clientBooking();

    $this->actingAs($appointment->client)
        ->put("/account/bookings/{$appointment->ulid}/cancel")
        ->assertRedirect();

    expect($appointment->refresh()->status->value)->toBe('cancelled');
});

it('will not let a client cancel somebody else’s booking', function () {
    $mine = clientBooking('0781879820');
    $theirs = clientBooking('0772000111');

    $this->actingAs($mine->client)
        ->put("/account/bookings/{$theirs->ulid}/cancel")
        ->assertNotFound();

    expect($theirs->refresh()->status->value)->not->toBe('cancelled');
});

it('refuses the account page to a stranger', function () {
    $this->get('/account')->assertRedirect('/login');
});
