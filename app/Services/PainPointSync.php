<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PainPoint;

class PainPointSync
{
    /**
     * Replaces the marks recorded on this visit.
     *
     * Like measurements, a mark is a snapshot of one visit and carries no state
     * worth matching across an edit, so a plain replace is enough.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(Appointment $appointment, array $rows): void
    {
        $appointment->painPoints()->delete();

        foreach ($rows as $row) {
            if (! isset($row['position_x'], $row['position_y'], $row['body_view'])) {
                continue;
            }

            $point = new PainPoint([
                'body_view' => $row['body_view'],
                'position_x' => $this->clamp($row['position_x']),
                'position_y' => $this->clamp($row['position_y']),
                'note' => $row['note'] ?? null,
            ]);
            $point->operator_id = $appointment->operator_id;
            $point->appointment_id = $appointment->id;
            $point->save();
        }
    }

    private function clamp(mixed $value): float
    {
        return max(0, min(100, (float) $value));
    }
}
