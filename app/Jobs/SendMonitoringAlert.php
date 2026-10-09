<?php

namespace App\Jobs;

use App\Mail\MonitoringAlertMail;
use App\Services\Messaging\OutgoingMail;
use App\Services\Monitoring\MonitoringRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * E-mail to the administrators about a security event or an application error.
 * Without a configured mail server it does nothing — the event is still in the
 * panel, and "Stan systemu" warns that alerts cannot be delivered.
 */
class SendMonitoringAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $subject,
        public readonly array $lines,
        public readonly string $link,
    ) {}

    public function handle(OutgoingMail $mail, MonitoringRecipients $recipients): void
    {
        if (! $mail->isConfigured()) {
            return;
        }

        foreach ($recipients->all() as $address) {
            $mail->send($address, new MonitoringAlertMail($this->subject, $this->lines, $this->link));
        }
    }
}
