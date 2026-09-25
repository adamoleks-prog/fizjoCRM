<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Settings the administrator edits in the panel. Secrets (the SMTP password,
 * the SMS token) are encrypted with the application key before they are stored
 * and are never sent back to the browser.
 */
class AppSettings
{
    public const SECRETS = ['mail.password', 'sms.token'];

    public const KEYS = [
        'mail.host', 'mail.port', 'mail.encryption', 'mail.username', 'mail.password',
        'mail.from_address', 'mail.from_name',
        'sms.token', 'sms.sender',
        'reminders.email_enabled', 'reminders.sms_enabled', 'reminders.hours_before',
    ];

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function has(string $key): bool
    {
        return filled($this->get($key));
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            $value = $value === null || $value === '' ? null : (string) $value;

            if ($value !== null && in_array($key, self::SECRETS, true)) {
                $value = Crypt::encryptString($value);
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->values = null;
    }

    /**
     * @return array<string, string|null>
     */
    private function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = DB::table('settings')->pluck('value', 'key')->all();

        foreach (self::SECRETS as $key) {
            if (filled($values[$key] ?? null)) {
                $values[$key] = Crypt::decryptString($values[$key]);
            }
        }

        return $this->values = $values;
    }
}
