<?php

namespace App\Http\Controllers;

use App\Enums\PatientAccessAction;
use App\Enums\RedactionCategory;
use App\Http\Requests\UpdateAnonymizedTextRequest;
use App\Models\Document;
use App\Services\Anonymization\DocumentAnonymizationService;
use App\Services\Anonymization\ReviewPresenter;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentAnonymizationController extends Controller
{
    public function __construct(
        private readonly DocumentAnonymizationService $gate,
        private readonly ReviewPresenter $presenter,
    ) {}

    public function store(Document $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $this->gate->prepare($document);

        return redirect()
            ->route('anonymizations.show', $document)
            ->with('status', 'Przygotowano wersję do przeglądu. Sprawdź ją, zanim cokolwiek zostanie zatwierdzone.');
    }

    public function show(Document $document): View|RedirectResponse
    {
        $this->authorize('view', $document);

        $record = $document->anonymization;

        if (! $record) {
            return redirect()
                ->route('patients.show', $document->patient_id)
                ->withErrors(['document' => 'Ten dokument nie ma jeszcze przygotowanej wersji do analizy.']);
        }

        // The screen puts the original medical text in front of the user.
        AuditLogService::log($document->patient, PatientAccessAction::Viewed);

        $residue = $this->gate->residue($record, $document);

        $panes = $this->presenter->render(
            (string) $document->ocr_text,
            (string) $record->anonymized_text,
            $residue['findings'],
        );

        return view('documents.anonymization', [
            'document' => $document->load('patient'),
            'record' => $record->load('approvedBy'),
            'residue' => $residue,
            'originalHtml' => $panes['original'],
            'outgoingHtml' => $panes['outgoing'],
            'stale' => $this->gate->isStale($record, $document),
            'categories' => collect($record->redaction_report ?? [])
                ->map(fn (int $count, string $key) => ['label' => RedactionCategory::from($key)->label(), 'count' => $count])
                ->values(),
        ]);
    }

    public function update(UpdateAnonymizedTextRequest $request, Document $document): RedirectResponse
    {
        $record = $document->anonymization()->firstOrFail();

        $this->gate->updateText($record, $request->string('anonymized_text')->toString());

        return redirect()
            ->route('anonymizations.show', $document)
            ->with('status', 'Zapisano ręczne poprawki. Ewentualne wcześniejsze zatwierdzenie zostało wycofane.');
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $record = $document->anonymization()->firstOrFail();

        $this->gate->approve($record, $document, $request->user(), $request->boolean('acknowledge'));

        return redirect()
            ->route('anonymizations.show', $document)
            ->with('status', 'Zatwierdzono. Ta wersja może zostać przekazana do analizy.');
    }

    public function revoke(Document $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $this->gate->revoke($document->anonymization()->firstOrFail());

        return redirect()
            ->route('anonymizations.show', $document)
            ->with('status', 'Zatwierdzenie wycofane.');
    }
}
