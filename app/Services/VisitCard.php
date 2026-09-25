<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The one-page summary a patient takes home: what was done, what to do, when
 * the next visit is. Deliberately leaves out the interview, examination and
 * internal notes — those are the physiotherapist's working record.
 */
class VisitCard
{
    public function pdf(Appointment $appointment): string
    {
        $patient = $appointment->patient()->withoutGlobalScope(OperatorScope::class)->firstOrFail();

        $nextVisits = Appointment::withoutGlobalScope(OperatorScope::class)
            ->where('patient_id', $patient->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->where('starts_at', '>', max($appointment->starts_at, now()))
            ->orderBy('starts_at')
            ->limit(3)
            ->get();

        return Pdf::loadView('pdf.visit-card', [
            'appointment' => $appointment->loadMissing('icd10'),
            'patient' => $patient,
            'physiotherapist' => User::findOrFail($appointment->operator_id),
            'nextVisits' => $nextVisits,
        ])->setPaper('a4')->output();
    }

    public function filename(Appointment $appointment): string
    {
        return 'karta-wizyty-'.$appointment->starts_at->format('Y-m-d').'.pdf';
    }
}
