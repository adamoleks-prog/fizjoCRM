<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Services\Backup\BackupRunner;
use App\Services\Messaging\AppSettings;
use App\Services\Monitoring\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupRunner $backups) {}

    public function index(AppSettings $settings): View
    {
        return view('admin.backups', [
            'backups' => $this->backups->list(),
            'lastSuccess' => $this->backups->lastSuccess(),
            'lastError' => $settings->get('backup.last_error'),
            'keepDays' => config('backup.keep_days'),
        ]);
    }

    public function store(): RedirectResponse
    {
        RunBackupJob::dispatch();

        return back()->with('status', 'Kopia w kolejce — pojawi się na liście w ciągu minuty lub dwóch. Odśwież stronę.');
    }

    /** A backup holds every patient's records — each download is logged and alerted. */
    public function download(string $name, SecurityLog $log): BinaryFileResponse
    {
        $path = $this->backups->path($name) ?? abort(404);

        $log->record(SecurityEventType::BackupDownloaded, ['file' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/octet-stream']);
    }
}
