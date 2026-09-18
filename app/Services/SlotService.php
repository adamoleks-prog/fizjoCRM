<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Scopes\OperatorScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SlotService
{
    public function slotMinutes(): int
    {
        return (int) config('appointments.slot_minutes');
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), config('appointments.working_days'), true);
    }

    /**
     * Whether the moment sits exactly on a slot boundary counted from the start
     * of the working day (e.g. 08:00, 08:30, 09:00 for 30-minute slots).
     */
    public function isOnSlotBoundary(CarbonInterface $moment): bool
    {
        $dayStart = $this->dayStart($moment);

        return $dayStart->diffInMinutes($moment, absolute: true) % $this->slotMinutes() === 0;
    }

    /**
     * Every slot of the working day, each flagged as free or taken.
     *
     * @return Collection<int, array{starts_at: Carbon, ends_at: Carbon, available: bool}>
     */
    public function daySlots(int $operatorId, CarbonInterface $date, ?int $ignoreAppointmentId = null): Collection
    {
        if (! $this->isWorkingDay($date)) {
            return collect();
        }

        $taken = $this->takenRanges($operatorId, $date, $ignoreAppointmentId);

        $slots = collect();
        $cursor = $this->dayStart($date);
        $dayEnd = $this->dayEnd($date);

        while ($cursor->lt($dayEnd)) {
            $slotEnd = $cursor->copy()->addMinutes($this->slotMinutes());

            $slots->push([
                'starts_at' => $cursor->copy(),
                'ends_at' => $slotEnd,
                'available' => ! $this->overlapsAny($cursor, $slotEnd, $taken),
            ]);

            $cursor = $slotEnd;
        }

        return $slots;
    }

    /**
     * Free slots only — what the booking form offers.
     *
     * @return Collection<int, array{starts_at: Carbon, ends_at: Carbon, available: bool}>
     */
    public function availableSlots(int $operatorId, CarbonInterface $date, ?int $ignoreAppointmentId = null): Collection
    {
        return $this->daySlots($operatorId, $date, $ignoreAppointmentId)
            ->where('available', true)
            ->values();
    }

    /**
     * How many consecutive free slots start at the given moment — caps the
     * duration the form may offer so a long visit cannot swallow a booked slot.
     */
    public function consecutiveFreeSlots(int $operatorId, CarbonInterface $startsAt): int
    {
        $slots = $this->daySlots($operatorId, $startsAt);
        $index = $slots->search(fn (array $slot) => $slot['starts_at']->equalTo($startsAt));

        if ($index === false) {
            return 0;
        }

        $count = 0;

        foreach ($slots->slice($index) as $slot) {
            if (! $slot['available']) {
                break;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function takenRanges(int $operatorId, CarbonInterface $date, ?int $ignoreAppointmentId): Collection
    {
        return Appointment::query()
            ->withoutGlobalScope(OperatorScope::class)
            ->where('operator_id', $operatorId)
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->whereBetween('starts_at', [$this->dayStart($date), $this->dayEnd($date)])
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $a) => [$a->starts_at, $a->ends_at]);
    }

    private function overlapsAny(CarbonInterface $start, CarbonInterface $end, Collection $ranges): bool
    {
        return $ranges->contains(fn (array $range) => $range[0]->lt($end) && $range[1]->gt($start));
    }

    private function dayStart(CarbonInterface $date): Carbon
    {
        [$hour, $minute] = explode(':', config('appointments.working_hours.start'));

        return Carbon::parse($date)->setTime((int) $hour, (int) $minute);
    }

    private function dayEnd(CarbonInterface $date): Carbon
    {
        [$hour, $minute] = explode(':', config('appointments.working_hours.end'));

        return Carbon::parse($date)->setTime((int) $hour, (int) $minute);
    }
}
