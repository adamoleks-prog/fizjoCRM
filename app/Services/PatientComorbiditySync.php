<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientComorbidity;

class PatientComorbiditySync
{
    /**
     * Applies the submitted rows to the patient: updates rows sent with an id,
     * creates the rest, removes the ones no longer present.
     *
     * @param  array<int, array{id?: int|string|null, name?: string|null, kind?: string|null}>  $rows
     */
    public function sync(Patient $patient, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $comorbidity = isset($row['id']) && $row['id']
                ? $patient->comorbidities()->whereKey($row['id'])->first()
                : null;

            if ($comorbidity) {
                $comorbidity->update(['name' => $name, 'kind' => $row['kind']]);
            } else {
                $comorbidity = new PatientComorbidity(['name' => $name, 'kind' => $row['kind']]);
                $comorbidity->operator_id = $patient->operator_id;
                $comorbidity->patient_id = $patient->id;
                $comorbidity->save();
            }

            $keptIds[] = $comorbidity->id;
        }

        $patient->comorbidities()->whereKeyNot($keptIds)->delete();
    }
}
