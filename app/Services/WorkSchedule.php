<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * When a physiotherapist works: a weekly pattern repeated week after week, with
 * single dates overridden. Someone who never set a pattern works the practice
 * default from config/appointments.php, so nothing changes until they do.
 */
class WorkSchedule
{
    public const WEEKDAYS = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];

    public const SLOT_CHOICES = [15, 20, 30, 45, 60];

    /** @var array<int, array<int, array<int, array{0: string, 1: string}>>|null> per operator */
    private array $patterns = [];

    public function slotMinutes(int $operatorId): int
    {
        $own = DB::table('users')->where('id', $operatorId)->value('slot_minutes');

        return (int) ($own ?: config('appointments.slot_minutes'));
    }

    /**
     * Working periods on the given date, in order.
     *
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    public function intervals(int $operatorId, CarbonInterface $date): Collection
    {
        $day = Carbon::parse($date)->startOfDay();

        $exceptions = DB::table('work_hour_exceptions')
            ->where('operator_id', $operatorId)
            ->whereDate('date', $day->toDateString())
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        $times = $exceptions->isNotEmpty()
            ? $exceptions->filter(fn ($row) => $row->starts_at !== null)->map(fn ($row) => [$row->starts_at, $row->ends_at])->values()->all()
            : ($this->pattern($operatorId)[$day->isoWeekday()] ?? []);

        return collect($times)->map(fn (array $t) => [
            $day->copy()->setTimeFromTimeString($t[0]),
            $day->copy()->setTimeFromTimeString($t[1]),
        ])->values();
    }

    public function isException(int $operatorId, CarbonInterface $date): bool
    {
        return DB::table('work_hour_exceptions')
            ->where('operator_id', $operatorId)
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->exists();
    }

    /**
     * The weekly pattern as weekday => list of [start, end] in H:i.
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    public function pattern(int $operatorId): array
    {
        if (isset($this->patterns[$operatorId])) {
            return $this->patterns[$operatorId];
        }

        $rows = DB::table('work_hours')
            ->where('operator_id', $operatorId)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get(['weekday', 'starts_at', 'ends_at']);

        if ($rows->isEmpty()) {
            return $this->patterns[$operatorId] = $this->defaultPattern();
        }

        $pattern = [];

        foreach ($rows as $row) {
            $pattern[(int) $row->weekday][] = [substr($row->starts_at, 0, 5), substr($row->ends_at, 0, 5)];
        }

        return $this->patterns[$operatorId] = $pattern;
    }

    public function hasOwnPattern(int $operatorId): bool
    {
        return DB::table('work_hours')->where('operator_id', $operatorId)->exists();
    }

    /**
     * @param  array<int, array<int, array{0: string, 1: string}>>  $pattern
     */
    public function savePattern(int $operatorId, array $pattern, int $slotMinutes): void
    {
        DB::transaction(function () use ($operatorId, $pattern, $slotMinutes) {
            DB::table('work_hours')->where('operator_id', $operatorId)->delete();

            foreach ($pattern as $weekday => $periods) {
                foreach ($periods as [$start, $end]) {
                    DB::table('work_hours')->insert([
                        'operator_id' => $operatorId,
                        'weekday' => $weekday,
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('users')->where('id', $operatorId)->update(['slot_minutes' => $slotMinutes]);
        });

        unset($this->patterns[$operatorId]);
    }

    /**
     * Replaces the hours of one date. An empty list marks a day off.
     *
     * @param  array<int, array{0: string, 1: string}>  $periods
     */
    public function saveException(int $operatorId, CarbonInterface $date, array $periods, ?string $note): void
    {
        $day = Carbon::parse($date)->toDateString();

        DB::transaction(function () use ($operatorId, $day, $periods, $note) {
            DB::table('work_hour_exceptions')->where('operator_id', $operatorId)->whereDate('date', $day)->delete();

            $rows = $periods === [] ? [[null, null]] : $periods;

            foreach ($rows as [$start, $end]) {
                DB::table('work_hour_exceptions')->insert([
                    'operator_id' => $operatorId,
                    'date' => $day,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'note' => $note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /** Back to the weekly pattern for that date. */
    public function clearException(int $operatorId, CarbonInterface $date): void
    {
        DB::table('work_hour_exceptions')
            ->where('operator_id', $operatorId)
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->delete();
    }

    /**
     * Earliest start and latest end over the week — bounds of the calendar grid.
     *
     * @return array{0: string, 1: string}
     */
    public function weekBounds(int $operatorId): array
    {
        $periods = collect($this->pattern($operatorId))->flatten(1);

        if ($periods->isEmpty()) {
            return [config('appointments.working_hours.start'), config('appointments.working_hours.end')];
        }

        return [$periods->min(0), $periods->max(1)];
    }

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function defaultPattern(): array
    {
        $hours = [config('appointments.working_hours.start'), config('appointments.working_hours.end')];

        return collect(config('appointments.working_days'))
            ->mapWithKeys(fn (int $weekday) => [$weekday => [$hours]])
            ->all();
    }
}
