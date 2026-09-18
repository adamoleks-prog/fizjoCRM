<?php

namespace App\Services\GoogleCalendar;

use App\Models\Appointment;

interface CalendarSynchronizer
{
    /**
     * Create or update the Google Calendar event for the appointment.
     *
     * @return string|null The Google event id, or null when the operator has no connected calendar.
     */
    public function upsertEvent(Appointment $appointment): ?string;

    public function deleteEvent(Appointment $appointment): void;
}
