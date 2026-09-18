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

    public function updated(Appointment $appointment): void
    {
        SyncAppointmentToGoogleCalendar::dispatch($appointment->id);
    }

    public function deleted(Appointment $appointment): void
    {
        SyncAppointmentToGoogleCalendar::dispatch($appointment->id, deleted: true);
    }
}
