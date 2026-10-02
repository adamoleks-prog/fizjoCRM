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
use App\Services\WorkSchedule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->scoped(WorkSchedule::class);

        // Tens of thousands of entries — built once per process, not per document.
        $this->app->singleton(NameDictionaries::class);
    }

    public function boot(): void
    {
        // Public booking pages. Named limiters keep separate counters — the
        // anonymous "throttle:N,1" form shares one counter per IP across routes.
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('booking-submit', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
