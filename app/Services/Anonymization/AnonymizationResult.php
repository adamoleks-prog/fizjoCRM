<?php

namespace App\Services\Anonymization;

use App\Enums\RedactionCategory;

class AnonymizationResult
{
    /**
     * @param  array<string, int>  $report  counts per category value — never the removed values
     * @param  array<int, string>  $suspicions  fragments that look like names but were not matched
     */
    public function __construct(
        public readonly string $text,
        public readonly array $report,
        public readonly array $suspicions,
    ) {}

    public function count(RedactionCategory $category): int
    {
        return $this->report[$category->value] ?? 0;
    }

    public function totalRedactions(): int
    {
        return array_sum($this->report);
    }

    /**
     * Low confidence blocks automatic sending: something in the text still looks
     * like a personal name that no layer claimed.
     */
    public function isHighConfidence(): bool
    {
        return $this->suspicions === [];
    }
}
