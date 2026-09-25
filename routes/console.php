<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminders go out during the day only — nobody wants a text about tomorrow's
// physiotherapy at 3 a.m. Requires the cron entry for schedule:run on the server.
Schedule::command('reminders:send')->hourly()->between('8:00', '20:00')->withoutOverlapping();
