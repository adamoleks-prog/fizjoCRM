<?php

namespace App\Services\Anonymization;

use App\Enums\RedactionCategory;
use App\Models\Patient;
use Carbon\Carbon;

/**
 * Strips direct identifiers from document text before it may leave the server.
 *
 * Three layers, applied strongest first: values we already know from the
 * patient record, then identifiers with a fixed shape, then a residue scan that
 * refuses to call the result safe when something still looks like a name.
 *
 * This produces pseudonymised text, not anonymous text — rare findings combined
 * with age can still point at a person, which no filter can prevent.
 */
class DocumentAnonymizer
{
    public const RULESET_VERSION = '1.0';

    /** @var array<string, int> */
    private array $report = [];

    public function __construct(private readonly NameAndPlaceRedactor $namesAndPlaces) {}

    public function anonymize(string $text, Patient $patient): AnonymizationResult
    {
        $this->report = [];

        $text = $this->redactKnownValues($text, $patient);
        $text = $this->redactPatterns($text);

        [$text, $suspicions] = $this->namesAndPlaces->redact($text, fn (RedactionCategory $c) => $this->tally($c));

        $text = $this->shiftDates($text, $this->offsetFor($patient));
        $text = $this->collapseRepeatedTokens($text);

        return new AnonymizationResult($text, $this->report, $suspicions);
    }

    /**
     * Re-checks text that is about to leave the server — including text a person
     * has just edited by hand, who may have typed an identifier back in.
     *
     * "critical" counts what the fixed layers still find (never the values), and
     * "findings" lists the fragments layer 3 would still remove or is unsure about.
     *
     * @return array{critical: array<string, int>, findings: array<int, string>}
     */
    public function residue(string $text, Patient $patient): array
    {
        $this->report = [];

        $this->redactPatterns($this->redactKnownValues($text, $patient));

        $critical = [];

        foreach (RedactionCategory::cases() as $category) {
            $count = $this->report[$category->value] ?? 0;

            if ($category->isCritical() && $count > 0) {
                $critical[$category->value] = $count;
            }
        }

        return ['critical' => $critical, 'findings' => $this->namesAndPlaces->findings($text)];
    }

    /**
     * Layer 1 — we know exactly who this patient is, so we look for their actual
     * values rather than guessing where a name might be.
     */
    private function redactKnownValues(string $text, Patient $patient): string
    {
        foreach ([$patient->last_name, $patient->first_name] as $name) {
            if (mb_strlen((string) $name) >= 3) {
                $text = $this->replace($text, $this->inflectedNamePattern($name), RedactionCategory::Patient);
            }
        }

        if ($patient->phone) {
            $text = $this->replace($text, $this->digitsPattern($patient->phone), RedactionCategory::Phone);
        }

        if ($patient->email) {
            $text = $this->replace($text, '/'.preg_quote($patient->email, '/').'/iu', RedactionCategory::Email);
        }

        foreach ($this->addressParts($patient->address) as $part) {
            $text = $this->replace($text, '/'.preg_quote($part, '/').'\w{0,3}/iu', RedactionCategory::Address);
        }

        return $text;
    }

    /**
     * Layer 2 — identifiers recognisable by shape alone, which also catches the
     * data of people other than the patient.
     */
    private function redactPatterns(string $text): string
    {
        $text = $this->replacePesel($text);

        $text = $this->replace($text, '/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', RedactionCategory::Email);
        // A postal code is followed by its town — which layer 1 may already have
        // turned into a token. Without that check the pattern also eats measurements
        // such as a 10-120 degree range of motion.
        $text = $this->replace($text, '/\b\d{2}-\d{3}\b(?=[ \t]+(?:\p{Lu}\p{L}|\[))/u', RedactionCategory::Address);

        // Polish phone numbers, with or without the country prefix and separators.
        $text = $this->replace(
            $text,
            '/(?<![\d-])(?:\+48[ \t-]?)?\d{3}[ \t-]?\d{3}[ \t-]?\d{3}(?![\d-])/u',
            RedactionCategory::Phone,
        );

        $text = $this->replace($text, '/\b[A-Z]{3}[ \t]?\d{6}\b/u', RedactionCategory::IdDocument);

        // Every pattern below uses [ \t] rather than \s on purpose: \s also matches a
        // newline, and a match that crosses one deletes the line break with it.

        // Facility names carry the town as often as the address does.
        $text = $this->replace(
            $text,
            '/\b(?:NZOZ|SPZOZ|ZOZ|Przychodnia|Centrum[ \t]+Medyczne|Centrum[ \t]+Rehabilitacji|Gabinet|Klinika|Szpital)'
            .'(?:[ \t]+[\p{Lu}][\p{L}-]+){0,3}/u',
            RedactionCategory::Facility,
        );

        // "dr n. med. Jan Zieliński", "lek. med. Anna Nowak"
        $text = $this->replace(
            $text,
            '/\b(?:dr|lek\.?|lekarz)[ \t.]*(?:n\.?[ \t]*med\.?)?[ \t]*[\p{Lu}][\p{L}-]+[ \t]+[\p{Lu}][\p{L}-]+/u',
            RedactionCategory::Doctor,
        );

        return $text;
    }

    /**
     * PESEL is eleven digits with a checksum, so the checksum keeps us off
     * unrelated digit runs.
     */
    private function replacePesel(string $text): string
    {
        return preg_replace_callback('/(?<!\d)\d{11}(?!\d)/u', function (array $m) {
            if (! $this->isValidPesel($m[0])) {
                return $m[0];
            }

            $this->tally(RedactionCategory::Pesel);

            return RedactionCategory::Pesel->token();
        }, $text) ?? $text;
    }

    private function isValidPesel(string $candidate): bool
    {
        $weights = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
        $sum = 0;

        foreach ($weights as $i => $weight) {
            $sum += ((int) $candidate[$i]) * $weight;
        }

        return (10 - ($sum % 10)) % 10 === (int) $candidate[10];
    }

    /**
     * Dates are shifted rather than removed: the interval between two events is
     * clinically meaningful ("six weeks after the injury"), the absolute date is
     * only identifying.
     */
    private function shiftDates(string $text, int $offsetDays): string
    {
        /** @var array<string, string> $parked */
        $parked = [];

        // Full dates are parked behind a sentinel before the bare-year pass runs,
        // otherwise that pass would shift the year inside a date already shifted.
        $park = function (string $value) use (&$parked): string {
            $key = "\x00D".count($parked)."\x00";
            $parked[$key] = $value;

            return $key;
        };

        $text = preg_replace_callback(
            '/\b(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})\b/u',
            function (array $m) use ($offsetDays, $park) {
                $date = $this->parseDate((int) $m[3], (int) $m[2], (int) $m[1]);

                if ($date === null) {
                    return $m[0];
                }

                $this->tally(RedactionCategory::Date);

                return $park($date->addDays($offsetDays)->format('d.m.Y'));
            },
            $text,
        ) ?? $text;

        $text = preg_replace_callback(
            '/\b(\d{4})-(\d{2})-(\d{2})\b/u',
            function (array $m) use ($offsetDays, $park) {
                $date = $this->parseDate((int) $m[1], (int) $m[2], (int) $m[3]);

                if ($date === null) {
                    return $m[0];
                }

                $this->tally(RedactionCategory::Date);

                return $park($date->addDays($offsetDays)->format('Y-m-d'));
            },
            $text,
        ) ?? $text;

        // Bare years ("poród w 2019 r.") shift by whole years so they stay plausible.
        $yearShift = $this->yearShift($offsetDays);

        $text = preg_replace_callback('/\b(?:19|20)\d{2}\b/u', function (array $m) use ($yearShift) {
            $this->tally(RedactionCategory::Date);

            return (string) ((int) $m[0] + $yearShift);
        }, $text) ?? $text;

        return strtr($text, $parked);
    }

    /**
     * A day offset under half a year rounds to zero years, which would leave a
     * birth year untouched — so the shift is never allowed to be nothing.
     */
    private function yearShift(int $offsetDays): int
    {
        $years = (int) round($offsetDays / 365);

        return $years !== 0 ? $years : ($offsetDays < 0 ? -1 : 1);
    }

    private function parseDate(int $year, int $month, int $day): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day);
    }

    /**
     * A surname keeps its stem through Polish declension ("Kowalska",
     * "Kowalskiej", "Kowalską"), so the stem plus a short ending matches them all.
     */
    private function inflectedNamePattern(string $name): string
    {
        $stem = preg_replace('/[aeiouyąęó]$/iu', '', trim($name)) ?: $name;

        return '/(?<![\p{L}])'.preg_quote($stem, '/').'\p{L}{0,3}(?![\p{L}])/iu';
    }

    /**
     * Built from the digits alone so spacing in the document does not matter, and
     * tolerant of the characters OCR habitually confuses.
     */
    private function digitsPattern(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (mb_strlen($digits) < 7) {
            return '/(?!)/';
        }

        $confusable = ['0' => '[0oO]', '1' => '[1lI]', '5' => '[5sS]', '8' => '[8bB]'];

        // The separator sits between digits only, and never matches a newline:
        // trailing or line-crossing whitespace here would swallow the line break
        // after the number and fuse two lines of the document into one.
        $pattern = implode('[ \t-]?', array_map(
            fn (string $digit) => $confusable[$digit] ?? $digit,
            str_split($digits),
        ));

        return '/(?<![\d])(?:\+?48[ \t-]?)?'.$pattern.'(?![\d])/u';
    }

    /**
     * @return array<int, string>
     */
    private function addressParts(?string $address): array
    {
        if (! $address) {
            return [];
        }

        $parts = [];

        foreach (preg_split('/[,\s]+/u', $address) ?: [] as $chunk) {
            $chunk = trim($chunk, " \t\n\r\0\x0B.,");

            // Skip house numbers and postal codes — the pattern layer handles those.
            if (mb_strlen($chunk) >= 3 && ! preg_match('/^\d/u', $chunk)) {
                $parts[] = $chunk;
            }
        }

        return $parts;
    }

    /**
     * "Anna Kowalska" matches twice and would read as two different people;
     * neighbouring copies of one token collapse into a single mention.
     */
    private function collapseRepeatedTokens(string $text): string
    {
        return preg_replace('/(\[[\p{Lu}]+\])(?:[ \t]+\1)+/u', '$1', $text) ?? $text;
    }

    private function replace(string $text, string $pattern, RedactionCategory $category): string
    {
        $replaced = preg_replace_callback($pattern, function () use ($category) {
            $this->tally($category);

            return $category->token();
        }, $text);

        return $replaced ?? $text;
    }

    private function tally(RedactionCategory $category): void
    {
        $this->report[$category->value] = ($this->report[$category->value] ?? 0) + 1;
    }

    /**
     * Generated once per patient and kept on the record, so every document of the
     * same patient shifts identically and intervals survive across documents.
     */
    private function offsetFor(Patient $patient): int
    {
        if ($patient->date_offset_days === null) {
            $patient->forceFill([
                'date_offset_days' => random_int(60, 400) * (random_int(0, 1) ? 1 : -1),
            ])->save();
        }

        return (int) $patient->date_offset_days;
    }
}
