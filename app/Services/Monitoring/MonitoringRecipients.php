<?php

namespace App\Services\Monitoring;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Messaging\AppSettings;

/**
 * Who gets alerts and the daily report: every administrator, plus an optional
 * extra address set on the "Stan systemu" page.
 */
class MonitoringRecipients
{
    public function __construct(private readonly AppSettings $settings) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $addresses = User::where('role', UserRole::Admin)->pluck('email')->all();

        if ($extra = $this->settings->get('monitoring.alert_email')) {
            $addresses[] = $extra;
        }

        return array_values(array_unique(array_map('strtolower', array_filter($addresses))));
    }
}
