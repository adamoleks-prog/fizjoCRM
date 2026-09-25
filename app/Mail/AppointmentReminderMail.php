<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Messaging\ReminderMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AppointmentReminderMail extends Mailable
{
    public function __construct(
        public readonly Appointment $appointment,
        public readonly User $physiotherapist,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ReminderMessage::subject($this->appointment, $this->physiotherapist));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.appointment-reminder',
            with: ['when' => ReminderMessage::when($this->appointment)],
        );
    }
}
