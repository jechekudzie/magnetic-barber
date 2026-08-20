<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Once a day is enough for retention: a client who is one day overdue is not
| a different client at 09:00 than at 09:05. Run in the morning, in shop
| hours, so anyone acting on the queue is at work when it appears.
|
*/

Schedule::command('reminders:dispatch')
    ->dailyAt('09:00')
    ->timezone(config('magnetic.timezone', 'Africa/Harare'))
    ->withoutOverlapping();
