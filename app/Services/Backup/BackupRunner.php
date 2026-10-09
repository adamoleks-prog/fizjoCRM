<?php

namespace App\Services\Backup;

use App\Services\Messaging\AppSettings;
use App\Services\Monitoring\SecurityAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * A full copy of what cannot be recreated: the database and the patients'
 * files. Packed with tar and encrypted with openssl using the application key,
 * so a downloaded copy is useless without the key — and restoring needs only
 * openssl and the key, not this application.
 */
class BackupRunner
{
    public const PREFIX = 'fizjoroom-';

    public const EXTENSION = '.tar.gz.enc';

    public function __construct(
        private readonly AppSettings $settings,
        private readonly SecurityAlerts $alerts,
    ) {}

    /**
     * @return string the backup file name
     *
     * @throws RuntimeException
     */
    public function run(): string
    {
        $dir = config('backup.path');
        File::ensureDirectoryExists($dir, 0700);

        $name = self::PREFIX.now()->format('Y-m-d-His').self::EXTENSION;
        $work = $dir.'/tmp-'.Str::random(12);
        File::ensureDirectoryExists($work, 0700);

        $this->settings->put(['backup.last_attempt_at' => now()->toIso8601String()]);

        try {
            $this->dumpDatabase($work.'/database.sql', $work.'/my.cnf');
            $this->pack($work.'/database.sql', $work.'/backup.tar.gz');
            $this->encrypt($work.'/backup.tar.gz', $dir.'/'.$name);
            @chmod($dir.'/'.$name, 0600);

            $this->settings->put([
                'backup.last_success_at' => now()->toIso8601String(),
                'backup.last_file' => $name,
                'backup.last_error' => null,
            ]);
        } catch (Throwable $e) {
            File::delete($dir.'/'.$name);
            $reason = Str::limit($e->getMessage(), 300);
            $this->settings->put(['backup.last_error' => $reason]);
            $this->alerts->send('backup-failed', 'Kopia zapasowa nie powiodła się', ['Powód: '.$reason], route('admin.backups.index'));

            throw new RuntimeException('Kopia zapasowa nie powiodła się: '.$reason, previous: $e);
        } finally {
            File::deleteDirectory($work);
        }

        $this->prune();

        return $name;
    }

    /**
     * @return Collection<int, array{name: string, size: int, created_at: CarbonImmutable}>
     */
    public function list(): Collection
    {
        return collect(File::glob(config('backup.path').'/'.self::PREFIX.'*'.self::EXTENSION) ?: [])
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'created_at' => CarbonImmutable::createFromTimestamp(filemtime($path))->setTimezone(config('app.timezone')),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    /** Full path of a listed backup, or null — the name is never used to build a path on its own. */
    public function path(string $name): ?string
    {
        $match = $this->list()->firstWhere('name', $name);

        return $match ? config('backup.path').'/'.$match['name'] : null;
    }

    public function prune(): int
    {
        $limit = now()->subDays(config('backup.keep_days'));
        $old = $this->list()->filter(fn ($backup) => $backup['created_at']->lessThan($limit));

        // Never delete the only copy there is.
        if ($old->count() === $this->list()->count()) {
            $old = $old->slice(1);
        }

        $old->each(fn ($backup) => File::delete(config('backup.path').'/'.$backup['name']));

        return $old->count();
    }

    public function lastSuccess(): ?CarbonImmutable
    {
        $value = $this->settings->get('backup.last_success_at');

        return $value ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone')) : null;
    }

    private function dumpDatabase(string $target, string $credentials): void
    {
        $db = config('database.connections.'.config('backup.connection'));

        // The password goes in a private file, not on the command line where
        // any user of the server could see it in the process list.
        File::put($credentials, "[client]\nuser=\"".($db['username'] ?? '')."\"\npassword=\"".addcslashes((string) ($db['password'] ?? ''), '"\\')."\"\n"
            .(filled($db['host'] ?? null) ? "host=\"{$db['host']}\"\n" : '')
            .(filled($db['port'] ?? null) ? "port={$db['port']}\n" : ''));
        @chmod($credentials, 0600);

        $result = Process::timeout(1800)->run([
            config('backup.mysqldump'),
            '--defaults-extra-file='.$credentials,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$target,
            $db['database'],
        ]);

        if ($result->failed() || ! File::exists($target) || File::size($target) === 0) {
            throw new RuntimeException('zrzut bazy — '.trim(Str::limit($result->errorOutput() ?: 'pusty plik', 200)));
        }
    }

    private function pack(string $dump, string $target): void
    {
        $directories = collect(config('backup.directories'))
            ->filter(fn ($dir) => File::isDirectory(storage_path('app/'.$dir)))
            ->values()
            ->all();

        $result = Process::timeout(1800)->run([
            config('backup.tar'), '-czf', $target,
            '-C', dirname($dump), basename($dump),
            ...($directories === [] ? [] : ['-C', storage_path('app'), ...$directories]),
        ]);

        if ($result->failed() || ! File::exists($target)) {
            throw new RuntimeException('pakowanie plików — '.trim(Str::limit($result->errorOutput(), 200)));
        }
    }

    private function encrypt(string $source, string $target): void
    {
        $result = Process::timeout(1800)
            ->env(['BACKUP_PASSPHRASE' => (string) config('app.key')])
            ->run([
                config('backup.openssl'), 'enc', '-'.config('backup.cipher'), '-salt', '-pbkdf2',
                '-iter', (string) config('backup.iterations'),
                '-in', $source, '-out', $target,
                '-pass', 'env:BACKUP_PASSPHRASE',
            ]);

        if ($result->failed() || ! File::exists($target)) {
            throw new RuntimeException('szyfrowanie — '.trim(Str::limit($result->errorOutput(), 200)));
        }
    }
}
