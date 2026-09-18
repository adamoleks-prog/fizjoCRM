<?php

namespace App\Enums;

enum MilestoneHorizon: string
{
    case Short = 'short';
    case Medium = 'medium';
    case Long = 'long';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Krótkoterminowy',
            self::Medium => 'Średnioterminowy',
            self::Long => 'Długoterminowy',
        };
    }

    /**
     * Ordering used when listing milestones: short-term goals lead to the long-term one.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Short => 0,
            self::Medium => 1,
            self::Long => 2,
        };
    }
}
