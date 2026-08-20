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
use Illuminate\Support\Facades\Cache;

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
    lapsedClient(18);   // three days off
    lapsedClient(2);    // nineteen days off, still inside the horizon

    $board = $this->reminders->board($this->branch);

    // Soon reaches out a full horizon so the screen can filter to "this week"
    // or "next 3 weeks" without asking the database again. Nearest first.
    expect($board['due'])->toHaveCount(1)
        ->and($board['soon'])->toHaveCount(2)
        ->and($board['soon'][0]['days_until'])->toBe(3)
        ->and($board['soon'][1]['days_until'])->toBe(19);
});

it('leaves out anyone further off than the horizon', function () {
    lapsedClient(0);    // in today, due in 21 days

    expect($this->reminders->board($this->branch)['soon'])->toHaveCount(1);

    // 21 days out is inside the 28 day horizon; 40 would not be.
    $this->reminders;
    Setting::put(ReminderService::SETTING_KEY, 60);

    expect($this->reminders->board($this->branch)['soon'])->toHaveCount(0);
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
        ->put('/admin/reminders/settings', [
            'threshold' => 14,
            'warn' => 3,
            'message' => 'Hi {name}, {days} days is too long. Shall we book you in?',
        ])
        ->assertRedirect();

    expect($this->reminders->thresholdDays())->toBe(14)
        ->and($this->reminders->messageTemplate())->toContain('too long');
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

/* -------------------------------------------------- reaching the client */

it('gives reception the number and a message already written', function () {
    lapsedClient(30, ['user_id' => User::factory()->client()->create([
        'name' => 'Tendai Moyo',
        'phone' => '+263781879820',
    ])->id]);

    $this->actingAs($this->owner)
        ->get('/admin/reminders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('due.0.phone', '+263781879820')
            ->where('due.0.phone_display', '078 187 9820')
            ->where('due.0.whatsapp_number', '263781879820')
            // First name only, and the real gap, so it does not read as a blast.
            ->where('due.0.message', fn (string $message): bool => str_contains($message, 'Hi Tendai')
                && str_contains($message, '30 days'))
        );
});

it('keeps the number from a barber who can still see who has lapsed', function () {
    $barber = User::factory()->create();
    $barber->branches()->attach($this->branch, ['is_primary' => true]);
    setPermissionsTeamId($this->branch->id);
    $barber->assignRole('barber');
    setPermissionsTeamId(null);

    lapsedClient(30, ['user_id' => User::factory()->client()->create([
        'phone' => '+263781879820',
    ])->id]);

    $this->actingAs($barber)
        ->get('/admin/reminders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('due.0.phone', '+2637****820')
            ->where('due.0.whatsapp_number', null)
            ->where('can.send', false));
});

it('logs a client as chased once reception opens WhatsApp', function () {
    $client = lapsedClient(30);

    $this->actingAs($this->owner)
        ->put('/admin/reminders/messaged', [
            'client' => $client->ulid,
            'days_since_visit' => 30,
        ])
        ->assertRedirect();

    expect(ReminderSchedule::where('client_id', $client->id)->whereNotNull('sent_at')->count())
        ->toBe(1);

    // And the board says so, rather than making somebody remember.
    expect($this->reminders->board($this->branch)['due'][0]['last_messaged'])
        ->toBe(now()->toDateString());
});

it('closes the queued reminder rather than raising a second one', function () {
    $client = lapsedClient(30);
    $this->reminders->schedule($this->branch);

    $this->actingAs($this->owner)
        ->put('/admin/reminders/messaged', ['client' => $client->ulid])
        ->assertRedirect();

    expect(ReminderSchedule::where('client_id', $client->id)->count())->toBe(1)
        ->and(ReminderSchedule::pending()->count())->toBe(0);
});

it('will not let a barber log a reminder', function () {
    $barber = User::factory()->create();
    $barber->branches()->attach($this->branch, ['is_primary' => true]);
    setPermissionsTeamId($this->branch->id);
    $barber->assignRole('barber');
    setPermissionsTeamId(null);

    $this->actingAs($barber)
        ->put('/admin/reminders/messaged', ['client' => lapsedClient(30)->ulid])
        ->assertForbidden();
});

it('counts the overdue for the sidebar without building the board', function () {
    lapsedClient(30);
    lapsedClient(25);
    lapsedClient(10, ['preferred_cycle_days' => 7]);  // weekly, so also late
    lapsedClient(2);

    expect($this->reminders->dueCount($this->branch))
        ->toBe(count($this->reminders->board($this->branch)['due']))
        ->toBe(3);
});

it('drops the badge count as soon as somebody books', function () {
    $client = lapsedClient(30);

    expect($this->reminders->dueCount($this->branch))->toBe(1);

    Cache::put('reminders.due.'.$this->branch->id, 99, now()->addHour());

    // Booking cancels the chase, which has to invalidate the cached badge too.
    $this->reminders->cancelFor($client->id);

    expect(Cache::get('reminders.due.'.$this->branch->id))->toBeNull();
});
