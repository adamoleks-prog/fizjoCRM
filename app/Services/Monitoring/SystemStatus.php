<?php

namespace App\Services\Monitoring;

use App\Enums\AppointmentStatus;
use App\Enums\SecurityEventType;
use App\Models\AppError;
use App\Models\SecurityEvent;
use App\Services\Backup\BackupRunner;
use App\Services\Booking\OnlineBooking;
use App\Services\Booking\Recaptcha;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\OutgoingMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Everything the administrator should know is fine — or is not — in one place:
 * background processes, failed deliveries, waiting bookings, disk, risky settings.
 * Used by the "Stan systemu" page and the morning report.
 */
class SystemStatus
{
    public function __construct(
        private readonly AppSettings $settings,
        private readonly OutgoingMail $mail,
        private readonly Recaptcha $recaptcha,
        private readonly BackupRunner $backups,
    ) {}

    /**
     * @return list<array{label: string, state: 'ok'|'warning'|'error', detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->heartbeat(Heartbeat::SCHEDULER, 'Harmonogram zadań (cron schedule:run)', 'Przypomnienia, raport dzienny i porządkowanie logów nie działają. Dodaj wpis cron dla schedule:run.'),
            $this->heartbeat(Heartbeat::QUEUE, 'Kolejka zadań (cron queue:work)', 'Synchronizacja z Google, asystent terapii i alerty e-mail czekają. Sprawdź wpis cron dla queue:work.'),
            $this->backup(),
            $this->failedJobs(),
            $this->mailConfigured(),
            $this->reminders(),
            $this->assistant(),
            $this->documents(),
            $this->pendingBookings(),
            $this->disk(),
            $this->logs(),
            $this->debugMode(),
            $this->bookingProtection(),
            $this->errors(),
        ];
    }

    /**
     * @return array{byType: Collection<int, object>, topIps: Collection<int, object>, total: int}
     */
    public function securitySummary(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        $byType = SecurityEvent::where('created_at', '>=', $since)
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) ['type' => $row->type, 'total' => (int) $row->total]);

        $topIps = SecurityEvent::where('created_at', '>=', $since)
            ->whereIn('severity', ['warning', 'critical'])
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('count(*) as total'), DB::raw('max(created_at) as last_at'))
            ->groupBy('ip_address')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return ['byType' => $byType, 'topIps' => $topIps, 'total' => (int) $byType->sum('total')];
    }

    /**
     * @return array{label: string, state: 'ok'|'warning'|'error', detail: string}
     */
    private function heartbeat(string $name, string $label, string $whenDead): array
    {
        $last = Heartbeat::last($name);

        if (Heartbeat::isAlive($name)) {
            return $this->ok($label, 'Ostatnio: '.$last->format('d.m.Y H:i'));
        }

        return $this->error($label, ($last ? 'Ostatni sygnał: '.$last->format('d.m.Y H:i').'. ' : 'Brak sygnału — nigdy nie uruchomiony. ').$whenDead);
    }

    private function backup(): array
    {
        $last = $this->backups->lastSuccess();
        $error = $this->settings->get('backup.last_error');

        if ($last === null) {
            return $this->error('Kopia zapasowa', $error ? 'Nieudana: '.$error : 'Jeszcze nie było kopii — wykonuje się codziennie o 2:30.');
        }

        // Daily at 2:30 — more than a day and a bit means a run was missed.
        if ($last->lessThan(now()->subHours(26))) {
            return $this->error('Kopia zapasowa', 'Ostatnia udana: '.$last->format('d.m.Y H:i').'.'.($error ? ' Ostatni błąd: '.$error : ''));
        }

        return $this->ok('Kopia zapasowa', 'Ostatnia: '.$last->format('d.m.Y H:i').', kopii na serwerze: '.$this->backups->list()->count().'.');
    }

    private function failedJobs(): array
    {
        $count = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDays(7))->count();

        return $count === 0
            ? $this->ok('Nieudane zadania w tle (7 dni)', 'Brak')
            : $this->warning('Nieudane zadania w tle (7 dni)', $count.' — szczegóły niżej.');
    }

    private function mailConfigured(): array
    {
        return $this->mail->isConfigured()
            ? $this->ok('Poczta wychodząca', 'Skonfigurowana — alerty i raporty mogą wychodzić.')
            : $this->error('Poczta wychodząca', 'Nieskonfigurowana — alerty i raport dzienny nie wyjdą. Ustaw SMTP w „Ustawieniach wysyłki”.');
    }

    private function reminders(): array
    {
        $failed = DB::table('appointment_reminders')->where('status', 'failed')->where('created_at', '>=', now()->subDay());
        $count = (clone $failed)->count();

        if ($count === 0) {
            return $this->ok('Przypomnienia SMS/e-mail (24 h)', 'Bez nieudanych wysyłek.');
        }

        $reasons = (clone $failed)->select('error', DB::raw('count(*) as total'))->groupBy('error')->orderByDesc('total')->limit(3)->get()
            ->map(fn ($row) => ($row->error ?: 'nieznany powód').' ×'.$row->total)->implode('; ');

        return $this->warning('Przypomnienia SMS/e-mail (24 h)', "Nieudanych: {$count}. {$reasons}");
    }

    private function assistant(): array
    {
        $failed = DB::table('ai_recommendations')->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();
        $cost = (float) DB::table('ai_recommendations')->where('created_at', '>=', now()->startOfMonth())->sum('cost');
        $costText = 'Koszt w tym miesiącu: $'.number_format($cost, 2, ',', ' ').'.';

        return $failed === 0
            ? $this->ok('Asystent terapii', $costText)
            : $this->warning('Asystent terapii', "Nieudanych podpowiedzi (7 dni): {$failed}. {$costText}");
    }

    private function documents(): array
    {
        $count = DB::table('documents')->where('text_extraction_status', 'failed')->count();

        return $count === 0
            ? $this->ok('Odczyt tekstu dokumentów', 'Bez błędów.')
            : $this->warning('Odczyt tekstu dokumentów', "Dokumentów, których nie udało się odczytać: {$count}.");
    }

    private function pendingBookings(): array
    {
        $count = DB::table('appointments')->whereNull('deleted_at')
            ->where('status', AppointmentStatus::Pending->value)
            ->where('created_at', '<', now()->subDay())
            ->count();

        return $count === 0
            ? $this->ok('Zapisy online do potwierdzenia', 'Nic nie czeka dłużej niż dobę.')
            : $this->warning('Zapisy online do potwierdzenia', "Czeka dłużej niż 24 h: {$count}.");
    }

    private function disk(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if (! $free || ! $total) {
            return $this->warning('Miejsce na dysku', 'Nie udało się odczytać.');
        }

        $percent = $free / $total * 100;
        $detail = sprintf('Wolne: %s z %s (%d%%).', $this->bytes($free), $this->bytes($total), $percent);

        return match (true) {
            $percent < 10 => $this->error('Miejsce na dysku', $detail),
            $percent < 20 => $this->warning('Miejsce na dysku', $detail),
            default => $this->ok('Miejsce na dysku', $detail),
        };
    }

    private function logs(): array
    {
        $size = collect(File::glob(storage_path('logs/*.log')) ?: [])->sum(fn ($file) => @filesize($file) ?: 0);
        $detail = 'Pliki logów: '.$this->bytes($size).'.';

        return $size > 500 * 1024 * 1024
            ? $this->warning('Logi aplikacji', $detail.' Bardzo duże — sprawdź, co je zapełnia.')
            : $this->ok('Logi aplikacji', $detail.' Rotacja dzienna, '.config('logging.channels.daily.max_files').' dni.');
    }

    private function debugMode(): array
    {
        return config('app.debug') && app()->isProduction()
            ? $this->error('Tryb debugowania', 'Włączony na produkcji — błędy pokazują szczegóły kodu. Ustaw APP_DEBUG=false.')
            : $this->ok('Tryb debugowania', config('app.debug') ? 'Włączony (środowisko '.app()->environment().').' : 'Wyłączony.');
    }

    private function bookingProtection(): array
    {
        if (OnlineBooking::testModeUntil($this->settings) !== null) {
            return $this->warning('Ochrona zapisów online', 'Włączony tryb testowy — kod 123456 działa dla każdego.');
        }

        $smsCode = $this->settings->get('booking.sms_verification', '1') === '1';

        if (! $smsCode && ! $this->recaptcha->isActive()) {
            return $this->warning('Ochrona zapisów online', 'Zapisy bez kodu SMS i bez reCAPTCHA — boty mogą zapełnić kalendarz.');
        }

        return $this->ok('Ochrona zapisów online', $smsCode ? 'Kod SMS.' : 'reCAPTCHA.');
    }

    private function errors(): array
    {
        $count = AppError::whereNull('resolved_at')->where('last_seen_at', '>=', now()->subDay())->count();

        return $count === 0
            ? $this->ok('Błędy aplikacji (24 h)', 'Brak.')
            : $this->error('Błędy aplikacji (24 h)', "Nierozwiązanych: {$count} — lista niżej.");
    }

    private function bytes(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i >= 3 ? 1 : 0, ',', ' ').' '.$units[$i];
    }

    private function ok(string $label, string $detail): array
    {
        return ['label' => $label, 'state' => 'ok', 'detail' => $detail];
    }

    private function warning(string $label, string $detail): array
    {
        return ['label' => $label, 'state' => 'warning', 'detail' => $detail];
    }

    private function error(string $label, string $detail): array
    {
        return ['label' => $label, 'state' => 'error', 'detail' => $detail];
    }

    /** Types worth a separate line in the morning report. */
    public static function reportedTypes(): array
    {
        return [
            SecurityEventType::LoginFailed, SecurityEventType::Lockout, SecurityEventType::AccessDenied,
            SecurityEventType::Probe, SecurityEventType::Throttled, SecurityEventType::BookingHoneypot,
            SecurityEventType::BookingRecaptchaFailed, SecurityEventType::SettingsChanged,
            SecurityEventType::PasswordReset, SecurityEventType::PasswordChanged, SecurityEventType::EmailChanged,
        ];
    }
}
