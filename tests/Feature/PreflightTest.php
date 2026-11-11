<?php

use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Otp\LogOtpChannel;
use App\Services\Otp\OtpChannel;
use Tests\Support\FakeOtpChannel;

/**
 * The launch check.
 *
 * Worth testing because it is the thing that gets trusted when nobody has
 * time to read config by hand, and a check that passes when it should not is
 * worse than no check.
 */
function readyShop(): void
{
    config()->set('app.debug', false);
    config()->set('app.env', 'production');
    config()->set('app.url', 'https://magneticbarber.co.zw');
    config()->set('mail.default', 'smtp');

    app()->instance(OtpChannel::class, new FakeOtpChannel);

    $branch = Branch::factory()->create(['is_active' => true]);
    $service = Service::factory()->create(['is_active' => true]);
    $branch->services()->attach($service, [
        'price_cents' => 1000, 'currency' => 'USD', 'duration_minutes' => 45, 'is_active' => true,
    ]);

    $barber = User::factory()->create();
    StaffProfile::factory()->for($barber)->create([
        'is_bookable' => true,
        'photo_path' => 'staff/one.jpg',
    ]);
    $branch->staff()->attach($barber, ['is_primary' => true]);
}

it('passes a shop that is actually ready', function () {
    readyShop();

    $this->artisan('magnetic:preflight')->assertSuccessful();
});

it('stops a launch with debug mode left on', function () {
    readyShop();
    config()->set('app.debug', true);

    $this->artisan('magnetic:preflight')
        ->expectsOutputToContain('Debug mode is on')
        ->assertFailed();
});

it('stops a launch served over plain http', function () {
    readyShop();
    config()->set('app.url', 'http://magneticbarber.co.zw');

    $this->artisan('magnetic:preflight')
        ->expectsOutputToContain('APP_URL is not https')
        ->assertFailed();
});

it('stops a launch whose password resets go to a log file', function () {
    readyShop();
    config()->set('mail.default', 'log');

    $this->artisan('magnetic:preflight')
        ->expectsOutputToContain('Mail goes to a log file')
        ->assertFailed();
});

it('stops a launch with nothing to book', function () {
    readyShop();
    Service::query()->update(['is_active' => false]);

    $this->artisan('magnetic:preflight')
        ->expectsOutputToContain('No active services')
        ->assertFailed();
});

/*
 * A warning, not a stop: the routes shut themselves while nothing delivers,
 * so this is a feature that is off rather than a hole that is open.
 */
it('mentions that login codes are off without blocking the launch', function () {
    readyShop();
    app()->instance(OtpChannel::class, new LogOtpChannel);

    $this->artisan('magnetic:preflight')
        ->expectsOutputToContain('Login codes cannot be sent')
        ->assertSuccessful();
});
