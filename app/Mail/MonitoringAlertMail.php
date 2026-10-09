<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MonitoringAlertMail extends Mailable
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $alertSubject,
        public readonly array $lines,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.$this->alertSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.monitoring-alert');
    }
}
