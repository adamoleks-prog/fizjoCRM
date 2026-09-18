<?php

namespace App\Services\Anonymization;

use App\Enums\RedactionCategory;
use App\Models\Document;
use App\Models\DocumentAnonymization;
use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The review gate: turns a document's text into a draft that may leave the server
 * only after a person has looked at it and approved it.
 */
class DocumentAnonymizationService
{
    public function __construct(private readonly DocumentAnonymizer $anonymizer) {}

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
            'source_hash' => $this->hash($document),
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

    /**
     * The document was read again, or the redaction rules changed, since the draft
     * was made — approving it would approve text nobody reviewed.
     */
    public function isStale(DocumentAnonymization $record, Document $document): bool
    {
        return $record->source_hash !== $this->hash($document)
            || $record->ruleset_version !== DocumentAnonymizer::RULESET_VERSION;
    }

    /**
     * Saves a hand-edited version. Any edit withdraws an earlier approval: what was
     * approved is not what is now stored.
     */
    public function updateText(DocumentAnonymization $record, string $text): void
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $record->fill([
            'anonymized_text' => $text,
            'manual_edits' => WordDiff::changedWords((string) $record->generated_text, $text),
            'status' => 'draft',
        ]);

        $record->approved_by_user_id = null;
        $record->approved_at = null;
        $record->save();
    }

    /**
     * What is still wrong with the current text, for the reviewer to act on.
     *
     * @return array{critical: array<string, int>, findings: array<int, string>}
     */
    public function residue(DocumentAnonymization $record, Document $document): array
    {
        return $this->anonymizer->residue((string) $record->anonymized_text, $this->patientOf($document));
    }

    /**
     * @throws ValidationException when the text still contains an identifier, is out
     *                             of date, or has flagged fragments nobody acknowledged
     */
    public function approve(DocumentAnonymization $record, Document $document, User $reviewer, bool $acknowledged): void
    {
        if ($this->isStale($record, $document)) {
            throw ValidationException::withMessages([
                'approval' => 'Dokument został odczytany ponownie lub zmieniły się reguły. Wygeneruj wersję od nowa.',
            ]);
        }

        $residue = $this->residue($record, $document);

        // Categories and counts only — repeating the value would put the very
        // identifier that must not leave into an error message.
        if ($residue['critical'] !== []) {
            $parts = array_map(
                fn (string $category, int $count) => RedactionCategory::from($category)->label()." ({$count})",
                array_keys($residue['critical']),
                $residue['critical'],
            );

            throw ValidationException::withMessages([
                'approval' => 'Tekst nadal zawiera dane pacjenta: '.implode(', ', $parts).'. Usuń je przed zatwierdzeniem.',
            ]);
        }

        if ($residue['findings'] !== [] && ! $acknowledged) {
            throw ValidationException::withMessages([
                'acknowledge' => 'Potwierdź, że sprawdziłeś oznaczone fragmenty.',
            ]);
        }

        $record->status = 'approved';
        $record->approved_by_user_id = $reviewer->id;
        $record->approved_at = now();
        $record->save();
    }

    /** Withdraws an approval without touching the text. */
    public function revoke(DocumentAnonymization $record): void
    {
        $record->status = 'draft';
        $record->approved_by_user_id = null;
        $record->approved_at = null;
        $record->save();
    }

    private function hash(Document $document): string
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
