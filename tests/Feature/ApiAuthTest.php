<?php

use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\ClientAccountService;
use App\Support\Phone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create(['slug' => 'harare-avenues', 'code' => 'MB']);
    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    // The throttles are real middleware; a shared limiter would make the
    // order tests run in matter.
    RateLimiter::clear('staff-login:barber@magnetic.test|127.0.0.1');
});

/* ---------------------------------------------------------------- clients */

it('sends a client a login code and says whether we have met them', function () {
    $response = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820']);

    $response->assertOk()
        ->assertJsonPath('is_registered', false)
        ->assertJsonPath('expires_in_minutes', 10);

    expect(VerificationCode::where('phone', '+263781879820')->usable()->count())->toBe(1);
});

it('hashes the code rather than storing it', function () {
    $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820']);

    $record = VerificationCode::sole();
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');

    expect($record->code_hash)->not->toBe($code)
        ->and(strlen($record->code_hash))->toBeGreaterThan(20);
});

it('registers a new client on their first verified code', function () {
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');

    $response = $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820',
        'code' => $code,
        'name' => 'Tendai Moyo',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.name', 'Tendai Moyo')
        ->assertJsonPath('user.is_staff', false)
        ->assertJsonStructure(['token', 'user' => ['id', 'client' => ['account_number', 'points']]]);

    expect(User::where('phone', '+263781879820')->sole()->phone_verified_at)->not->toBeNull();
});

it('asks for a name only when the number is new to us', function () {
    app(ClientAccountService::class)->register('Rudo Chikafu', '0781879820', $this->branch);

    $start = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820']);
    $start->assertJsonPath('is_registered', true);

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820',
        'code' => $start->json('code'),
    ])->assertOk()->assertJsonPath('user.name', 'Rudo Chikafu');
});

it('refuses to register without a name', function () {
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');

    $this->postJson('/api/v1/auth/client/verify', ['phone' => '0781879820', 'code' => $code])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rejects a wrong code and counts the attempt', function () {
    $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820']);

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => '000000', 'name' => 'Tendai',
    ])->assertStatus(422)->assertJsonValidationErrors('code');

    expect(VerificationCode::sole()->attempts)->toBe(1);
});

it('burns a code after five wrong guesses', function () {
    $start = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820']);

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/client/verify', [
            'phone' => '0781879820', 'code' => '000000', 'name' => 'Tendai',
        ])->assertStatus(422);
    }

    // Even the right code is now dead.
    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $start->json('code'), 'name' => 'Tendai',
    ])->assertStatus(422);
});

it('spends a code, so it cannot be replayed', function () {
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $code, 'name' => 'Tendai Moyo',
    ])->assertOk();

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $code, 'name' => 'Tendai Moyo',
    ])->assertStatus(422);
});

it('kills the previous code when a client asks for another', function () {
    $first = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');
    $second = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $first, 'name' => 'Tendai',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $second, 'name' => 'Tendai',
    ])->assertOk();
});

it('rejects an expired code', function () {
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');

    VerificationCode::sole()->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $code, 'name' => 'Tendai',
    ])->assertStatus(422);
});

it('refuses a number that is not a Zimbabwean mobile', function () {
    $this->postJson('/api/v1/auth/client/start', ['phone' => '12345'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

/* ------------------------------------------- clients with a password */

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'password' => 'cut-and-fade-2026',
    ], $overrides);
}

it('registers a client with a phone and a password', function () {
    $response = $this->postJson('/api/v1/auth/client/register', registerPayload());

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Tendai Moyo')
        ->assertJsonPath('user.is_staff', false)
        ->assertJsonStructure(['token', 'user' => ['client' => ['account_number']]]);

    expect(User::where('phone', '+263781879820')->sole()->password)->not->toBeNull();
});

it('takes an email as well, and lets them sign in with either', function () {
    $this->postJson('/api/v1/auth/client/register', registerPayload([
        'email' => 'tendai@example.test',
    ]))->assertCreated();

    $this->postJson('/api/v1/auth/client/login', [
        'identifier' => 'tendai@example.test',
        'password' => 'cut-and-fade-2026',
    ])->assertOk()->assertJsonPath('user.name', 'Tendai Moyo');

    $this->postJson('/api/v1/auth/client/login', [
        'identifier' => '0781879820',
        'password' => 'cut-and-fade-2026',
    ])->assertOk();
});

it('finds a client however they type their number', function () {
    $this->postJson('/api/v1/auth/client/register', registerPayload())->assertCreated();

    foreach (['0781879820', '+263781879820', '263781879820'] as $typed) {
        $this->postJson('/api/v1/auth/client/login', [
            'identifier' => $typed,
            'password' => 'cut-and-fade-2026',
        ])->assertOk();
    }
});

it('puts a password on the account a booking already created', function () {
    // Exactly what a guest booking leaves behind: a client, no password.
    $existing = app(ClientAccountService::class)
        ->register('Tendai Moyo', '0781879820', $this->branch);
    $existing->clientProfile()->update(['visit_count' => 4]);

    $this->postJson('/api/v1/auth/client/register', registerPayload([
        'name' => 'Tendai M',
    ]))->assertCreated()
        // Their history follows them in rather than starting again.
        ->assertJsonPath('user.client.visit_count', 4);

    expect(User::where('phone', '+263781879820')->count())->toBe(1);
});

it('refuses to register over an account that already has a password', function () {
    $this->postJson('/api/v1/auth/client/register', registerPayload())->assertCreated();

    $this->postJson('/api/v1/auth/client/register', registerPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('refuses an email already used by somebody else', function () {
    $this->postJson('/api/v1/auth/client/register', registerPayload([
        'email' => 'taken@example.test',
    ]))->assertCreated();

    $this->postJson('/api/v1/auth/client/register', registerPayload([
        'phone' => '0782000099',
        'email' => 'taken@example.test',
    ]))->assertStatus(422)->assertJsonValidationErrors('email');
});

it('refuses a password shorter than eight characters', function () {
    $this->postJson('/api/v1/auth/client/register', registerPayload(['password' => 'short1']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('gives the same answer for a wrong client password and an unknown number', function () {
    $this->postJson('/api/v1/auth/client/register', registerPayload())->assertCreated();

    $wrong = $this->postJson('/api/v1/auth/client/login', [
        'identifier' => '0781879820', 'password' => 'nope',
    ]);
    $unknown = $this->postJson('/api/v1/auth/client/login', [
        'identifier' => '0782000099', 'password' => 'nope',
    ]);

    $wrong->assertStatus(422);
    $unknown->assertStatus(422);
    expect($wrong->json('errors'))->toBe($unknown->json('errors'));
});

it('refuses a client who has never set a password', function () {
    // A guest booking leaves exactly this: an account with no password.
    app(ClientAccountService::class)->register('Tendai Moyo', '0781879820', $this->branch);

    $this->postJson('/api/v1/auth/client/login', [
        'identifier' => '0781879820', 'password' => '',
    ])->assertStatus(422);
});

it('keeps staff out of the client door', function () {
    staffMember();

    $this->postJson('/api/v1/auth/client/login', [
        'identifier' => 'barber@magnetic.test',
        'password' => 'correct-horse',
    ])->assertStatus(422);
});

it('hands a client token that cannot reach the staff surface', function () {
    $token = $this->postJson('/api/v1/auth/client/register', registerPayload())->json('token');

    $this->withToken($token)->getJson('/api/v1/staff/diary')->assertStatus(403);
});

/* ------------------------------------------------------------------ staff */

function staffMember(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'email' => 'barber@magnetic.test',
        'phone' => '+263782000001',
        'password' => Hash::make('correct-horse'),
        'is_active' => true,
    ], $attributes));

    StaffProfile::factory()->for($user)->create(['is_bookable' => true]);
    test()->branch->staff()->attach($user, ['is_primary' => true]);

    setPermissionsTeamId(test()->branch->id);
    $user->assignRole('barber');
    setPermissionsTeamId(null);

    return $user;
}

it('signs a staff member in with an email and password', function () {
    staffMember();

    $response = $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'barber@magnetic.test',
        'password' => 'correct-horse',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.is_staff', true)
        ->assertJsonPath('user.roles', ['barber'])
        ->assertJsonStructure(['token', 'user' => ['branches', 'permissions']]);
});

it('signs a staff member in with a phone number, however they type it', function () {
    staffMember();

    $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => '0782000001',
        'password' => 'correct-horse',
    ])->assertOk();
});

it('gives the same answer for a wrong password and an unknown person', function () {
    staffMember();

    $wrongPassword = $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'barber@magnetic.test', 'password' => 'nope',
    ]);

    $unknown = $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'nobody@magnetic.test', 'password' => 'nope',
    ]);

    $wrongPassword->assertStatus(422);
    $unknown->assertStatus(422);
    expect($wrongPassword->json('errors'))->toBe($unknown->json('errors'));
});

it('keeps a client out of the staff door even with a password', function () {
    $client = User::factory()->create([
        'email' => 'client@magnetic.test',
        'password' => Hash::make('correct-horse'),
    ]);
    app(ClientAccountService::class)->register($client->name, '0781879820', $this->branch);

    $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'client@magnetic.test', 'password' => 'correct-horse',
    ])->assertStatus(422);
});

it('refuses a deactivated staff member', function () {
    staffMember(['is_active' => false]);

    $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'barber@magnetic.test', 'password' => 'correct-horse',
    ])->assertStatus(403);
});

it('locks the staff door after five wrong passwords', function () {
    staffMember();

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'barber@magnetic.test', 'password' => 'nope',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'barber@magnetic.test', 'password' => 'correct-horse',
    ])->assertStatus(422)->assertJsonPath('errors.identifier.0', fn ($m) => str_contains($m, 'Too many attempts'));
});

/* --------------------------------------------------------------------- me */

it('reports a client to themselves', function () {
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');
    $token = $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $code, 'name' => 'Tendai Moyo',
    ])->json('token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.is_staff', false)
        ->assertJsonPath('data.name', 'Tendai Moyo')
        ->assertJsonPath('data.roles', ['client']);
});

it('reports a staff member with the permissions they hold at that branch', function () {
    staffMember();

    $token = $this->postJson('/api/v1/auth/staff/login', [
        'identifier' => 'barber@magnetic.test', 'password' => 'correct-horse',
    ])->json('token');

    $response = $this->withToken($token)
        ->withHeader('X-Branch-Id', (string) $this->branch->id)
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.is_staff', true)
        ->assertJsonPath('data.staff.is_bookable', true);

    expect($response->json('data.permissions'))->toContain('appointment.view.own')
        // A barber may never read client phone numbers: that is how the shop
        // keeps its client list when a barber leaves.
        ->not->toContain('client.contact.view');
});

it('refuses an anonymous caller', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

it('signs out by destroying only the token that was used', function () {
    $code = $this->postJson('/api/v1/auth/client/start', ['phone' => '0781879820'])->json('code');
    $token = $this->postJson('/api/v1/auth/client/verify', [
        'phone' => '0781879820', 'code' => $code, 'name' => 'Tendai Moyo',
    ])->json('token');

    $this->withToken($token)->postJson('/api/v1/auth/client/logout')->assertOk();

    /*
     * One container serves every request in a test, so the guard still holds
     * the user it resolved before the token was revoked. Production gets a
     * fresh container per request; this is the test harness catching up.
     */
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});
