<?php

namespace App\Listeners;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Monitoring\SecurityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Logins, failed logins, lockouts, logouts and password resets go to the
 * security log. Only the e-mail that was typed is kept — never the password.
 */
class RecordAuthenticationEvents
{
    public function __construct(private readonly SecurityLog $log) {}

    public function failed(Failed $event): void
    {
        $this->log->record(
            SecurityEventType::LoginFailed,
            ['account_exists' => $event->user !== null],
            user: $event->user instanceof User ? $event->user : null,
            email: (string) ($event->credentials['email'] ?? ''),
        );
    }

    public function lockout(Lockout $event): void
    {
        $this->log->record(SecurityEventType::Lockout, email: (string) $event->request->input('email'), request: $event->request);
    }

    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $ip = request()->ip();

        $newIp = ! SecurityEvent::where('type', SecurityEventType::Login)
            ->where('user_id', $event->user->id)
            ->where('ip_address', $ip)
            ->exists();

        $failuresBefore = SecurityEvent::where('type', SecurityEventType::LoginFailed)
            ->where('email', strtolower($event->user->email))
            ->where('created_at', '>=', now()->subHour())
            ->count();

        // The very first login of an account is not "a new place" yet.
        $firstEver = ! SecurityEvent::where('type', SecurityEventType::Login)->where('user_id', $event->user->id)->exists();

        $this->log->record(
            SecurityEventType::Login,
            array_filter([
                'new_ip' => $newIp && ! $firstEver,
                'remember' => $event->remember,
                'failures_before' => $failuresBefore,
            ]),
            user: $event->user,
            email: $event->user->email,
        );
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(SecurityEventType::Logout, user: $event->user, email: $event->user->email);
        }
    }

    public function passwordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(SecurityEventType::PasswordReset, user: $event->user, email: $event->user->email);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'failed',
            Lockout::class => 'lockout',
            Login::class => 'login',
            Logout::class => 'logout',
            PasswordReset::class => 'passwordReset',
        ];
    }
}
