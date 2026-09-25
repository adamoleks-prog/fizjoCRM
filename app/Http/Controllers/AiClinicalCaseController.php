<?php

namespace App\Http\Controllers;

use App\Enums\PatientAccessAction;
use App\Enums\RedactionCategory;
use App\Http\Requests\UpdateClinicalCaseTextRequest;
use App\Models\AiClinicalCase;
use App\Models\TherapyCycle;
use App\Services\Anonymization\ReviewPresenter;
use App\Services\AuditLogService;
use App\Services\TherapySuggestion\ClinicalCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Review of the clinical case of a cycle — the same gate documents go through,
 * applied to the whole text that would be sent.
 */
class AiClinicalCaseController extends Controller
{
    public function __construct(
        private readonly ClinicalCaseService $cases,
        private readonly ReviewPresenter $presenter,
    ) {}

    public function store(TherapyCycle $therapyCycle): RedirectResponse
    {
        $this->authorize('view', $therapyCycle);

        $this->cases->prepare($therapyCycle);

        return redirect()
            ->route('clinical-cases.show', $therapyCycle)
            ->with('status', 'Przygotowano dane do przeglądu. Sprawdź je, zanim cokolwiek zostanie zatwierdzone.');
    }

    public function show(TherapyCycle $therapyCycle): View|RedirectResponse
    {
        $this->authorize('view', $therapyCycle);

        $record = $therapyCycle->clinicalCase;

        if (! $record) {
            return redirect()
                ->route('therapy-cycles.show', $therapyCycle)
                ->withErrors(['case' => 'Dane tego cyklu nie zostały jeszcze przygotowane.']);
        }

        // The screen puts the original medical record in front of the user.
        AuditLogService::log($this->cases->patientOf($therapyCycle), PatientAccessAction::Viewed);

        $residue = $this->cases->residue($record, $therapyCycle);

        $panes = $this->presenter->render(
            $this->cases->source($therapyCycle)->original(),
            (string) $record->anonymized_text,
            $residue['findings'],
        );

        return view('therapy-cycles.case', [
            'cycle' => $therapyCycle->load('patient'),
            'record' => $record->load('approvedBy'),
            'residue' => $residue,
            'originalHtml' => $panes['original'],
            'outgoingHtml' => $panes['outgoing'],
            'stale' => $this->cases->isStale($record, $therapyCycle),
            'categories' => collect($record->redaction_report ?? [])
                ->map(fn (int $count, string $key) => ['label' => RedactionCategory::from($key)->label(), 'count' => $count])
                ->values(),
        ]);
    }

    public function update(UpdateClinicalCaseTextRequest $request, TherapyCycle $therapyCycle): RedirectResponse
    {
        $this->cases->updateText($this->record($therapyCycle), $request->string('anonymized_text')->toString());

        return redirect()
            ->route('clinical-cases.show', $therapyCycle)
            ->with('status', 'Zapisano ręczne poprawki. Ewentualne wcześniejsze zatwierdzenie zostało wycofane.');
    }

    public function approve(Request $request, TherapyCycle $therapyCycle): RedirectResponse
    {
        $this->authorize('view', $therapyCycle);

        $this->cases->approve($this->record($therapyCycle), $therapyCycle, $request->user(), $request->boolean('acknowledge'));

        return redirect()
            ->route('clinical-cases.show', $therapyCycle)
            ->with('status', 'Zatwierdzono. Te dane mogą zostać wysłane do asystenta terapii.');
    }

    public function revoke(TherapyCycle $therapyCycle): RedirectResponse
    {
        $this->authorize('view', $therapyCycle);

        $this->cases->revoke($this->record($therapyCycle));

        return redirect()
            ->route('clinical-cases.show', $therapyCycle)
            ->with('status', 'Zatwierdzenie wycofane.');
    }

    private function record(TherapyCycle $therapyCycle): AiClinicalCase
    {
        return $therapyCycle->clinicalCase()->firstOrFail();
    }
}
