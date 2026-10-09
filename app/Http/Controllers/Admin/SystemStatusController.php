<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Jobs\SendMonitoringAlert;
use App\Models\AppError;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\OutgoingMail;
use App\Services\Monitoring\Heartbeat;
use App\Services\Monitoring\MonitoringRecipients;
use App\Services\Monitoring\SecurityLog;
use App\Services\Monitoring\SystemStatus;
use App\Support\PersonalData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SystemStatusController extends Controller
{
    public function __construct(private readonly AppSettings $settings) {}

    public function show(SystemStatus $status, MonitoringRecipients $recipients, OutgoingMail $mail): View
    {
        $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(10)->get()
            ->map(fn ($job) => (object) [
                'id' => $job->id,
                'name' => class_basename(json_decode($job->payload, true)['displayName'] ?? 'zadanie'),
                'failed_at' => $job->failed_at,
                // First line only — the rest is a stack trace.
                'reason' => Str::limit(strtok((string) $job->exception, "\n"), 300),
            ]);

        return view('admin.system-status', [
            'checks' => $status->checks(),
            'security' => $status->securitySummary(24),
            'appErrors' => AppError::with('user')->orderByRaw('resolved_at is not null')->orderByDesc('last_seen_at')->limit(30)->get(),
            'failedJobs' => $failedJobs,
            'settings' => $this->settings,
            'recipients' => $recipients->all(),
            'mailConfigured' => $mail->isConfigured(),
            'scheduler' => Heartbeat::last(Heartbeat::SCHEDULER),
        ]);
    }

    public function update(Request $request, SecurityLog $log): RedirectResponse
    {
        $data = $request->validate([
            'alerts_enabled' => ['boolean'],
            'daily_report' => ['boolean'],
            'alert_email' => PersonalData::email(),
            'healthcheck_url' => ['nullable', 'url:https', 'max:255'],
        ]);

        $values = [
            'monitoring.alerts_enabled' => $request->boolean('alerts_enabled') ? '1' : '0',
            'monitoring.daily_report' => $request->boolean('daily_report') ? '1' : '0',
            'monitoring.alert_email' => $data['alert_email'] ?? null,
            'monitoring.healthcheck_url' => $data['healthcheck_url'] ?? null,
        ];

        $changed = array_keys(array_filter($values, fn ($value, $key) => (string) $this->settings->get($key) !== (string) $value, ARRAY_FILTER_USE_BOTH));
        $this->settings->put($values);

        if ($changed !== []) {
            $log->record(SecurityEventType::SettingsChanged, ['area' => 'Monitoring', 'changed' => $changed]);
        }

        return redirect()->route('admin.system.show')->with('status', 'Zapisano ustawienia monitoringu.');
    }

    public function testAlert(OutgoingMail $mail): RedirectResponse
    {
        if (! $mail->isConfigured()) {
            return back()->withErrors(['alert' => 'Najpierw skonfiguruj pocztę w „Ustawieniach wysyłki”.']);
        }

        SendMonitoringAlert::dispatch(
            'Test alertu',
            ['To jest alert testowy wysłany z „Stanu systemu”.', 'Czas: '.now()->format('d.m.Y H:i:s')],
            route('admin.system.show'),
        );

        return back()->with('status', 'Alert testowy w kolejce — dotrze w ciągu minuty, jeśli kolejka działa.');
    }

    public function resolveError(AppError $appError): RedirectResponse
    {
        $appError->update(['resolved_at' => now()]);

        return redirect()->to(route('admin.system.show').'#bledy')->with('status', 'Oznaczono jako rozwiązany. Jeśli błąd wróci — dostaniesz alert.');
    }
}
