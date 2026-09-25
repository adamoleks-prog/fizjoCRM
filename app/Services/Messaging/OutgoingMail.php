<?php

namespace App\Services\Messaging;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends mail through the server configured in the admin panel. Those settings
 * live in the database rather than .env, so they are applied to the "smtp"
 * mailer right before sending.
 */
class OutgoingMail
{
    public function __construct(private readonly AppSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->has('mail.host') && $this->settings->has('mail.from_address');
    }

    /**
     * @throws MessagingNotConfigured
     */
    public function send(string $to, Mailable $mailable): void
    {
        if (! $this->isConfigured()) {
            throw new MessagingNotConfigured('Poczta nie jest skonfigurowana (Ustawienia wysyłki).');
        }

        $encryption = $this->settings->get('mail.encryption', 'tls');

        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $encryption === 'ssl' ? 'smtps' : null,
                'host' => $this->settings->get('mail.host'),
                'port' => (int) $this->settings->get('mail.port', $encryption === 'ssl' ? 465 : 587),
                'username' => $this->settings->get('mail.username'),
                'password' => $this->settings->get('mail.password'),
                'timeout' => 20,
            ],
            'mail.from' => [
                'address' => $this->settings->get('mail.from_address'),
                'name' => $this->settings->get('mail.from_name', config('app.name')),
            ],
        ]);

        // The manager keeps an already resolved mailer; drop it so the settings apply.
        Mail::purge('smtp');

        Mail::mailer('smtp')->to($to)->send($mailable);
    }
}
