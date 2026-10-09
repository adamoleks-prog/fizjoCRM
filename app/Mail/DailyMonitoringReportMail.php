<?php

namespace App\Mail;

use App\Models\AppError;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DailyMonitoringReportMail extends Mailable
{
    /**
     * @param  list<array{label: string, state: string, detail: string}>  $checks
     * @param  array{byType: \Illuminate\Support\Collection<int, object>, topIps: \Illuminate\Support\Collection<int, object>, total: int}  $security
     * @param  Collection<int, AppError>  $appErrors
     */
    public function __construct(
        public readonly array $checks,
        public readonly array $security,
        public readonly Collection $appErrors,
    ) {}

    public function envelope(): Envelope
    {
        $problems = collect($this->checks)->where('state', '!=', 'ok')->count();

        return new Envelope(subject: '['.config('app.name').'] Raport dzienny '.now()->format('d.m.Y').' — '
            .($problems === 0 ? 'wszystko w porządku' : "do sprawdzenia: {$problems}"));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.daily-monitoring-report');
    }
}
