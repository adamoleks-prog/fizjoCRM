<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\TherapyCycle;

class TherapyCycleResolver
{
    /**
     * Picks the chosen cycle or opens a new one for the appointment's patient.
     *
     * A null $newCycleName means no cycle was chosen at all; an empty string means
     * "start a new cycle" without the physiotherapist naming it.
     */
    public function resolve(Appointment $appointment, ?int $therapyCycleId, ?string $newCycleName): ?TherapyCycle
    {
        if ($therapyCycleId) {
            return TherapyCycle::find($therapyCycleId);
        }

        if ($newCycleName === null) {
            return null;
        }

        $cycle = new TherapyCycle([
            'patient_id' => $appointment->patient_id,
            'name' => $newCycleName !== '' ? $newCycleName : 'Cykl od '.now()->format('d.m.Y'),
        ]);
        $cycle->operator_id = $appointment->operator_id;
        $cycle->save();

        return $cycle;
    }
}
