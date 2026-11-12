<?php

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/*
 * magnetic:staff is the only way a real staff account comes into being: the
 * seeders deliberately refuse to put a known password on a live system. That
 * makes this command the single thing standing between the shop and a
 * production admin nobody can sign into, so it is tested as the SSH one liner
 * it will actually be run as, with no terminal to prompt at.
 */

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create(['slug' => 'harare-avenues']);
});

it('creates an owner who can sign in', function () {
    $this->artisan('magnetic:staff', [
        '--name' => 'Nigel Jecheta',
        '--phone' => '0781879820',
        '--email' => 'owner@magneticbarber.co.zw',
        '--role' => 'owner',
        '--branch' => 'harare-avenues',
        '--password' => 'correct-horse-7-battery',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'owner@magneticbarber.co.zw')->sole();

    expect($user->phone)->toBe('+263781879820')
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('correct-horse-7-battery', $user->password))->toBeTrue()
        ->and($user->isStaff())->toBeTrue();

    setPermissionsTeamId($this->branch->id);
    expect($user->fresh()->hasRole('owner'))->toBeTrue();
    setPermissionsTeamId(null);
});

it('generates a password when none is given, and never stores it in the clear', function () {
    $this->artisan('magnetic:staff', [
        '--name' => 'Rudo Chikwanha',
        '--phone' => '0782000111',
        '--email' => 'manager@magneticbarber.co.zw',
        '--role' => 'branch-manager',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'manager@magneticbarber.co.zw')->sole();

    // Hashed, long, and not the thing anybody typed.
    expect($user->password)->toStartWith('$2y$')
        ->and(Hash::check('', $user->password))->toBeFalse();
});

it('refuses to run blind without a name and a number', function () {
    $this->artisan('magnetic:staff', ['--no-interaction' => true])
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses to make a browser admin without an email to sign in with', function () {
    // A receptionist works the desk in a browser, so there is no version of
    // this account that does not sign into the admin.
    $this->artisan('magnetic:staff', [
        '--name' => 'Farai Sibanda',
        '--phone' => '0782000222',
        '--role' => 'receptionist',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('gives a barber a password without demanding an email', function () {
    // Not every barber has a work email, and the app takes a phone number as
    // the identifier. A barber with no password cannot open the app at all.
    $this->artisan('magnetic:staff', [
        '--name' => 'Blessing Ncube',
        '--phone' => '0782000333',
        '--role' => 'barber',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('name', 'Blessing Ncube')->sole();

    expect($user->email)->toBeNull()
        ->and($user->password)->not->toBeNull()
        ->and($user->isStaff())->toBeTrue();
});

it('honours a password handed to a barber instead of dropping it', function () {
    // This silently did nothing: the password was only applied to the roles
    // that needed an email, so the command reported success and the barber
    // could not sign in.
    $this->artisan('magnetic:staff', [
        '--name' => 'Taps Mushore',
        '--phone' => '0782000777',
        '--role' => 'barber',
        '--branch' => 'harare-avenues',
        '--password' => 'clippers-and-a-7-guard',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('name', 'Taps Mushore')->sole();

    expect(Hash::check('clippers-and-a-7-guard', $user->password))->toBeTrue();
});

it('creates a barber who can actually sign into the staff app', function () {
    // The end to end point of the whole command: a barber the shop created on
    // the server can open the handset app with their number and nothing else.
    $this->artisan('magnetic:staff', [
        '--name' => 'Chipo Marufu',
        '--phone' => '0782000888',
        '--role' => 'barber',
        '--branch' => 'harare-avenues',
        '--password' => 'fade-season-2026',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $response = $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => '0782000888',
        'password' => 'fade-season-2026',
        'device_name' => 'Chipo iPhone',
    ]);

    $response->assertOk()->assertJsonStructure(['token']);

    // And the token carries the staff ability, not the client one.
    $token = PersonalAccessToken::findToken($response->json('token'));

    expect($token->abilities)->toContain('staff')
        ->and($token->abilities)->not->toContain('client');
});

it('keeps new staff off the public team page until someone writes them up', function () {
    $this->artisan('magnetic:staff', [
        '--name' => 'Nyasha Dube',
        '--phone' => '0782000444',
        '--role' => 'aesthetician',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $profile = StaffProfile::query()->where('display_name', 'Nyasha Dube')->sole();

    // A barber with no bio and no photo on the live site looks like a bug.
    expect($profile->show_on_site)->toBeFalse()
        ->and($profile->is_bookable)->toBeTrue();
});

it('does not make a bookable chair out of the receptionist', function () {
    $this->artisan('magnetic:staff', [
        '--name' => 'Desk Person',
        '--phone' => '0782000555',
        '--email' => 'desk@magneticbarber.co.zw',
        '--role' => 'receptionist',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(StaffProfile::query()->where('display_name', 'Desk Person')->sole()->is_bookable)
        ->toBeFalse();
});

it('lands a second role on the same person rather than a duplicate account', function () {
    // The same number twice is somebody being promoted, not a new hire. A
    // second row would split their commission and their diary.
    $borrowdale = Branch::factory()->create(['slug' => 'borrowdale', 'sort_order' => 5]);

    $this->artisan('magnetic:staff', [
        '--name' => 'Tapiwa Moyo',
        '--phone' => '0781111111',
        '--role' => 'barber',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $this->artisan('magnetic:staff', [
        '--name' => 'Tapiwa Moyo',
        '--phone' => '0781111111',
        '--email' => 'tapiwa@magneticbarber.co.zw',
        '--role' => 'branch-manager',
        '--branch' => 'borrowdale',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('phone', '+263781111111')->sole();

    expect(User::query()->count())->toBe(1)
        ->and($user->branches)->toHaveCount(2);

    setPermissionsTeamId($this->branch->id);
    expect($user->fresh()->hasRole('barber'))->toBeTrue();
    setPermissionsTeamId($borrowdale->id);
    expect($user->fresh()->hasRole('branch-manager'))->toBeTrue();
    setPermissionsTeamId(null);
});

it('rejects a branch slug that does not exist rather than guessing', function () {
    $this->artisan('magnetic:staff', [
        '--name' => 'Somebody',
        '--phone' => '0782000666',
        '--role' => 'barber',
        '--branch' => 'shop-we-never-opened',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('rejects something that is not a mobile number', function () {
    $this->artisan('magnetic:staff', [
        '--name' => 'Somebody',
        '--phone' => 'not-a-phone',
        '--role' => 'barber',
        '--branch' => 'harare-avenues',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
