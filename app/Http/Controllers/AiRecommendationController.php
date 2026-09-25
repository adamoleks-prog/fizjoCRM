<?php

namespace App\Http\Controllers;

use App\Enums\PatientAccessAction;
use App\Jobs\RequestTherapySuggestion;
use App\Models\AiRecommendation;
use App\Models\Scopes\OperatorScope;
use App\Models\TherapyCycle;
use App\Services\AuditLogService;
use App\Services\TherapySuggestion\ClinicalCaseService;
use App\Services\TherapySuggestion\SuggestionPrompt;
use App\Services\TherapySuggestion\SuggestionSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiRecommendationController extends Controller
{
    public function __construct(private readonly ClinicalCaseService $cases) {}

    /**
     * Queues one suggestion. The job checks everything again right before sending —
     * this is the early, readable refusal, not the only one.
     */
    public function store(Request $request, TherapyCycle $therapyCycle): RedirectResponse
    {
        $this->authorize('view', $therapyCycle);

        if (! config('services.openrouter.enabled')) {
            throw ValidationException::withMessages([
                'recommendation' => 'Asystent terapii jest wyłączony, dopóki nie zostanie podpisana umowa powierzenia danych.',
            ]);
        }

        $case = $therapyCycle->clinicalCase;

        if (! $case || ! $this->cases->isSendable($case, $therapyCycle)) {
            throw ValidationException::withMessages([
                'recommendation' => 'Najpierw przygotuj i zatwierdź aktualne dane cyklu.',
            ]);
        }

        $pending = AiRecommendation::withoutGlobalScope(OperatorScope::class)
            ->where('therapy_cycle_id', $therapyCycle->id)
            ->where('status', 'queued')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'recommendation' => 'Poprzednia podpowiedź dla tego cyklu jest jeszcze przygotowywana.',
            ]);
        }

        // Counted per operator whose patient it is — that is whose budget it spends.
        $usedToday = AiRecommendation::withoutGlobalScope(OperatorScope::class)
            ->where('operator_id', $therapyCycle->operator_id)
            ->where('created_at', '>=', today())
            ->count();

        if ($usedToday >= config('services.openrouter.daily_limit_per_operator')) {
            throw ValidationException::withMessages([
                'recommendation' => 'Wykorzystano dzienny limit podpowiedzi. Spróbuj jutro.',
            ]);
        }

        $recommendation = new AiRecommendation;
        $recommendation->operator_id = $therapyCycle->operator_id;
        $recommendation->therapy_cycle_id = $therapyCycle->id;
        $recommendation->ai_clinical_case_id = $case->id;
        $recommendation->requested_by_user_id = $request->user()->id;
        $recommendation->status = 'queued';
        $recommendation->model = config('services.openrouter.model');
        $recommendation->prompt_version = SuggestionPrompt::VERSION;
        $recommendation->schema_version = SuggestionSchema::VERSION;
        $recommendation->case_text_hash = $case->textHash();
        $recommendation->save();

        AuditLogService::log($this->cases->patientOf($therapyCycle), PatientAccessAction::SentToAi);

        RequestTherapySuggestion::dispatch($recommendation);

        return redirect()
            ->route('recommendations.show', $recommendation)
            ->with('status', 'Wysłano zatwierdzone dane. Podpowiedź pojawi się tutaj za chwilę.');
    }

    public function show(Request $request, AiRecommendation $recommendation): View|JsonResponse
    {
        $cycle = $this->cycleOf($recommendation);

        // Polled by the page while the suggestion is being prepared.
        if ($request->wantsJson()) {
            return response()->json(['status' => $recommendation->status]);
        }

        return view('therapy-cycles.recommendation', [
            'recommendation' => $recommendation->load(['requestedBy', 'decidedBy']),
            'cycle' => $cycle->load('patient'),
        ]);
    }

    public function decide(Request $request, AiRecommendation $recommendation): RedirectResponse
    {
        $this->cycleOf($recommendation);

        abort_unless($recommendation->isCompleted(), 422);

        $data = $request->validate([
            'decision' => ['required', 'in:useful,rejected'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $recommendation->decision = $data['decision'];
        $recommendation->decision_note = $data['decision_note'] ?? null;
        $recommendation->decided_by_user_id = $request->user()->id;
        $recommendation->decided_at = now();
        $recommendation->save();

        return redirect()
            ->route('recommendations.show', $recommendation)
            ->with('status', 'Zapisano ocenę podpowiedzi.');
    }

    private function cycleOf(AiRecommendation $recommendation): TherapyCycle
    {
        $cycle = $recommendation->therapyCycle()->withTrashed()->firstOrFail();

        $this->authorize('view', $cycle);

        return $cycle;
    }
}
