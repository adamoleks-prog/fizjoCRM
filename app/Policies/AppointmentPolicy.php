<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    private function owns(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin() || $appointment->operator_id === $user->id;
    }
}
