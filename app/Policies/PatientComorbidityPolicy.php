<?php

namespace App\Policies;

use App\Models\PatientComorbidity;
use App\Models\User;

class PatientComorbidityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PatientComorbidity $comorbidity): bool
    {
        return $this->owns($user, $comorbidity);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PatientComorbidity $comorbidity): bool
    {
        return $this->owns($user, $comorbidity);
    }

    public function delete(User $user, PatientComorbidity $comorbidity): bool
    {
        return $this->owns($user, $comorbidity);
    }

    private function owns(User $user, PatientComorbidity $comorbidity): bool
    {
        return $user->isAdmin() || $comorbidity->operator_id === $user->id;
    }
}
