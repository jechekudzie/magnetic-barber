<?php

use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClientAccountService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create(['slug' => 'harare-avenues', 'code' => 'MB']);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    RateLimiter::clear('0782000002|127.0.0.1');
});

/** A barber as the shop actually creates them: a number, no email. */
function phoneOnlyBarber(): User
{
    $user = User::factory()->create([
        'name' => 'Blessing Ncube',
        'email' => null,
        'phone' => '+263782000002',
        'password' => Hash::make('correct-horse'),
    ]);

    StaffProfile::factory()->for($user)->create(['is_bookable' => true]);
    test()->branch->staff()->attach($user, ['is_primary' => true]);

    setPermissionsTeamId(test()->branch->id);
    $user->assignRole('barber');
    setPermissionsTeamId(null);

    return $user;
}

it('signs in a barber who has no email at all', function () {
    $barber = phoneOnlyBarber();

    $this->post('/login', [
        'email' => '0782000002',
        'password' => 'correct-horse',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($barber);
});

it('finds them however they type the number', function () {
    $barber = phoneOnlyBarber();

    foreach (['0782000002', '+263782000002', '263782000002'] as $typed) {
        auth()->logout();
        RateLimiter::clear(strtolower($typed).'|127.0.0.1');

        $this->post('/login', ['email' => $typed, 'password' => 'correct-horse'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($barber);
    }
});

it('still signs in with an email', function () {
    $owner = User::factory()->create([
        'email' => 'owner@magneticbarber.co.zw',
        'password' => Hash::make('correct-horse'),
    ]);
    StaffProfile::factory()->for($owner)->create();
    $this->branch->staff()->attach($owner, ['is_primary' => true]);

    $this->post('/login', [
        'email' => 'owner@magneticbarber.co.zw',
        'password' => 'correct-horse',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($owner);
});

it('signs in a client the booking form created, once they have a password', function () {
    $client = app(ClientAccountService::class)
        ->register('Tendai Moyo', '0781879820', $this->branch);

    $client->forceFill(['password' => Hash::make('cut-and-fade-2026')])->save();

    $this->post('/login', [
        'email' => '0781879820',
        'password' => 'cut-and-fade-2026',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($client->fresh());
});

it('refuses a client who has never set a password', function () {
    // Exactly what a guest booking leaves behind.
    app(ClientAccountService::class)->register('Tendai Moyo', '0781879820', $this->branch);

    $this->post('/login', ['email' => '0781879820', 'password' => ''])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('refuses a wrong password', function () {
    phoneOnlyBarber();

    $this->post('/login', ['email' => '0782000002', 'password' => 'nope'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('refuses a deactivated account even with the right password', function () {
    $barber = phoneOnlyBarber();
    $barber->forceFill(['is_active' => false])->save();

    $this->post('/login', ['email' => '0782000002', 'password' => 'correct-horse'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('offers a way in from the public site', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});

it('shows the site to somebody already signed in without losing them', function () {
    $barber = phoneOnlyBarber();

    $this->actingAs($barber)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.name', 'Blessing Ncube'));
});
