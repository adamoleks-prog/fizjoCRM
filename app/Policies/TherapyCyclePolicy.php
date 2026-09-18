<?php

namespace App\Policies;

use App\Models\TherapyCycle;
use App\Models\User;

class TherapyCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TherapyCycle $cycle): bool
    {
        return $this->owns($user, $cycle);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TherapyCycle $cycle): bool
    {
        return $this->owns($user, $cycle);
    }

    public function delete(User $user, TherapyCycle $cycle): bool
    {
        return $this->owns($user, $cycle);
    }

    private function owns(User $user, TherapyCycle $cycle): bool
    {
        return $user->isAdmin() || $cycle->operator_id === $user->id;
    }
}
