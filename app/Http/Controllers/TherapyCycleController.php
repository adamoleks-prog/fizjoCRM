<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\TherapyCycle;
use App\Services\TherapyCycleResolver;
use App\Services\TherapySuggestion\ClinicalCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TherapyCycleController extends Controller
{
    public function __construct(private readonly ClinicalCaseService $cases) {}

    /** Every cycle the user may see, the most recently active first. */
    public function index(): View
    {
        $cycles = TherapyCycle::query()
            ->with(['patient', 'clinicalCase'])
            ->withCount('recommendations')
            ->withMax('appointments', 'starts_at')
            ->orderByDesc('appointments_max_starts_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('therapy-cycles.index', ['cycles' => $cycles]);
    }

    /**
     * Opens the assistant for the visit at hand — during the visit, straight after
     * the interview. A visit without a cycle gets a new one, as it would when the
     * physiotherapist picks "+ Nowy cykl" in the visit form.
     */
    public function fromAppointment(Appointment $appointment, TherapyCycleResolver $resolver): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $cycle = $appointment->therapyCycle;

        if (! $cycle) {
            $cycle = $resolver->resolve($appointment, null, '');
            $appointment->therapy_cycle_id = $cycle->id;
            $appointment->saveQuietly();
        }

        return redirect()->route('therapy-cycles.show', $cycle);
    }

    /** The therapy assistant page of a cycle: the case to review and the suggestions so far. */
    public function show(TherapyCycle $therapyCycle): View
    {
        $this->authorize('view', $therapyCycle);

        $therapyCycle->load(['patient', 'clinicalCase.approvedBy']);
        $case = $therapyCycle->clinicalCase;
        $recommendations = $therapyCycle->recommendations()->with('requestedBy')->get();

        return view('therapy-cycles.show', [
            'cycle' => $therapyCycle,
            'case' => $case,
            'stale' => $case ? $this->cases->isStale($case, $therapyCycle) : false,
            'visits' => $therapyCycle->appointments()->get(),
            'recommendations' => $recommendations,
            'hasQueued' => $recommendations->contains(fn ($r) => $r->isQueued()),
            'enabled' => (bool) config('services.openrouter.enabled'),
        ]);
    }
}
