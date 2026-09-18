<?php

namespace App\Services\Anonymization;

/**
 * A set of words that can be looked up through Polish declension.
 *
 * The registers list names in the nominative only, but documents contain
 * "Kowalskiego", "Martą", "w Oświęcimiu". Each entry is therefore indexed twice —
 * as written and with its final vowel dropped — and a lookup also tries the token
 * with common case endings removed. Everything is compared folded to lowercase
 * without diacritics, because OCR frequently loses the Polish letters.
 */
class WordIndex
{
    /** Longest first, so "iego" is tried before "ego" and "a". */
    private const ENDINGS = [
        'iego', 'iemu', 'ego', 'emu', 'iej', 'ami', 'ach', 'owi', 'iem',
        'ie', 'iu', 'ej', 'ym', 'im', 'om', 'ow', 'em', 'a', 'e', 'i', 'o', 'u', 'y',
    ];

    /** @var array<string, true> */
    private array $exact = [];

    /** @var array<string, true> */
    private array $stems = [];

    /**
     * @param  iterable<string>  $words
     */
    public function __construct(iterable $words)
    {
        foreach ($words as $word) {
            $folded = self::fold($word);

            if ($folded === '') {
                continue;
            }

            $this->exact[$folded] = true;

            $stem = preg_replace('/[aeiouy]$/', '', $folded) ?? $folded;

            if (strlen($stem) >= 3) {
                $this->stems[$stem] = true;
            }
        }
    }

    public static function fromFile(string $path): self
    {
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        return new self(array_filter($lines ?: [], fn (string $l) => ! str_starts_with($l, '#')));
    }

    public static function fold(string $value): string
    {
        return strtr(mb_strtolower(trim($value)), [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
    }

    /**
     * @param  int  $minStem  shortest remainder accepted after stripping an ending;
     *                        guards against short stems matching unrelated words
     */
    public function matches(string $token, int $minStem = 4): bool
    {
        $folded = self::fold($token);

        if (isset($this->exact[$folded]) || isset($this->stems[$folded])) {
            return true;
        }

        foreach (self::ENDINGS as $ending) {
            if (! str_ends_with($folded, $ending)) {
                continue;
            }

            $base = substr($folded, 0, -strlen($ending));

            if (strlen($base) < $minStem) {
                continue;
            }

            if (isset($this->exact[$base]) || isset($this->stems[$base])) {
                return true;
            }
        }

        return false;
    }

    public function has(string $token): bool
    {
        return isset($this->exact[self::fold($token)]);
    }
}
