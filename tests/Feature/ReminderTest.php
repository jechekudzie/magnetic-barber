<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\ReminderSchedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReminderService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create();

    $this->owner = User::factory()->create();
    $this->owner->branches()->attach($this->branch, ['is_primary' => true]);

    setPermissionsTeamId($this->branch->id);
    $this->owner->assignRole('owner');
    setPermissionsTeamId(null);

    $this->reminders = app(ReminderService::class);
});

function lapsedClient(int $daysAgo, array $profile = []): User
{
    $client = User::factory()->client()->create();

    ClientProfile::factory()->for($client)->create(array_merge([
        'home_branch_id' => test()->branch->id,
        'visit_count' => 3,
        'last_visit_at' => now()->subDays($daysAgo),
    ], $profile));

    return $client;
}

/* ------------------------------------------------------------ the rule */

it('uses three weeks unless the shop says otherwise', function () {
    expect($this->reminders->thresholdDays())->toBe(21);

    Setting::put(ReminderService::SETTING_KEY, 14);

    expect($this->reminders->thresholdDays())->toBe(14);
});

it('flags a client past the threshold and leaves one inside it alone', function () {
    lapsedClient(25);
    lapsedClient(5);

    $board = $this->reminders->board($this->branch);

    expect($board['due'])->toHaveCount(1)
        ->and($board['due'][0]['days_over'])->toBe(4);
});

it('separates who is nearly due from who is late', function () {
    lapsedClient(25);   // four days over
    lapsedClient(18);   // three days off, inside the five day warning
    lapsedClient(2);    // nowhere near

    $board = $this->reminders->board($this->branch);

    expect($board['due'])->toHaveCount(1)
        ->and($board['soon'])->toHaveCount(1)
        ->and($board['soon'][0]['days_until'])->toBe(3);
});

it('honours what a client asked for over the shop rule', function () {
    // Weekly client, ten days out: late, even though the shop says 21.
    lapsedClient(10, ['preferred_cycle_days' => 7]);

    $board = $this->reminders->board($this->branch);

    expect($board['due'])->toHaveCount(1)
        ->and($board['due'][0]['threshold'])->toBe(7)
        ->and($board['due'][0]['days_over'])->toBe(3);
});

it('leaves a client alone once they have booked again', function () {
    $client = lapsedClient(30);

    expect($this->reminders->board($this->branch)['due'])->toHaveCount(1);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'client_id' => $client->id,
        'status' => AppointmentStatus::Confirmed,
        'scheduled_start_at' => now()->addDays(2),
        'scheduled_end_at' => now()->addDays(2)->addMinutes(45),
    ]);

    expect($this->reminders->board($this->branch)['due'])->toBeEmpty();
});

it('never chases a client who has opted out', function () {
    lapsedClient(40, ['reminders_enabled' => false]);

    expect($this->reminders->board($this->branch)['due'])->toBeEmpty();
});

it('ignores somebody who has never been in', function () {
    $client = User::factory()->client()->create();
    ClientProfile::factory()->for($client)->create([
        'home_branch_id' => $this->branch->id,
        'visit_count' => 0,
        'last_visit_at' => null,
    ]);

    expect($this->reminders->board($this->branch)['due'])->toBeEmpty();
});

/* --------------------------------------------------------- the queue */

it('queues one reminder per lapsed client and never a second', function () {
    lapsedClient(30);
    lapsedClient(40);

    expect($this->reminders->schedule($this->branch))->toBe(2)
        // Running again must not double up on anyone.
        ->and($this->reminders->schedule($this->branch))->toBe(0)
        ->and(ReminderSchedule::count())->toBe(2);
});

it('drops the queued reminder when the client books', function () {
    $client = lapsedClient(30);
    $this->reminders->schedule($this->branch);

    expect(ReminderSchedule::query()->pending()->count())->toBe(1);

    $this->reminders->cancelFor($client->id);

    expect(ReminderSchedule::query()->pending()->count())->toBe(0);
});

it('works out a client rhythm from the middle gap, not the average', function () {
    $client = lapsedClient(5);

    // Fortnightly, then one long holiday gap. The median ignores the outlier.
    foreach ([0, 14, 28, 90] as $daysAgo) {
        Appointment::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $client->id,
            'status' => AppointmentStatus::Completed,
            'scheduled_start_at' => now()->subDays($daysAgo),
            'scheduled_end_at' => now()->subDays($daysAgo)->addMinutes(45),
        ]);
    }

    expect($this->reminders->recomputeCycle($client))->toBe(14);
});

/* ---------------------------------------------------------- the screen */

it('shows the board and saves the shop rule', function () {
    lapsedClient(30);

    $this->actingAs($this->owner)
        ->get('/admin/reminders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reminders')
            ->where('settings.threshold', 21)
            ->has('due', 1));

    $this->actingAs($this->owner)
        ->put('/admin/reminders/settings', ['threshold' => 14, 'warn' => 3])
        ->assertRedirect();

    expect($this->reminders->thresholdDays())->toBe(14);
});

it('saves how often one client wants a cut', function () {
    $client = lapsedClient(30);

    $this->actingAs($this->owner)
        ->put('/admin/reminders/client', [
            'client' => $client->ulid,
            'preferred_cycle_days' => 7,
            'reminders_enabled' => true,
        ])
        ->assertRedirect();

    expect($client->clientProfile->fresh()->preferred_cycle_days)->toBe(7);
});

it('refuses a threshold of zero days, which would chase everybody always', function () {
    $this->actingAs($this->owner)
        ->put('/admin/reminders/settings', ['threshold' => 0, 'warn' => 3])
        ->assertSessionHasErrors('threshold');
});

it('queues reminders from the scheduled command', function () {
    lapsedClient(30);

    $this->artisan('reminders:dispatch')
        ->expectsOutputToContain('Queued 1 new reminder')
        ->assertSuccessful();

    expect(ReminderSchedule::query()->pending()->count())->toBe(1);
});
