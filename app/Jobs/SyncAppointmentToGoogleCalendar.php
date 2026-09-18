<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendar\CalendarSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAppointmentToGoogleCalendar implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $appointmentId,
        public readonly bool $deleted = false,
    ) {}

    public function handle(CalendarSynchronizer $synchronizer): void
    {
        $appointment = Appointment::withoutGlobalScopes()
            ->withTrashed()
            ->with(['operator.googleCalendarToken', 'patient'])
            ->find($this->appointmentId);

        if (! $appointment || ! $appointment->operator->googleCalendarToken) {
            return;
        }

        if ($this->deleted) {
            $synchronizer->deleteEvent($appointment);

            return;
        }

        $eventId = $synchronizer->upsertEvent($appointment);

        if ($eventId && $eventId !== $appointment->google_event_id) {
            // forceFill + saveQuietly: the column is system-managed (not fillable)
            // and must not re-trigger the observer that dispatched this job.
            $appointment->forceFill(['google_event_id' => $eventId])->saveQuietly();
        }
    }
}
