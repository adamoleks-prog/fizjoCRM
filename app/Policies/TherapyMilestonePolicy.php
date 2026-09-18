<?php

namespace App\Policies;

use App\Models\TherapyMilestone;
use App\Models\User;

class TherapyMilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TherapyMilestone $milestone): bool
    {
        return $this->owns($user, $milestone);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TherapyMilestone $milestone): bool
    {
        return $this->owns($user, $milestone);
    }

    public function delete(User $user, TherapyMilestone $milestone): bool
    {
        return $this->owns($user, $milestone);
    }

    private function owns(User $user, TherapyMilestone $milestone): bool
    {
        return $user->isAdmin() || $milestone->operator_id === $user->id;
    }
}
