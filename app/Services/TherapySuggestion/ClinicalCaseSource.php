<?php

namespace App\Services\TherapySuggestion;

/**
 * The clinical picture of a cycle before anonymisation, in two parts.
 *
 * $raw still holds identifiers and goes through the anonymiser. $approved is text
 * a person already approved document by document — it is appended untouched,
 * because running it through again would shift its dates a second time.
 */
class ClinicalCaseSource
{
    public function __construct(
        public readonly string $raw,
        public readonly string $approved,
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->raw) === '' && trim($this->approved) === '';
    }

    /** The source as the reviewer sees it next to the outgoing text. */
    public function original(): string
    {
        return $this->join($this->raw);
    }

    /** Builds the outgoing text from an anonymised raw part. */
    public function join(string $raw): string
    {
        return $this->approved === '' ? $raw : $raw."\n\n".$this->approved;
    }

    public function hash(): string
    {
        return hash('sha256', $this->raw."\x00".$this->approved);
    }
}
