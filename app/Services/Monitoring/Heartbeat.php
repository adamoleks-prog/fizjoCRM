<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * "Still alive" marks from the background processes: the scheduler (cron
 * schedule:run) and the queue worker (cron queue:work). When a mark goes stale,
 * reminders, calendar sync and the assistant have quietly stopped.
 */
class Heartbeat
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    /** Both run from cron every minute — a few minutes of silence means they stopped. */
    public const STALE_AFTER_MINUTES = 5;

    public static function beat(string $name): void
    {
        Cache::put('heartbeat.'.$name, now()->getTimestamp(), now()->addDays(30));
    }

    public static function last(string $name): ?CarbonImmutable
    {
        $timestamp = Cache::get('heartbeat.'.$name);

        return $timestamp ? CarbonImmutable::createFromTimestamp($timestamp)->setTimezone(config('app.timezone')) : null;
    }

    public static function isAlive(string $name): bool
    {
        $last = self::last($name);

        return $last !== null && $last->greaterThan(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }
}
