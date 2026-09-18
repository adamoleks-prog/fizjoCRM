<?php

namespace App\Services;

use App\Enums\MeasurementType;
use App\Models\Appointment;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;

class MeasurementSync
{
    /**
     * Replaces the visit's measurements with the submitted rows.
     *
     * Unlike milestones these carry no state worth preserving across an edit —
     * a measurement is just the value read on this visit — so a plain replace
     * keeps the code simple.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(Appointment $appointment, array $rows): void
    {
        $appointment->measurements()->delete();

        foreach ($rows as $row) {
            $template = MeasurementTemplate::find($row['measurement_template_id'] ?? null);

            if (! $template) {
                continue;
            }

            $values = $this->values($template->type, $row);

            if ($values === null) {
                continue;
            }

            $measurement = new Measurement([
                ...$values,
                'note' => $row['note'] ?? null,
            ]);
            $measurement->operator_id = $appointment->operator_id;
            $measurement->appointment_id = $appointment->id;
            $measurement->measurement_template_id = $template->id;
            $measurement->save();
        }
    }

    /**
     * Returns null when the row carries no reading at all, so empty rows left in
     * the form are simply dropped.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function values(MeasurementType $type, array $row): ?array
    {
        $left = $this->number($row['value_left'] ?? null);
        $right = $this->number($row['value_right'] ?? null);

        return match ($type) {
            MeasurementType::Boolean => isset($row['value_boolean']) && $row['value_boolean'] !== ''
                ? ['value_boolean' => filter_var($row['value_boolean'], FILTER_VALIDATE_BOOLEAN)]
                : null,
            MeasurementType::Scale, MeasurementType::Numeric => $left === null
                ? null
                : ['value_left' => $left],
            MeasurementType::Bilateral => $left === null && $right === null
                ? null
                : ['value_left' => $left, 'value_right' => $right],
        };
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
