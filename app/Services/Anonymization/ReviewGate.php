<?php

namespace App\Services\Anonymization;

use App\Enums\RedactionCategory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The approval rules for any text that may leave the server — documents and the
 * clinical case sent for a therapy suggestion alike. Kept in one place on purpose:
 * a fix to this logic must never land in one copy and miss the other.
 *
 * Works on a model with the columns anonymized_text, generated_text, source_hash,
 * ruleset_version, status, manual_edits, approved_by_user_id and approved_at.
 */
class ReviewGate
{
    public function __construct(private readonly DocumentAnonymizer $anonymizer) {}

    /**
     * The source was read again, or the redaction rules changed, since the draft was
     * made — approving it would approve text nobody reviewed.
     */
    public function isStale(Model $record, string $currentSourceHash): bool
    {
        return $record->source_hash !== $currentSourceHash
            || $record->ruleset_version !== DocumentAnonymizer::RULESET_VERSION;
    }

    /**
     * Saves a hand-edited version. Any edit withdraws an earlier approval: what was
     * approved is not what is now stored.
     */
    public function updateText(Model $record, string $text): void
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
    public function residue(Model $record, Patient $patient): array
    {
        return $this->anonymizer->residue((string) $record->anonymized_text, $patient);
    }

    /**
     * @throws ValidationException when the text still contains an identifier, is out
     *                             of date, or has flagged fragments nobody acknowledged
     */
    public function approve(
        Model $record,
        string $currentSourceHash,
        Patient $patient,
        User $reviewer,
        bool $acknowledged,
    ): void {
        if ($this->isStale($record, $currentSourceHash)) {
            throw ValidationException::withMessages([
                'approval' => 'Źródło zostało zmienione lub zmieniły się reguły. Wygeneruj wersję od nowa.',
            ]);
        }

        $residue = $this->residue($record, $patient);

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
    public function revoke(Model $record): void
    {
        $record->status = 'draft';
        $record->approved_by_user_id = null;
        $record->approved_at = null;
        $record->save();
    }
}
