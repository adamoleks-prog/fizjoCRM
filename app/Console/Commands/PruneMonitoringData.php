<?php

namespace App\Console\Commands;

use App\Models\AppError;
use App\Models\SecurityEvent;
use Illuminate\Console\Command;

/**
 * Security events are kept for a year, resolved errors for three months. The
 * patient access log is not touched here — it is part of the medical records
 * trail and is kept as long as the records themselves.
 */
class PruneMonitoringData extends Command
{
    protected $signature = 'monitoring:prune';

    protected $description = 'Delete security events older than 12 months and old resolved errors';

    public const SECURITY_EVENTS_MONTHS = 12;

    public const RESOLVED_ERRORS_DAYS = 90;

    public function handle(): int
    {
        $events = SecurityEvent::where('created_at', '<', now()->subMonths(self::SECURITY_EVENTS_MONTHS))->delete();
        $errors = AppError::whereNotNull('resolved_at')->where('last_seen_at', '<', now()->subDays(self::RESOLVED_ERRORS_DAYS))->delete();

        $this->info("Usunięto zdarzeń: {$events}, błędów: {$errors}.");

        return self::SUCCESS;
    }
}
