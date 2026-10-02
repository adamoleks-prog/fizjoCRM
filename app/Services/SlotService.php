<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Scopes\OperatorScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Bookable slots of one physiotherapist, cut from their working hours.
 */
class SlotService
{
    public function __construct(private readonly WorkSchedule $schedule) {}

    public function slotMinutes(int $operatorId): int
    {
        return $this->schedule->slotMinutes($operatorId);
    }

    public function isWorkingDay(int $operatorId, CarbonInterface $date): bool
    {
        return $this->schedule->intervals($operatorId, $date)->isNotEmpty();
    }

    /**
     * Whether the moment is the start of a slot: inside a working period and a
     * whole number of slots after that period's start (08:00, 08:30, … for
     * 30-minute slots).
     */
    public function isOnSlotBoundary(int $operatorId, CarbonInterface $moment): bool
    {
        $slot = $this->slotMinutes($operatorId);

        return $this->schedule->intervals($operatorId, $moment)->contains(
            fn (array $period) => $moment->gte($period[0])
                && $moment->lt($period[1])
                && $period[0]->diffInMinutes($moment, absolute: true) % $slot === 0,
        );
    }

    /**
     * Every slot of the working day, each flagged as free or taken.
     *
     * @return Collection<int, array{starts_at: Carbon, ends_at: Carbon, available: bool}>
     */
    public function daySlots(int $operatorId, CarbonInterface $date, ?int $ignoreAppointmentId = null): Collection
    {
        $periods = $this->schedule->intervals($operatorId, $date);

        if ($periods->isEmpty()) {
            return collect();
        }

        $taken = $this->takenRanges($operatorId, $date, $ignoreAppointmentId);
        $length = $this->slotMinutes($operatorId);
        $slots = collect();

        foreach ($periods as [$start, $end]) {
            $cursor = $start->copy();

            // A slot that would run past the end of the period is not offered.
            while ($cursor->copy()->addMinutes($length)->lte($end)) {
                $slotEnd = $cursor->copy()->addMinutes($length);

                $slots->push([
                    'starts_at' => $cursor->copy(),
                    'ends_at' => $slotEnd,
                    'available' => ! $this->overlapsAny($cursor, $slotEnd, $taken),
                ]);

                $cursor = $slotEnd;
            }
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
     * duration the form may offer so a long visit cannot swallow a booked slot
     * or run into a break.
     */
    public function consecutiveFreeSlots(int $operatorId, CarbonInterface $startsAt): int
    {
        $slots = $this->daySlots($operatorId, $startsAt);
        $index = $slots->search(fn (array $slot) => $slot['starts_at']->equalTo($startsAt));

        if ($index === false) {
            return 0;
        }

        $count = 0;
        $previousEnd = null;

        foreach ($slots->slice($index) as $slot) {
            if (! $slot['available'] || ($previousEnd && ! $previousEnd->equalTo($slot['starts_at']))) {
                break;
            }

            $count++;
            $previousEnd = $slot['ends_at'];
        }

        return $count;
    }

    /** The first day from $from on with any working hours, within the next two months. */
    public function nextWorkingDay(int $operatorId, CarbonInterface $from): Carbon
    {
        $date = Carbon::parse($from);

        for ($i = 0; $i < 62; $i++) {
            if ($this->isWorkingDay($operatorId, $date)) {
                return $date;
            }

            $date = $date->addDay();
        }

        return Carbon::parse($from);
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
            ->where('starts_at', '<', Carbon::parse($date)->endOfDay())
            ->where('ends_at', '>', Carbon::parse($date)->startOfDay())
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $a) => [$a->starts_at, $a->ends_at]);
    }

    private function overlapsAny(CarbonInterface $start, CarbonInterface $end, Collection $ranges): bool
    {
        return $ranges->contains(fn (array $range) => $range[0]->lt($end) && $range[1]->gt($start));
    }
}
