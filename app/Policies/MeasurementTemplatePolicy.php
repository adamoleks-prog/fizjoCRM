<?php

namespace App\Policies;

use App\Models\MeasurementTemplate;
use App\Models\User;

class MeasurementTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeasurementTemplate $template): bool
    {
        return $this->owns($user, $template);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MeasurementTemplate $template): bool
    {
        return $this->owns($user, $template);
    }

    public function delete(User $user, MeasurementTemplate $template): bool
    {
        return $this->owns($user, $template);
    }

    private function owns(User $user, MeasurementTemplate $template): bool
    {
        return $user->isAdmin() || $template->operator_id === $user->id;
    }
}
