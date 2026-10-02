<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Working hours: a weekly pattern repeated week after week, and single days that
 * differ from it. Each physiotherapist edits their own; an admin may edit anyone's.
 */
class WorkScheduleController extends Controller
{
    private const WEEKS_AHEAD = 4;

    public function __construct(private readonly WorkSchedule $schedule) {}

    public function edit(Request $request): View
    {
        $operator = $this->operator($request);
        $editDate = $request->filled('date') ? Carbon::parse($request->string('date')->value())->startOfDay() : null;

        $days = collect();
        $day = now()->startOfWeek();

        for ($i = 0; $i < self::WEEKS_AHEAD * 7; $i++) {
            $days->push([
                'date' => $day->copy(),
                'periods' => $this->schedule->intervals($operator->id, $day),
                'exception' => $this->schedule->isException($operator->id, $day),
            ]);
            $day->addDay();
        }

        return view('schedule.edit', [
            'operator' => $operator,
            'operators' => $request->user()->isAdmin() ? User::orderBy('name')->get() : collect(),
            'pattern' => $this->schedule->pattern($operator->id),
            'hasOwnPattern' => $this->schedule->hasOwnPattern($operator->id),
            'slotMinutes' => $this->schedule->slotMinutes($operator->id),
            'days' => $days,
            'editDate' => $editDate,
            'editPeriods' => $editDate ? $this->schedule->intervals($operator->id, $editDate) : collect(),
            'editIsException' => $editDate && $this->schedule->isException($operator->id, $editDate),
            'editAppointments' => $editDate ? $this->appointmentsOn($operator->id, $editDate) : collect(),
        ]);
    }

    public function updatePattern(Request $request): RedirectResponse
    {
        $operator = $this->operator($request);

        $request->validate([
            'slot_minutes' => ['required', 'integer', Rule::in(WorkSchedule::SLOT_CHOICES)],
            'pattern' => ['nullable', 'array'],
            'pattern.*' => ['array', 'max:4'],
            'pattern.*.*.start' => ['required', 'date_format:H:i'],
            'pattern.*.*.end' => ['required', 'date_format:H:i'],
        ]);

        $pattern = [];

        foreach ($request->input('pattern', []) as $weekday => $periods) {
            if (! array_key_exists((int) $weekday, WorkSchedule::WEEKDAYS) || ! $request->boolean("works.{$weekday}")) {
                continue;
            }

            $pattern[(int) $weekday] = $this->periods($periods, "pattern.{$weekday}");
        }

        $this->schedule->savePattern($operator->id, $pattern, $request->integer('slot_minutes'));

        return redirect()
            ->route('schedule.edit', $this->keep($request, $operator))
            ->with('status', 'Zapisano tygodniowy czas pracy. Obowiązuje we wszystkich kolejnych tygodniach, poza dniami zmienionymi pojedynczo.');
    }

    public function saveDay(Request $request): RedirectResponse
    {
        $operator = $this->operator($request);

        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'day_off' => ['boolean'],
            'periods' => ['nullable', 'array', 'max:4'],
            'periods.*.start' => ['required', 'date_format:H:i'],
            'periods.*.end' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        $date = Carbon::parse($request->string('date')->value());
        $periods = $request->boolean('day_off') ? [] : $this->periods($request->input('periods', []), 'periods');

        $this->schedule->saveException($operator->id, $date, $periods, $request->input('note'));

        $outside = $this->appointmentsOutside($operator->id, $date);

        return redirect()
            ->route('schedule.edit', $this->keep($request, $operator))
            ->with('status', 'Zapisano godziny na '.$date->format('d.m.Y').'.'
                .($outside ? " Uwaga: {$outside} umówionych wizyt wypada poza nowymi godzinami — nie zostały odwołane." : ''));
    }

    public function resetDay(Request $request): RedirectResponse
    {
        $operator = $this->operator($request);
        $request->validate(['date' => ['required', 'date']]);

        $this->schedule->clearException($operator->id, Carbon::parse($request->string('date')->value()));

        return redirect()
            ->route('schedule.edit', $this->keep($request, $operator))
            ->with('status', 'Przywrócono standardowe godziny dla tego dnia.');
    }

    /**
     * Sorted [start, end] pairs; rejects an end before its start and overlaps.
     *
     * @param  array<int, array{start: string, end: string}>  $rows
     * @return array<int, array{0: string, 1: string}>
     */
    private function periods(array $rows, string $field): array
    {
        $periods = collect($rows)
            ->map(fn (array $row) => [$row['start'], $row['end']])
            ->sortBy(0)
            ->values();

        foreach ($periods as $i => [$start, $end]) {
            if ($end <= $start) {
                throw ValidationException::withMessages([$field => 'Godzina zakończenia musi być późniejsza niż rozpoczęcia ('.$start.'–'.$end.').']);
            }

            if ($i > 0 && $start < $periods[$i - 1][1]) {
                throw ValidationException::withMessages([$field => 'Przedziały godzin nakładają się na siebie.']);
            }
        }

        return $periods->all();
    }

    private function appointmentsOn(int $operatorId, Carbon $date)
    {
        return Appointment::withoutGlobalScopes()
            ->with('patient')
            ->where('operator_id', $operatorId)
            ->where('status', AppointmentStatus::Scheduled)
            ->whereBetween('starts_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();
    }

    private function appointmentsOutside(int $operatorId, Carbon $date): int
    {
        $periods = $this->schedule->intervals($operatorId, $date);

        return $this->appointmentsOn($operatorId, $date)
            ->reject(fn (Appointment $a) => $periods->contains(fn ($p) => $a->starts_at->gte($p[0]) && $a->ends_at->lte($p[1])))
            ->count();
    }

    private function operator(Request $request): User
    {
        $user = $request->user();

        if ($user->isAdmin() && $request->filled('operator')) {
            return User::findOrFail($request->integer('operator'));
        }

        return $user;
    }

    /**
     * @return array<string, int>
     */
    private function keep(Request $request, User $operator): array
    {
        return $operator->id !== $request->user()->id ? ['operator' => $operator->id] : [];
    }
}
