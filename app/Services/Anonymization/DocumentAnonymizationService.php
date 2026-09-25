<?php

namespace App\Services\Anonymization;

use App\Models\Document;
use App\Models\DocumentAnonymization;
use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The document side of the review gate: which documents qualify and how their
 * draft is produced. The approval rules themselves live in ReviewGate, shared with
 * the clinical case sent for a therapy suggestion.
 */
class DocumentAnonymizationService
{
    public function __construct(
        private readonly DocumentAnonymizer $anonymizer,
        private readonly ReviewGate $gate,
    ) {}

    /**
     * Null when the document can go through the gate, otherwise the reason.
     */
    public function ineligibleReason(Document $document): ?string
    {
        if (! $document->type->isClinical()) {
            return "Dokumenty typu „{$document->type->label()}” nie są przekazywane do analizy.";
        }

        if (! $document->text_extraction_status->hasText() || blank($document->ocr_text)) {
            return 'Z tego dokumentu nie odczytano tekstu, więc nie ma czego analizować.';
        }

        return null;
    }

    /**
     * Creates the draft, or replaces the existing one — regenerating discards any
     * manual corrections and the approval, because both belonged to the old text.
     */
    public function prepare(Document $document): DocumentAnonymization
    {
        if ($reason = $this->ineligibleReason($document)) {
            throw ValidationException::withMessages(['document' => $reason]);
        }

        $patient = $this->patientOf($document);
        $result = $this->anonymizer->anonymize($document->ocr_text, $patient);

        $record = $document->anonymization()->first() ?? new DocumentAnonymization;

        $record->fill([
            'anonymized_text' => $result->text,
            'generated_text' => $result->text,
            'source_hash' => self::sourceHash($document),
            'redaction_report' => $result->report,
            'suspicion_count' => count($result->suspicions),
            'confidence' => $result->isHighConfidence() ? 'high' : 'low',
            'status' => 'draft',
            'manual_edits' => 0,
            'ruleset_version' => DocumentAnonymizer::RULESET_VERSION,
        ]);

        $record->operator_id = $patient->operator_id;
        $record->document_id = $document->id;
        $record->approved_by_user_id = null;
        $record->approved_at = null;
        $record->save();

        return $record;
    }

    public function isStale(DocumentAnonymization $record, Document $document): bool
    {
        return $this->gate->isStale($record, self::sourceHash($document));
    }

    public function updateText(DocumentAnonymization $record, string $text): void
    {
        $this->gate->updateText($record, $text);
    }

    /**
     * @return array{critical: array<string, int>, findings: array<int, string>}
     */
    public function residue(DocumentAnonymization $record, Document $document): array
    {
        return $this->gate->residue($record, $this->patientOf($document));
    }

    public function approve(DocumentAnonymization $record, Document $document, User $reviewer, bool $acknowledged): void
    {
        $this->gate->approve($record, self::sourceHash($document), $this->patientOf($document), $reviewer, $acknowledged);
    }

    public function revoke(DocumentAnonymization $record): void
    {
        $this->gate->revoke($record);
    }

    /** Fingerprint of the document text an anonymised version was made from. */
    public static function sourceHash(Document $document): string
    {
        return hash('sha256', (string) $document->ocr_text);
    }

    /**
     * Resolved without the operator scope: the policy has already decided the
     * caller may touch this document, and the scope would hide a patient an admin
     * is entitled to see.
     */
    private function patientOf(Document $document): Patient
    {
        return $document->patient()->withoutGlobalScope(OperatorScope::class)->firstOrFail();
    }
}
