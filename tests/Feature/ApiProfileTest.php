<?php

use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\User;
use App\Services\ClientAccountService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create(['slug' => 'harare-avenues', 'code' => 'MB']);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->client = app(ClientAccountService::class)
        ->register('Tendai Moyo', '0781879820', $this->branch);

    $this->client->forceFill(['password' => Hash::make('cut-and-fade-2026')])->save();
});

it('changes a password when the current one is right', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'cut-and-fade-2026',
        'password' => 'new-fade-every-week',
        'password_confirmation' => 'new-fade-every-week',
    ])->assertOk();

    expect(Hash::check('new-fade-every-week', $this->client->fresh()->password))->toBeTrue();
});

it('refuses a password change without the current one', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'wrong-one',
        'password' => 'new-fade-every-week',
        'password_confirmation' => 'new-fade-every-week',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    expect(Hash::check('cut-and-fade-2026', $this->client->fresh()->password))->toBeTrue();
});

it('refuses a change where the confirmation does not match', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'cut-and-fade-2026',
        'password' => 'new-fade-every-week',
        'password_confirmation' => 'something-else',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('signs every other device out, but not the one asking', function () {
    $keep = $this->client->createToken('this-phone', ['client'])->plainTextToken;
    $this->client->createToken('old-tablet', ['client']);
    $this->client->createToken('shop-counter', ['client']);

    expect(PersonalAccessToken::count())->toBe(3);

    $this->withToken($keep)->putJson('/api/v1/me/password', [
        'current_password' => 'cut-and-fade-2026',
        'password' => 'new-fade-every-week',
        'password_confirmation' => 'new-fade-every-week',
    ])->assertOk();

    expect(PersonalAccessToken::count())->toBe(1);

    // The device that made the change stays signed in.
    $this->app['auth']->forgetGuards();
    $this->withToken($keep)->getJson('/api/v1/me')->assertOk();
});

it('updates a name and an email', function () {
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me', [
        'name' => 'Tendai M Moyo',
        'email' => 'tendai@example.test',
    ])->assertOk()->assertJsonPath('data.name', 'Tendai M Moyo');

    expect($this->client->fresh()->email)->toBe('tendai@example.test');
});

it('refuses an email that belongs to somebody else', function () {
    User::factory()->create(['email' => 'taken@example.test']);
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me', ['email' => 'taken@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('refuses a number that belongs to somebody else', function () {
    User::factory()->create(['phone' => '+263782000099']);
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me', ['phone' => '0782000099'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('unverifies a number that changes, since it is the shop\'s identity key', function () {
    $this->client->forceFill(['phone_verified_at' => now()])->save();
    Sanctum::actingAs($this->client, ['client']);

    $this->putJson('/api/v1/me', ['phone' => '0782000099'])->assertOk();

    $fresh = $this->client->fresh();

    expect($fresh->phone)->toBe('+263782000099')
        ->and($fresh->phone_verified_at)->toBeNull();
});

it('leaves verification alone when the number is retyped unchanged', function () {
    $this->client->forceFill(['phone_verified_at' => now()])->save();
    Sanctum::actingAs($this->client, ['client']);

    // Same number, different shape.
    $this->putJson('/api/v1/me', ['phone' => '+263781879820'])->assertOk();

    expect($this->client->fresh()->phone_verified_at)->not->toBeNull();
});

it('turns an anonymous caller away from the profile', function () {
    $this->putJson('/api/v1/me', ['name' => 'Nobody'])->assertStatus(401);
    $this->putJson('/api/v1/me/password', [])->assertStatus(401);
});
