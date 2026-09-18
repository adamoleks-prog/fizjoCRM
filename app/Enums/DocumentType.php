<?php

namespace App\Enums;

enum DocumentType: string
{
    case Referral = 'referral';
    case Imaging = 'imaging';
    case Questionnaire = 'questionnaire';
    case Consent = 'consent';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Skierowanie',
            self::Imaging => 'Badanie obrazowe',
            self::Questionnaire => 'Kwestionariusz',
            self::Consent => 'Zgoda',
            self::Other => 'Inne',
        };
    }

    /**
     * Administrative paperwork carries the patient's identity but no clinical
     * content, so it is never a candidate for automated analysis.
     */
    public function isClinical(): bool
    {
        return match ($this) {
            self::Referral, self::Imaging, self::Questionnaire => true,
            self::Consent, self::Other => false,
        };
    }
}
