<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Scopes\OperatorScope;
use Carbon\CarbonInterface;

class CollisionChecker
{
    /**
     * Whether the operator already has an appointment overlapping the given window.
     *
     * Half-open interval comparison: touching appointments (one ends exactly when
     * the next starts) do not count as a collision.
     *
     * @param  bool  $lock  Take a row lock — required when called inside the booking transaction.
     */
    public function hasCollision(
        int $operatorId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $ignoreAppointmentId = null,
        bool $lock = false,
    ): bool {
        $query = Appointment::query()
            ->withoutGlobalScope(OperatorScope::class)
            ->where('operator_id', $operatorId)
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->when($lock, fn ($query) => $query->lockForUpdate());

        return $query->exists();
    }
}
