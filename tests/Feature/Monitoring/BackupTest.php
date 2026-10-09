<?php

use App\Enums\SecurityEventType;
use App\Jobs\RunBackupJob;
use App\Jobs\SendMonitoringAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Backup\BackupRunner;
use App\Services\Messaging\AppSettings;
use App\Services\Monitoring\SystemStatus;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

/** The value after "--flag=" or after "-flag" in a faked command. */
function argAfter(array $command, string $flag): ?string
{
    foreach ($command as $i => $part) {
        if (str_starts_with($part, $flag.'=')) {
            return substr($part, strlen($flag) + 1);
        }
        if ($part === $flag) {
            return $command[$i + 1] ?? null;
        }
    }

    return null;
}

/** Fakes mysqldump, tar and openssl by writing the files they would produce. */
function fakeBackupTools(bool $dumpFails = false): void
{
    Process::fake(function (PendingProcess $process) use ($dumpFails) {
        $command = $process->command;

        if (str_contains($command[0], 'mysqldump')) {
            if ($dumpFails) {
                return Process::result(errorOutput: 'Access denied for user', exitCode: 2);
            }
            File::put(argAfter($command, '--result-file'), 'CREATE TABLE patients;');
        } elseif (str_contains($command[0], 'tar')) {
            File::put(argAfter($command, '-czf'), 'tar-data');
        } elseif (str_contains($command[0], 'openssl')) {
            File::put(argAfter($command, '-out'), 'encrypted');
        }

        return Process::result();
    });
}

beforeEach(function () {
    Queue::fake();
    config(['backup.path' => storage_path('framework/testing/backups-'.getmypid())]);
    File::deleteDirectory(config('backup.path'));
});

afterEach(function () {
    File::deleteDirectory(config('backup.path'));
});

test('a backup dumps the database, packs the patient files and encrypts with the app key', function () {
    fakeBackupTools();

    $name = app(BackupRunner::class)->run();

    expect(File::exists(config('backup.path').'/'.$name))->toBeTrue()
        ->and(File::glob(config('backup.path').'/tmp-*'))->toBe([]);

    Process::assertRan(fn (PendingProcess $p) => str_contains($p->command[0], 'mysqldump')
        && collect($p->command)->contains(fn ($part) => str_starts_with($part, '--defaults-extra-file='))
        // The database password is never on the command line.
        && ! collect($p->command)->contains(fn ($part) => str_contains($part, 'password')));
    Process::assertRan(fn (PendingProcess $p) => str_contains($p->command[0], 'tar') && in_array('private', $p->command, true));
    Process::assertRan(fn (PendingProcess $p) => str_contains($p->command[0], 'openssl')
        && in_array('env:BACKUP_PASSPHRASE', $p->command, true)
        && $p->environment['BACKUP_PASSPHRASE'] === config('app.key'));

    expect(app(BackupRunner::class)->lastSuccess())->not->toBeNull();
});

test('a failed backup leaves no partial file, records the reason and alerts', function () {
    fakeBackupTools(dumpFails: true);

    expect(fn () => app(BackupRunner::class)->run())->toThrow(RuntimeException::class);

    expect(app(BackupRunner::class)->list())->toBeEmpty()
        ->and(app(AppSettings::class)->get('backup.last_error'))->toContain('Access denied');
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'Kopia zapasowa nie powiodła się'));
});

test('old backups are pruned but the newest one always stays', function () {
    File::ensureDirectoryExists(config('backup.path'));
    foreach ([20, 16, 3] as $days) {
        $path = config('backup.path').'/'.BackupRunner::PREFIX."old-{$days}".BackupRunner::EXTENSION;
        File::put($path, 'x');
        touch($path, now()->subDays($days)->getTimestamp());
    }

    expect(app(BackupRunner::class)->prune())->toBe(2)
        ->and(app(BackupRunner::class)->list()->pluck('name')->all())->toBe([BackupRunner::PREFIX.'old-3'.BackupRunner::EXTENSION]);

    // Only stale copies left: the newest of them is kept.
    touch(config('backup.path').'/'.BackupRunner::PREFIX.'old-3'.BackupRunner::EXTENSION, now()->subDays(30)->getTimestamp());
    expect(app(BackupRunner::class)->prune())->toBe(0);
});

test('the system status reports a missing or stale backup', function () {
    $check = fn () => collect(app(SystemStatus::class)->checks())->firstWhere('label', 'Kopia zapasowa');

    expect($check()['state'])->toBe('error');

    app(AppSettings::class)->put(['backup.last_success_at' => now()->subHours(2)->toIso8601String()]);
    expect($check()['state'])->toBe('ok');

    app(AppSettings::class)->put(['backup.last_success_at' => now()->subDays(2)->toIso8601String()]);
    expect($check()['state'])->toBe('error');
});

test('the administrator lists, starts and downloads backups; each download is logged and alerted', function () {
    $admin = User::factory()->admin()->create();
    File::ensureDirectoryExists(config('backup.path'));
    $name = BackupRunner::PREFIX.'2026-10-09-023000'.BackupRunner::EXTENSION;
    File::put(config('backup.path').'/'.$name, 'encrypted');

    $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk()->assertSee($name);

    $this->actingAs($admin)->post(route('admin.backups.store'))->assertRedirect();
    Queue::assertPushed(RunBackupJob::class);

    $this->actingAs($admin)->get(route('admin.backups.download', $name))->assertOk()->assertDownload($name);
    expect(SecurityEvent::where('type', SecurityEventType::BackupDownloaded)->sole()->details['file'])->toBe($name);
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'Pobrano kopię zapasową'));
});

test('only listed backups can be downloaded, and only by an administrator', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();
    File::ensureDirectoryExists(config('backup.path'));
    $name = BackupRunner::PREFIX.'x'.BackupRunner::EXTENSION;
    File::put(config('backup.path').'/'.$name, 'encrypted');

    $this->actingAs($admin)->get(route('admin.backups.download', '..env'))->assertNotFound();
    $this->actingAs($admin)->get('/admin/backups/..%2F..%2F.env')->assertNotFound();
    $this->actingAs($operator)->get(route('admin.backups.download', $name))->assertForbidden();
    $this->actingAs($operator)->post(route('admin.backups.store'))->assertForbidden();
});
