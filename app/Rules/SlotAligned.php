<?php

namespace App\Rules;

use App\Services\SlotService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The start of a visit must fall on a slot of the physiotherapist who runs it.
 */
class SlotAligned implements ValidationRule
{
    public function __construct(
        private readonly SlotService $slots,
        private readonly int $operatorId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $moment = Carbon::parse($value);

        if (! $this->slots->isWorkingDay($this->operatorId, $moment)) {
            $fail('Ten dzień jest poza godzinami pracy fizjoterapeuty.');

            return;
        }

        if (! $this->slots->isOnSlotBoundary($this->operatorId, $moment)) {
            $fail('Wizyty można umawiać tylko w godzinach pracy, w slotach co '.$this->slots->slotMinutes($this->operatorId).' minut.');
        }
    }
}
