<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationEvents;
use App\Services\Anonymization\NameDictionaries;
use App\Services\Encryption\PatientCipher;
use App\Services\Encryption\PatientKeyring;
use App\Services\GoogleCalendar\CalendarSynchronizer;
use App\Services\GoogleCalendar\GoogleCalendarService;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\SmsApiGateway;
use App\Services\Messaging\SmsGateway;
use App\Services\Monitoring\Heartbeat;
use App\Services\TherapySuggestion\OpenRouterClient;
use App\Services\TherapySuggestion\SuggestionProvider;
use App\Services\WorkSchedule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
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
        // Decrypted patient keys live only for one request or queue job.
        $this->app->scoped(PatientKeyring::class);
        $this->app->scoped(PatientCipher::class);

        // Tens of thousands of entries — built once per process, not per document.
        $this->app->singleton(NameDictionaries::class);
    }

    public function boot(): void
    {
        // Public booking pages. Named limiters keep separate counters — the
        // anonymous "throttle:N,1" form shares one counter per IP across routes.
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('booking-submit', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        Event::subscribe(RecordAuthenticationEvents::class);

        // The worker loops between jobs; one mark every half minute is plenty.
        Event::listen(Looping::class, function () {
            static $last = 0;
            if (time() - $last >= 30) {
                $last = time();
                Heartbeat::beat(Heartbeat::QUEUE);
            }
        });
    }
}
