<?php

namespace App\Services\Monitoring;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the security log. What goes in: the kind of event, who (account or
 * the e-mail typed at login), where from (IP, browser) and which address was
 * requested. What never goes in: passwords, codes, form contents, patient data.
 */
class SecurityLog
{
    /** A single scanner can send thousands of requests — beyond this many per hour, per kind and IP, the rest is not stored. */
    public const MAX_PER_HOUR = 30;

    public function __construct(private readonly SecurityAlerts $alerts) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        SecurityEventType $type,
        array $details = [],
        ?User $user = null,
        ?string $email = null,
        ?Request $request = null,
    ): ?SecurityEvent {
        try {
            $request ??= request();
            $ip = $request->ip();

            $key = 'security-log:'.$type->value.':'.$ip;
            if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
                return null;
            }
            RateLimiter::hit($key, 3600);

            $event = SecurityEvent::create([
                'type' => $type,
                'severity' => $type->severity(),
                'user_id' => $user?->id ?? $request->user()?->id,
                'email' => $email !== null ? Str::limit(Str::lower(trim($email)), 250, '') : null,
                'ip_address' => $ip,
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                'method' => $request->method(),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
                'details' => $details ?: null,
            ]);

            $this->alerts->afterSecurityEvent($event);

            return $event;
        } catch (Throwable) {
            // Logging must never break the page it is watching.
            return null;
        }
    }

    /**
     * Typical addresses automated scanners try on any site — this application
     * has none of them, so a request for one is somebody probing.
     */
    public static function isProbe(string $path): bool
    {
        $path = '/'.ltrim(Str::lower($path), '/');

        if ($path === '/index.php') {
            return false;
        }

        return (bool) preg_match(
            '~(^/wp-|/wordpress|xmlrpc\.php|/\.env|/\.git|/\.aws|/\.ssh|/\.svn|/\.htaccess|/\.ds_store|phpmyadmin|/pma\b|/myadmin|/adminer|'
            .'/cgi-bin|/vendor/|/phpunit|eval-stdin|/shell|/backup|\.sql(\.gz)?$|\.bak$|\.zip$|\.tar(\.gz)?$|/config\.(php|json|yml)|'
            .'/server-status|/actuator|/boaform|/solr|/telescope|/_ignition|/horizon|/storage/logs|\.php$)~',
            $path,
        );
    }
}
