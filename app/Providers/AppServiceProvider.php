<?php

namespace App\Providers;

use App\Services\Anonymization\NameDictionaries;
use App\Services\GoogleCalendar\CalendarSynchronizer;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CalendarSynchronizer::class, GoogleCalendarService::class);

        // Tens of thousands of entries — built once per process, not per document.
        $this->app->singleton(NameDictionaries::class);
    }

    public function boot(): void
    {
        //
    }
}
