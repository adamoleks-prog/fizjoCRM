<?php

namespace App\Console\Commands;

use App\Services\Messaging\AppSettings;
use App\Services\Monitoring\Heartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tells an outside watcher (e.g. healthchecks.io) that the background processes
 * are alive. If the scheduler dies, the pings stop and the watcher raises the
 * alarm; if only the queue dies, the ping goes to the "/fail" address.
 */
class PingHealthcheck extends Command
{
    protected $signature = 'monitoring:ping';

    protected $description = 'Ping the external heartbeat URL set on the "Stan systemu" page';

    public function handle(AppSettings $settings): int
    {
        $url = $settings->get('monitoring.healthcheck_url');

        if (! $url) {
            return self::SUCCESS;
        }

        $queueAlive = Heartbeat::isAlive(Heartbeat::QUEUE);

        try {
            Http::timeout(10)->retry(2, 1000, throw: false)
                ->withBody($queueAlive ? 'ok' : 'Kolejka zadań nie działa', 'text/plain')
                ->post(rtrim($url, '/').($queueAlive ? '' : '/fail'));
        } catch (Throwable) {
            // The watcher alarms on a missing ping anyway.
        }

        return self::SUCCESS;
    }
}
