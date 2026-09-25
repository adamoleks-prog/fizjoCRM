<?php

namespace App\Observers;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;

class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        SyncAppointmentToGoogleCalendar::dispatch($appointment->id);
    }

    /** A reminder was for the old time — the new one deserves its own. */
    public function updating(Appointment $appointment): void
    {
        if ($appointment->isDirty('starts_at')) {
            $appointment->reminder_sent_at = null;
        }
    }

    public function updated(Appointment $appointment): void
    {
        SyncAppointmentToGoogleCalendar::dispatch($appointment->id);
    }

    public function deleted(Appointment $appointment): void
    {
        SyncAppointmentToGoogleCalendar::dispatch($appointment->id, deleted: true);
    }
}
