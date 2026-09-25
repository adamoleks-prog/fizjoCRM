<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\TherapyCycle;
use App\Services\TherapySuggestion\ClinicalCaseService;
use Illuminate\View\View;

class TherapyCycleController extends Controller
{
    public function __construct(private readonly ClinicalCaseService $cases) {}

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
            'completedVisits' => $therapyCycle->appointments()->where('status', AppointmentStatus::Completed)->count(),
            'recommendations' => $recommendations,
            'hasQueued' => $recommendations->contains(fn ($r) => $r->isQueued()),
            'enabled' => (bool) config('services.openrouter.enabled'),
        ]);
    }
}
