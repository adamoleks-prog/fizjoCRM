<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupRunner;
use Illuminate\Console\Command;
use RuntimeException;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Kopia zapasowa bazy i dokumentów pacjentów (zaszyfrowana kluczem aplikacji)';

    public function handle(BackupRunner $backups): int
    {
        try {
            $name = $backups->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Gotowe: '.$name);

        return self::SUCCESS;
    }
}
