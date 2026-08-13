<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->branch = Branch::factory()->create([
        'opens_at' => '09:00',
        'closes_at' => '12:00',
        'days_open' => [1, 2, 3, 4, 5],
        'timezone' => 'Africa/Harare',
    ]);

    $this->barber = User::factory()->create();
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($this->barber);

    $this->service = Service::factory()->create([
        'default_duration_minutes' => 60,
        'buffer_minutes' => 0,
    ]);
    $this->branch->services()->attach($this->service, [
        'price_cents' => 1200,
        'duration_minutes' => 60,
        'is_active' => true,
    ]);

    $this->availability = app(AvailabilityService::class);

    // A Monday well clear of today, so "lead time" never trims the grid.
    $this->monday = Carbon::parse('next monday', 'Africa/Harare')->addWeek()->startOfDay();
});

it('slices the shift into slots on a 15 minute grid', function () {
    $result = $this->availability->forDate($this->branch, $this->monday, [$this->service->id]);

    // 09:00 to 12:00 with a 60 minute service: last start is 11:00.
    $labels = collect($result['any_staff'])->pluck('label')->all();

    expect($result['closed'])->toBeFalse()
        ->and($result['duration_minutes'])->toBe(60)
        ->and($labels)->toContain('9:00am', '9:15am', '11:00am')
        ->and($labels)->not->toContain('11:15am');
});

it('reports the shop closed on a day it does not trade', function () {
    $sunday = $this->monday->copy()->subDay();

    $result = $this->availability->forDate($this->branch, $sunday, [$this->service->id]);

    expect($result['closed'])->toBeTrue()
        ->and($result['any_staff'])->toBeEmpty()
        ->and($result['reason'])->toBe('The shop is closed that day.');
});

it('removes slots that would overlap an existing booking', function () {
    $start = $this->monday->copy()->setTime(10, 0);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'staff_id' => $this->barber->id,
        'scheduled_start_at' => $start->copy()->utc(),
        'scheduled_end_at' => $start->copy()->addMinutes(60)->utc(),
    ]);

    $labels = collect(
        $this->availability->forDate($this->branch, $this->monday, [$this->service->id])['any_staff']
    )->pluck('label')->all();

    // Anything starting between 9:15 and 10:45 would run into the booking.
    expect($labels)->toContain('9:00am', '11:00am')
        ->and($labels)->not->toContain('10:00am', '9:30am', '10:45am');
});

it('ignores a cancelled booking when working out what is free', function () {
    $start = $this->monday->copy()->setTime(10, 0);

    Appointment::factory()->cancelled()->create([
        'branch_id' => $this->branch->id,
        'staff_id' => $this->barber->id,
        'scheduled_start_at' => $start->copy()->utc(),
        'scheduled_end_at' => $start->copy()->addMinutes(60)->utc(),
    ]);

    $labels = collect(
        $this->availability->forDate($this->branch, $this->monday, [$this->service->id])['any_staff']
    )->pluck('label')->all();

    expect($labels)->toContain('10:00am');
});

it('honours a barber shift that is shorter than the shop hours', function () {
    WorkingHour::create([
        'branch_id' => $this->branch->id,
        'user_id' => $this->barber->id,
        'weekday' => (int) $this->monday->dayOfWeek,
        'starts_at' => '10:00',
        'ends_at' => '12:00',
    ]);

    $labels = collect(
        $this->availability->forDate($this->branch, $this->monday, [$this->service->id])['any_staff']
    )->pluck('label')->all();

    expect($labels)->not->toContain('9:00am')
        ->and($labels)->toContain('10:00am', '11:00am');
});

it('adds up the duration of every chosen service', function () {
    $second = Service::factory()->create(['default_duration_minutes' => 30, 'buffer_minutes' => 5]);
    $this->branch->services()->attach($second, [
        'price_cents' => 800,
        'duration_minutes' => 30,
        'is_active' => true,
    ]);

    $minutes = $this->availability->requiredMinutes(
        $this->branch,
        [$this->service->id, $second->id],
    );

    expect($minutes)->toBe(95);
});

/**
 * The calendar is shown before services are picked, so with nothing chosen it
 * falls back to the shortest thing the branch sells and says so.
 */
it('draws a provisional grid before any service is chosen', function () {
    $result = $this->availability->forDate($this->branch, $this->monday, []);

    expect($result['provisional'])->toBeTrue()
        ->and($result['closed'])->toBeFalse()
        ->and($result['duration_minutes'])->toBe(60)
        ->and($result['any_staff'])->not->toBeEmpty();
});

it('uses the shortest service for the provisional block', function () {
    $quick = Service::factory()->create(['default_duration_minutes' => 20, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($quick, [
        'price_cents' => 700,
        'duration_minutes' => 20,
        'is_active' => true,
    ]);

    expect($this->availability->forDate($this->branch, $this->monday, [])['duration_minutes'])
        ->toBe(20);
});

it('marks the grid as final once real services are chosen', function () {
    $result = $this->availability->forDate($this->branch, $this->monday, [$this->service->id]);

    expect($result['provisional'])->toBeFalse();
});

it('leaves out a barber who is not bookable', function () {
    $hidden = User::factory()->create();
    StaffProfile::factory()->for($hidden)->create(['is_bookable' => false]);
    $this->branch->staff()->attach($hidden);

    $result = $this->availability->forDate($this->branch, $this->monday, [$this->service->id]);

    expect($result['staff'])->toHaveCount(1);
});

it('serves the grid over http for the wizard', function () {
    $this->getJson('/book/availability?'.http_build_query([
        'date' => $this->monday->toDateString(),
        'service_ids' => [$this->service->ulid],
        'staff' => 'any',
    ]))
        ->assertOk()
        ->assertJsonPath('duration_minutes', 60)
        ->assertJsonPath('closed', false);
});
