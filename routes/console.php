<?php

use App\Services\Monitoring\Heartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminders go out during the day only — nobody wants a text about tomorrow's
// physiotherapy at 3 a.m. Requires the cron entry for schedule:run on the server.
Schedule::command('reminders:send')->hourly()->between('8:00', '20:00')->withoutOverlapping();

// Monitoring: a "still alive" mark every minute, an outside ping every five,
// the morning report for the administrators and a nightly clean-up.
Schedule::call(fn () => Heartbeat::beat(Heartbeat::SCHEDULER))->everyMinute()->name('heartbeat');
Schedule::command('monitoring:ping')->everyFiveMinutes();
Schedule::command('monitoring:daily-report')->dailyAt('07:00');
Schedule::command('monitoring:prune')->dailyAt('03:30');

// Database and patient files, encrypted; kept for 14 days on the server.
Schedule::command('backup:run')->dailyAt('02:30')->withoutOverlapping(120);
