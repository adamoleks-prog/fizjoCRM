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
 *
 * The patients' data keys go to a separate file kept only a few days. Restoring
 * takes any data backup plus the newest key file (keys are only ever added or
 * destroyed, never changed). A patient erased today has no key in key files
 * made from now on, so once the older key files expire, their records are
 * unreadable in every data backup, including the 14-day-old ones.
 */
class BackupRunner
{
    public const PREFIX = 'fizjoroom-';

    public const EXTENSION = '.tar.gz.enc';

    public const KEYS_PREFIX = 'klucze-fizjoroom-';

    public const KEYS_EXTENSION = '.sql.enc';

    public const KEYS_TABLE = 'patient_keys';

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

        $stamp = now()->format('Y-m-d-His');
        $name = self::PREFIX.$stamp.self::EXTENSION;
        $keysName = self::KEYS_PREFIX.$stamp.self::KEYS_EXTENSION;
        $work = $dir.'/tmp-'.Str::random(12);
        File::ensureDirectoryExists($work, 0700);

        $this->settings->put(['backup.last_attempt_at' => now()->toIso8601String()]);

        try {
            $this->dumpDatabase($work.'/database.sql', $work.'/my.cnf', ignore: [self::KEYS_TABLE]);
            $this->dumpDatabase($work.'/keys.sql', $work.'/my.cnf', only: [self::KEYS_TABLE]);
            $this->pack($work.'/database.sql', $work.'/backup.tar.gz');
            $this->encrypt($work.'/backup.tar.gz', $dir.'/'.$name);
            $this->encrypt($work.'/keys.sql', $dir.'/'.$keysName);
            @chmod($dir.'/'.$name, 0600);
            @chmod($dir.'/'.$keysName, 0600);

            $this->settings->put([
                'backup.last_success_at' => now()->toIso8601String(),
                'backup.last_file' => $name,
                'backup.last_error' => null,
            ]);
        } catch (Throwable $e) {
            File::delete([$dir.'/'.$name, $dir.'/'.$keysName]);
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
    public function list(bool $keys = false): Collection
    {
        $pattern = $keys ? self::KEYS_PREFIX.'*'.self::KEYS_EXTENSION : self::PREFIX.'*'.self::EXTENSION;

        return collect(File::glob(config('backup.path').'/'.$pattern) ?: [])
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
        $match = $this->list()->firstWhere('name', $name) ?? $this->list(keys: true)->firstWhere('name', $name);

        return $match ? config('backup.path').'/'.$match['name'] : null;
    }

    public function prune(): int
    {
        return $this->pruneKind(false, config('backup.keep_days')) + $this->pruneKind(true, config('backup.keys_keep_days'));
    }

    private function pruneKind(bool $keys, int $days): int
    {
        $all = $this->list($keys);
        $old = $all->filter(fn ($backup) => $backup['created_at']->lessThan(now()->subDays($days)));

        // Never delete the only copy there is.
        if ($old->count() === $all->count()) {
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

    /**
     * @param  list<string>  $ignore  tables left out
     * @param  list<string>  $only  dump just these tables
     */
    private function dumpDatabase(string $target, string $credentials, array $ignore = [], array $only = []): void
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
            ...array_map(fn (string $table) => '--ignore-table='.$db['database'].'.'.$table, $ignore),
            $db['database'],
            ...$only,
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
