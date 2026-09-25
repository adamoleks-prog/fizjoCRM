<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\MeasurementTemplate;
use App\Models\Patient;
use App\Services\CollisionChecker;
use App\Services\MeasurementSync;
use App\Services\Messaging\ReminderSender;
use App\Services\PainPointSync;
use App\Services\PatientComorbiditySync;
use App\Services\SlotService;
use App\Services\TherapyCycleResolver;
use App\Services\TherapyMilestoneSync;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly CollisionChecker $collisionChecker,
        private readonly SlotService $slots,
        private readonly TherapyCycleResolver $therapyCycles,
        private readonly TherapyMilestoneSync $milestones,
        private readonly MeasurementSync $measurements,
        private readonly PatientComorbiditySync $comorbidities,
        private readonly PainPointSync $painPoints,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Appointment::class);

        return view('appointments.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Appointment::class);

        $date = $request->date('date') ?? $this->nextWorkingDay();
        $operatorId = $request->user()->isAdmin()
            ? $request->integer('operator_id') ?: null
            : $request->user()->id;

        return view('appointments.create', [
            'patients' => Patient::query()->orderBy('last_name')->get(),
            'selectedPatientId' => $request->integer('patient_id') ?: null,
            'date' => $date,
            'slots' => $operatorId ? $this->slots->daySlots($operatorId, $date) : collect(),
            'isWorkingDay' => $this->slots->isWorkingDay($date),
            'slotMinutes' => $this->slots->slotMinutes(),
            'maxDuration' => config('appointments.max_duration_minutes'),
        ]);
    }

    /**
     * Free slots for a given day — feeds the booking form when the date changes.
     */
    public function slots(Request $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $date = $request->date('date') ?? now();
        $operatorId = $request->user()->isAdmin()
            ? $request->integer('operator_id')
            : $request->user()->id;

        $slots = $this->slots->daySlots($operatorId, $date)
            ->map(fn (array $slot) => [
                'value' => $slot['starts_at']->format('Y-m-d H:i:s'),
                'label' => $slot['starts_at']->format('H:i'),
                'available' => $slot['available'],
                'max_duration' => $slot['available']
                    ? $this->slots->consecutiveFreeSlots($operatorId, $slot['starts_at']) * $this->slots->slotMinutes()
                    : 0,
            ]);

        return response()->json([
            'working_day' => $this->slots->isWorkingDay($date),
            'slots' => $slots,
        ]);
    }

    private function nextWorkingDay(): CarbonInterface
    {
        $date = now();

        while (! $this->slots->isWorkingDay($date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $appointment = DB::transaction(function () use ($request) {
            // Re-checked under a row lock: the request-level check is for the error
            // message, this one prevents two concurrent bookings taking one slot.
            if ($this->collisionChecker->hasCollision(
                $request->operatorId(),
                $request->startsAt(),
                $request->endsAt(),
                lock: true,
            )) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Ten termin koliduje z inną wizytą.',
                ]);
            }

            $appointment = new Appointment([
                'patient_id' => $request->integer('patient_id'),
                'starts_at' => $request->startsAt(),
                'ends_at' => $request->endsAt(),
                'status' => AppointmentStatus::Scheduled,
            ]);
            $appointment->operator_id = $request->operatorId();
            $appointment->save();

            return $appointment;
        });

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'Wizyta została umówiona.');
    }

    public function show(Appointment $appointment, ReminderSender $reminders): View
    {
        $this->authorize('view', $appointment);

        $appointment->load([
            'patient.comorbidities', 'documents', 'therapyCycle.milestones', 'measurements.template', 'painPoints', 'reminders',
        ]);

        return view('appointments.show', [
            'appointment' => $appointment,
            'reminderAvailable' => $reminders->isAvailableFor($appointment->patient),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $this->authorize('update', $appointment);

        return view('appointments.edit', [
            'appointment' => $appointment->load([
                'patient.comorbidities', 'therapyCycle.milestones', 'measurements.template', 'painPoints',
            ]),
            'therapyCycles' => $appointment->patient->therapyCycles()->latest()->get(),
            'measurementTemplates' => MeasurementTemplate::query()->orderBy('name')->get(),
            'slots' => $this->slots->daySlots($appointment->operator_id, $appointment->starts_at, $appointment->id),
            'slotMinutes' => $this->slots->slotMinutes(),
            'maxDuration' => config('appointments.max_duration_minutes'),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        DB::transaction(function () use ($request, $appointment) {
            if ($this->collisionChecker->hasCollision(
                $appointment->operator_id,
                $request->startsAt(),
                $request->endsAt(),
                $appointment->id,
                lock: true,
            )) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Ten termin koliduje z inną wizytą.',
                ]);
            }

            $cycle = $this->therapyCycles->resolve(
                $appointment,
                $request->integer('therapy_cycle_id') ?: null,
                $request->newTherapyCycleName(),
            );

            $appointment->update([
                ...$request->safe()->except([
                    'duration_minutes',
                    'therapy_cycle_id',
                    'new_therapy_cycle_name',
                    'therapy_plan',
                    'milestones',
                ]),
                'starts_at' => $request->startsAt(),
                'ends_at' => $request->endsAt(),
            ]);

            $appointment->therapy_cycle_id = $cycle?->id;
            $appointment->save();

            // The therapy plan lives on the cycle, so it can only be edited once the
            // visit belongs to one.
            if ($cycle) {
                $cycle->update(['therapy_plan' => $request->input('therapy_plan')]);
                $this->milestones->sync($cycle, $request->input('milestones', []));
            }

            $this->measurements->sync($appointment, $request->input('measurements', []));
            $this->painPoints->sync($appointment, $request->input('pain_points', []));

            // Collected during the interview, but stored on the patient — they hold
            // across every visit, not just this one.
            if ($request->has('comorbidities')) {
                $this->comorbidities->sync($appointment->patient, $request->input('comorbidities', []));
            }
        });

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'Wizyta została zaktualizowana.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Wizyta została usunięta.');
    }

    /**
     * JSON feed for the calendar view. Authorization happens through the operator
     * scope on the model, so an operator can only ever receive their own events.
     */
    public function calendarFeed(Request $request)
    {
        $this->authorize('viewAny', Appointment::class);

        return Appointment::query()
            ->with('patient')
            ->when($request->date('start'), fn ($query, $start) => $query->where('ends_at', '>=', $start))
            ->when($request->date('end'), fn ($query, $end) => $query->where('starts_at', '<=', $end))
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'title' => $appointment->patient->last_name.' '.$appointment->patient->first_name,
                'start' => $appointment->starts_at->toIso8601String(),
                'end' => $appointment->ends_at->toIso8601String(),
                'url' => route('appointments.show', $appointment),
                'className' => 'status-'.$appointment->status->value,
            ]);
    }
}
