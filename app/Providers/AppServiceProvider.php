<?php

namespace App\Providers;

use App\Services\GoogleCalendar\CalendarSynchronizer;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CalendarSynchronizer::class, GoogleCalendarService::class);
    }

    public function boot(): void
    {
        //
    }
}
