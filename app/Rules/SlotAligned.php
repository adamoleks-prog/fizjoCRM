<?php

namespace App\Rules;

use App\Services\SlotService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SlotAligned implements ValidationRule
{
    public function __construct(private readonly SlotService $slots) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $moment = Carbon::parse($value);

        if (! $this->slots->isWorkingDay($moment)) {
            $fail('Wybrany dzień jest poza dniami pracy gabinetu.');

            return;
        }

        if (! $this->slots->isOnSlotBoundary($moment)) {
            $fail('Wizyty można umawiać tylko w slotach co '.$this->slots->slotMinutes().' minut.');
        }
    }
}
