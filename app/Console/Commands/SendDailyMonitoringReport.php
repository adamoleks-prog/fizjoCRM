<?php

namespace App\Console\Commands;

use App\Mail\DailyMonitoringReportMail;
use App\Models\AppError;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\OutgoingMail;
use App\Services\Monitoring\MonitoringRecipients;
use App\Services\Monitoring\SystemStatus;
use Illuminate\Console\Command;

class SendDailyMonitoringReport extends Command
{
    protected $signature = 'monitoring:daily-report';

    protected $description = 'E-mail the administrators the morning system and security summary';

    public function handle(SystemStatus $status, OutgoingMail $mail, MonitoringRecipients $recipients, AppSettings $settings): int
    {
        if ($settings->get('monitoring.daily_report', '1') !== '1') {
            $this->info('Raport dzienny wyłączony.');

            return self::SUCCESS;
        }

        if (! $mail->isConfigured()) {
            $this->warn('Poczta nie jest skonfigurowana — raport nie wyszedł.');

            return self::SUCCESS;
        }

        $report = new DailyMonitoringReportMail(
            $status->checks(),
            $status->securitySummary(24),
            AppError::whereNull('resolved_at')->where('last_seen_at', '>=', now()->subDay())->orderByDesc('last_seen_at')->limit(10)->get(),
        );

        foreach ($recipients->all() as $address) {
            $mail->send($address, $report);
        }

        $this->info('Wysłano raport.');

        return self::SUCCESS;
    }
}
