<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VisitCardMail extends Mailable
{
    public function __construct(
        public readonly Appointment $appointment,
        public readonly User $physiotherapist,
        private readonly string $pdf,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Karta wizyty z '.$this->appointment->starts_at->format('d.m.Y'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.visit-card');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
