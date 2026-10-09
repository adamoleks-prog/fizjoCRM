<?php

namespace App\Services\Monitoring;

use App\Enums\SecurityEventType;
use App\Jobs\SendMonitoringAlert;
use App\Models\AppError;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Messaging\AppSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Decides which events are worth an e-mail to the administrator. Each kind of
 * alert goes out at most once an hour, so an ongoing attack is one message,
 * not a flood.
 */
class SecurityAlerts
{
    public const FAILED_LOGINS_PER_IP = 10;

    public const FAILURES_BEFORE_LOGIN = 3;

    public function __construct(private readonly AppSettings $settings) {}

    public function afterSecurityEvent(SecurityEvent $event): void
    {
        $who = $event->email ?? $event->user?->email;
        $where = 'Adres IP: '.$event->ip_address;

        match ($event->type) {
            SecurityEventType::LoginFailed => $this->failedLogins($event),
            SecurityEventType::Lockout => $this->send(
                'lockout:'.$event->ip_address,
                'Zablokowano logowanie po zbyt wielu próbach',
                ['Konto: '.($who ?? '—'), $where, 'Jeśli to nie Ty ani nikt z gabinetu — ktoś próbuje zgadnąć hasło.'],
            ),
            SecurityEventType::Login => $this->login($event),
            SecurityEventType::PasswordReset, SecurityEventType::PasswordChanged, SecurityEventType::EmailChanged => $this->send(
                $event->type->value.':'.$event->user_id,
                $event->type->label().': '.($who ?? 'konto #'.$event->user_id),
                [$where, 'Jeśli nikt z gabinetu tego nie robił — konto mogło zostać przejęte. Zmień hasło i sprawdź dziennik bezpieczeństwa.'],
            ),
            SecurityEventType::SettingsChanged => $this->send(
                'settings:'.$event->id,
                'Zmieniono ustawienia: '.($event->details['area'] ?? 'panel'),
                [
                    'Kto: '.($event->user?->email ?? '—'),
                    $where,
                    'Zmienione pola: '.(implode(', ', $event->details['changed'] ?? []) ?: '—'),
                ],
            ),
            default => null,
        };
    }

    public function afterError(AppError $error, bool $first): void
    {
        $this->send(
            'error:'.$error->fingerprint,
            ($first ? 'Nowy błąd aplikacji: ' : 'Błąd aplikacji: ').class_basename($error->exception),
            [
                'Miejsce: '.$error->file.':'.$error->line,
                'Adres: '.$error->method.' '.$error->url,
                'Komunikat: '.$error->message,
                'Wystąpień łącznie: '.$error->occurrences,
            ],
            route('admin.system.show').'#bledy',
        );
    }

    /**
     * @param  list<string>  $lines
     */
    public function send(string $key, string $subject, array $lines, ?string $link = null): void
    {
        if (! $this->enabled() || ! Cache::add('monitoring-alert:'.$key, 1, 3600)) {
            return;
        }

        SendMonitoringAlert::dispatch(
            $subject,
            [...$lines, 'Czas: '.now()->format('d.m.Y H:i:s')],
            $link ?? route('admin.security.index'),
        );
    }

    public function enabled(): bool
    {
        return $this->settings->get('monitoring.alerts_enabled', '1') === '1';
    }

    private function failedLogins(SecurityEvent $event): void
    {
        $count = SecurityEvent::where('type', SecurityEventType::LoginFailed)
            ->where('ip_address', $event->ip_address)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($count >= self::FAILED_LOGINS_PER_IP) {
            $this->send(
                'failed-logins:'.$event->ip_address,
                "Wiele nieudanych logowań z adresu {$event->ip_address}",
                [
                    "W ciągu ostatniej godziny: {$count} prób.",
                    'Ostatnio wpisany e-mail: '.($event->email ?? '—'),
                    'Możliwa próba zgadywania haseł.',
                ],
            );
        }
    }

    private function login(SecurityEvent $event): void
    {
        $user = $event->user;
        $failures = (int) ($event->details['failures_before'] ?? 0);

        if ($failures >= self::FAILURES_BEFORE_LOGIN) {
            $this->send(
                'login-after-failures:'.$event->user_id.':'.$event->ip_address,
                'Udane logowanie po serii nieudanych prób: '.$user?->email,
                [
                    'Adres IP: '.$event->ip_address,
                    'Nieudanych prób w ostatniej godzinie: '.$failures,
                    'Jeśli to nie Ty — zmień hasło od razu.',
                ],
            );
        }

        if (($event->details['new_ip'] ?? false) && $user instanceof User && $user->isAdmin()) {
            $this->send(
                'admin-new-ip:'.$event->user_id.':'.$event->ip_address,
                'Logowanie administratora z nowego adresu IP',
                ['Konto: '.$user->email, 'Adres IP: '.$event->ip_address, 'Przeglądarka: '.$event->user_agent],
            );
        }
    }
}
