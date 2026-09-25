<?php

namespace App\Services\TherapySuggestion;

use App\Models\AiClinicalCase;
use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\TherapyCycle;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;
use App\Services\Anonymization\ReviewGate;
use Illuminate\Validation\ValidationException;

/**
 * The cycle side of the review gate: how the clinical case is produced. Approval
 * rules are ReviewGate's, the same ones documents go through.
 */
class ClinicalCaseService
{
    public function __construct(
        private readonly ClinicalCaseAssembler $assembler,
        private readonly DocumentAnonymizer $anonymizer,
        private readonly ReviewGate $gate,
    ) {}

    public function source(TherapyCycle $cycle): ClinicalCaseSource
    {
        return $this->assembler->assemble($cycle);
    }

    /**
     * Creates the draft, or replaces the existing one — regenerating discards manual
     * corrections and the approval, because both belonged to the old text.
     */
    public function prepare(TherapyCycle $cycle): AiClinicalCase
    {
        $source = $this->source($cycle);

        if ($source->isEmpty()) {
            throw ValidationException::withMessages([
                'case' => 'Ten cykl nie ma jeszcze żadnej udokumentowanej wizyty ani zatwierdzonego dokumentu.',
            ]);
        }

        $patient = $this->patientOf($cycle);
        $result = $this->anonymizer->anonymize($source->raw, $patient);
        $text = $source->join($result->text);

        $record = $cycle->clinicalCase()->withoutGlobalScope(OperatorScope::class)->first() ?? new AiClinicalCase;

        $record->fill([
            'anonymized_text' => $text,
            'generated_text' => $text,
            'source_hash' => $source->hash(),
            'redaction_report' => $result->report,
            'suspicion_count' => count($result->suspicions),
            'confidence' => $result->isHighConfidence() ? 'high' : 'low',
            'status' => 'draft',
            'manual_edits' => 0,
            'ruleset_version' => DocumentAnonymizer::RULESET_VERSION,
        ]);

        $record->operator_id = $cycle->operator_id;
        $record->therapy_cycle_id = $cycle->id;
        $record->approved_by_user_id = null;
        $record->approved_at = null;
        $record->save();

        return $record;
    }

    /** A visit note changed, or a document approval was withdrawn, since the draft was made. */
    public function isStale(AiClinicalCase $record, TherapyCycle $cycle): bool
    {
        return $this->gate->isStale($record, $this->source($cycle)->hash());
    }

    public function updateText(AiClinicalCase $record, string $text): void
    {
        $this->gate->updateText($record, $text);
    }

    /**
     * @return array{critical: array<string, int>, findings: array<int, string>}
     */
    public function residue(AiClinicalCase $record, TherapyCycle $cycle): array
    {
        return $this->gate->residue($record, $this->patientOf($cycle));
    }

    public function approve(AiClinicalCase $record, TherapyCycle $cycle, User $reviewer, bool $acknowledged): void
    {
        $this->gate->approve($record, $this->source($cycle)->hash(), $this->patientOf($cycle), $reviewer, $acknowledged);
    }

    public function revoke(AiClinicalCase $record): void
    {
        $this->gate->revoke($record);
    }

    /** Approved and still matching the records — the only state in which it may be sent. */
    public function isSendable(AiClinicalCase $record, TherapyCycle $cycle): bool
    {
        return $record->mayBeSent() && ! $this->isStale($record, $cycle);
    }

    public function patientOf(TherapyCycle $cycle): Patient
    {
        return $cycle->patient()->withoutGlobalScope(OperatorScope::class)->firstOrFail();
    }
}
