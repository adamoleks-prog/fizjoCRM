<?php

namespace App\Services\Booking;

use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use App\Services\Messaging\PhoneNumber;

/**
 * Finds an online booker among a physiotherapist's patients despite typos and
 * missing Polish characters ("Lukasz Kowalksi" finds "Łukasz Kowalski").
 *
 * Names are compared after folding case and diacritics, allowing one typo in
 * short names and two in longer ones. The phone decides how far the match is
 * trusted: same phone and names — the same person; same names only — probably
 * the same person with a new number, which the physiotherapist confirms.
 */
class PatientMatcher
{
    private const FOLD = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ];

    /**
     * @return array{patient: ?Patient, phoneMatches: bool}
     */
    public function find(User $physiotherapist, string $phone, string $firstName, string $lastName): array
    {
        $first = self::fold($firstName);
        $last = self::fold($lastName);

        $sameName = Patient::withoutGlobalScope(OperatorScope::class)
            ->where('operator_id', $physiotherapist->id)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email', 'operator_id'])
            ->filter(fn (Patient $p) => self::similar($last, self::fold((string) $p->last_name))
                && self::similar($first, self::fold((string) $p->first_name)))
            ->values();

        $withPhone = $sameName->first(fn (Patient $p) => PhoneNumber::normalize($p->phone) === $phone);

        if ($withPhone) {
            return ['patient' => $withPhone, 'phoneMatches' => true];
        }

        // Only an unambiguous name match is attached — two "Anna Nowak" on file
        // without the phone to tell them apart become a new record to check.
        return ['patient' => $sameName->count() === 1 ? $sameName->first() : null, 'phoneMatches' => false];
    }

    public static function fold(string $name): string
    {
        return strtr(mb_strtolower(trim($name)), self::FOLD);
    }

    /** Levenshtein on folded names: one typo up to 5 letters, two above. */
    public static function similar(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        $allowed = max(mb_strlen($a), mb_strlen($b)) <= 5 ? 1 : 2;

        return levenshtein($a, $b) <= $allowed;
    }
}
