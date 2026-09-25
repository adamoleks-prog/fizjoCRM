<?php

namespace App\Services\Anonymization;

/**
 * Shared by every model that holds text waiting for a person's approval before it
 * may leave the server. The columns it relies on are listed in ReviewGate.
 */
trait HasReviewState
{
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** Nothing may be sent until a person has looked at it. */
    public function mayBeSent(): bool
    {
        return $this->isApproved();
    }
}
