<?php

namespace App\Policies;

use App\Models\PainPoint;
use App\Models\User;

class PainPointPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PainPoint $point): bool
    {
        return $this->owns($user, $point);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PainPoint $point): bool
    {
        return $this->owns($user, $point);
    }

    public function delete(User $user, PainPoint $point): bool
    {
        return $this->owns($user, $point);
    }

    private function owns(User $user, PainPoint $point): bool
    {
        return $user->isAdmin() || $point->operator_id === $user->id;
    }
}
