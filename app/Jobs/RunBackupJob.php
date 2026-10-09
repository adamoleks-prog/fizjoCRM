<?php

namespace App\Jobs;

use App\Services\Backup\BackupRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Make a backup now" from the panel. The runner records the outcome and
 * alerts on failure itself.
 */
class RunBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(BackupRunner $backups): void
    {
        $backups->run();
    }
}
