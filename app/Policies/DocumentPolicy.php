<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Scopes\OperatorScope;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, Document $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Resolved without the operator scope so a foreign patient yields the real
        // owner id to compare against, rather than being silently filtered to null.
        $patient = $document->patient()->withoutGlobalScope(OperatorScope::class)->first();

        return $patient?->operator_id === $user->id;
    }
}
