<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMessageMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test poczty — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>To jest wiadomość testowa z panelu. Poczta działa.</p>');
    }
}
