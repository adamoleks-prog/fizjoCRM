<?php

namespace App\Services\Encryption;

use RuntimeException;

class PatientDataErased extends RuntimeException
{
    public function __construct(public readonly int $patientId)
    {
        parent::__construct("Dane pacjenta #{$patientId} zostały trwale usunięte.");
    }
}
