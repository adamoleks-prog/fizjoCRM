<?php

namespace App\Providers;

use App\Services\Anonymization\NameDictionaries;
use App\Services\GoogleCalendar\CalendarSynchronizer;
use App\Services\GoogleCalendar\GoogleCalendarService;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\SmsApiGateway;
use App\Services\Messaging\SmsGateway;
use App\Services\TherapySuggestion\OpenRouterClient;
use App\Services\TherapySuggestion\SuggestionProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CalendarSynchronizer::class, GoogleCalendarService::class);
        $this->app->bind(SuggestionProvider::class, OpenRouterClient::class);
        $this->app->bind(SmsGateway::class, SmsApiGateway::class);

        // Read once per request (or queue job), dropped between them.
        $this->app->scoped(AppSettings::class);

        // Tens of thousands of entries — built once per process, not per document.
        $this->app->singleton(NameDictionaries::class);
    }

    public function boot(): void
    {
        //
    }
}
