<?php

namespace App\Services\Anonymization;

use App\Enums\RedactionCategory;

/**
 * Layer 3 — people and places the patient record knows nothing about.
 *
 * The registers cannot be applied blindly: Kolano, Bark and Krzyż are villages,
 * Parkinson and Baker are surnames. So a bare dictionary hit is never enough to
 * remove text. Removal needs a strong signal (a first name next to a surname, a
 * word like "córka" in front, a multi-word place name); a weak hit is reported as
 * a suspicion, which lowers confidence and sends the document to a person.
 */
class NameAndPlaceRedactor
{
    /** Words that introduce a person: "córka Marta", "pani Kowalska". */
    private const PERSON_CONTEXT = [
        'pan', 'pani', 'panu', 'pana', 'pania', 'p', 'corka', 'syn', 'maz', 'zona', 'matka', 'mama', 'ojciec',
        'tata', 'brat', 'siostra', 'wnuk', 'wnuczka', 'opiekun', 'opiekunka', 'partner', 'partnerka', 'tesc',
        'tesciowa', 'ziec', 'synowa', 'babcia', 'dziadek', 'sasiad', 'sasiadka', 'kolega', 'kolezanka',
        'znajomy', 'znajoma', 'szwagier', 'szwagierka', 'ciotka', 'wujek', 'kuzyn', 'kuzynka', 'pielegniarka',
        'corke', 'syna', 'meza', 'zone', 'matke', 'ojca', 'brata', 'siostre', 'wnuka', 'wnuczke', 'corki',
        'matki', 'ojca', 'brata', 'siostry', 'wnuczki', 'corka',
    ];

    /** Short words that end in a full stop without ending the sentence. */
    private const ABBREVIATIONS = [
        'dr', 'ul', 'al', 'os', 'pl', 'lek', 'med', 'prof', 'inz', 'mgr', 'n', 'im', 'sw', 'tel', 'ok', 'np',
        'tj', 'ww', 'godz', 'ur', 'zam', 'hab', 'ks', 'gen', 'ppor', 'kpt', 'por', 'sp', 'ws', 'nr',
    ];

    /** @var array<int, string> */
    private array $suspicions = [];

    /** @var array<int, string> */
    private array $removed = [];

    public function __construct(private readonly NameDictionaries $dictionaries) {}

    /**
     * Fragments of an already processed text that this layer would still remove
     * or is unsure about. Run on the outgoing text, it is what a reviewer needs to
     * look at: anything listed here escaped the first pass.
     *
     * @return array<int, string>
     */
    public function findings(string $text): array
    {
        $this->redact($text, function (RedactionCategory $category): void {});

        return array_values(array_unique([...$this->removed, ...$this->suspicions]));
    }

    /**
     * @param  callable(RedactionCategory): void  $tally
     * @return array{0: string, 1: array<int, string>} the redacted text and the suspicions left in it
     */
    public function redact(string $text, callable $tally): array
    {
        $this->suspicions = [];
        $this->removed = [];

        if (! preg_match_all(
            '/(?<![\p{L}\d\[])\p{Lu}\p{Ll}{2,}(?:[ -]\p{Lu}\p{Ll}{2,}){0,3}(?![\p{L}])/u',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE,
        )) {
            return [$text, []];
        }

        /** @var array<int, array{0: int, 1: int, 2: string}> $edits */
        $edits = [];

        foreach ($matches[0] as [$run, $offset]) {
            foreach ($this->evaluateRun($text, $run, $offset) as $edit) {
                $edits[] = $edit;
                $tally($edit[3]);
            }
        }

        usort($edits, fn (array $a, array $b) => $b[0] <=> $a[0]);

        foreach ($edits as [$start, $length, $token]) {
            $this->removed[] = substr($text, $start, $length);
            $text = substr_replace($text, $token, $start, $length);
        }

        return [$text, array_values(array_unique($this->suspicions))];
    }

    /**
     * @return array<int, array{0: int, 1: int, 2: string, 3: RedactionCategory}>
     */
    private function evaluateRun(string $text, string $run, int $runOffset): array
    {
        // Words keep their byte position so a partial run can be replaced in place.
        preg_match_all('/\p{Lu}\p{Ll}{2,}/u', $run, $found, PREG_OFFSET_CAPTURE);
        $tokens = array_map(fn (array $f) => $f[0], $found[0]);
        $positions = array_map(fn (array $f) => $runOffset + $f[1], $found[0]);

        $atSentenceStart = $this->isSentenceStart($text, $runOffset);
        $previousWord = $this->wordBefore($text, $runOffset);

        $edits = [];
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $onContext = $i === 0 && $previousWord !== null
                && in_array(WordIndex::fold($previousWord), self::PERSON_CONTEXT, true);

            $length = $this->personLength($tokens, $i, $onContext);

            if ($length > 0) {
                $edits[] = $this->edit($positions, $tokens, $i, $length, RedactionCategory::OtherPerson);
                $i += $length;

                continue;
            }

            $length = $this->localityLength($tokens, $i);

            if ($length === 0 && $this->singleLocalityAllowed($text, $tokens[$i], $positions[$i], $i === 0 && $atSentenceStart)) {
                $length = 1;
            }

            if ($length > 0) {
                $edits[] = $this->edit($positions, $tokens, $i, $length, RedactionCategory::Locality);
                $i += $length;

                continue;
            }

            $midSentence = $i > 0 || ! $atSentenceStart;

            if ($midSentence && ($this->dictionaries->isFirstName($tokens[$i]) || $this->dictionaries->isSurname($tokens[$i]))) {
                $this->suspicions[] = $tokens[$i];
            }

            $i++;
        }

        return $edits;
    }

    /**
     * How many tokens from $i form one person; 0 when the evidence is too weak.
     *
     * @param  array<int, string>  $tokens
     */
    private function personLength(array $tokens, int $i, bool $introducedByContext): int
    {
        $isName = fn (string $t) => $this->dictionaries->isFirstName($t) || $this->dictionaries->isSurname($t);

        if ($introducedByContext && $isName($tokens[$i])) {
            return $this->extendName($tokens, $i, $isName);
        }

        $next = $tokens[$i + 1] ?? null;

        if ($next === null) {
            return 0;
        }

        $firstThenAny = $this->dictionaries->isFirstName($tokens[$i]) && $isName($next);
        $surnameThenFirst = $this->dictionaries->isSurname($tokens[$i]) && $this->dictionaries->isFirstName($next);

        return ($firstThenAny || $surnameThenFirst) ? $this->extendName($tokens, $i, $isName) : 0;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  callable(string): bool  $isName
     */
    private function extendName(array $tokens, int $i, callable $isName): int
    {
        $length = 1;

        while (isset($tokens[$i + $length]) && $length < 4 && $isName($tokens[$i + $length])) {
            $length++;
        }

        return $length;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function localityLength(array $tokens, int $i): int
    {
        for ($length = min(4, count($tokens) - $i); $length >= 2; $length--) {
            if ($this->dictionaries->isLocalityPhrase(array_slice($tokens, $i, $length))) {
                return $length;
            }
        }

        return 0;
    }

    /**
     * A lone capitalised word is only a place when it is not merely the first
     * word of a sentence — "Kolano prawe bolesne" opens with a joint, not a
     * village. A letter header ("Kraków, 12.08.2026") is the one exception.
     */
    private function singleLocalityAllowed(string $text, string $token, int $position, bool $atSentenceStart): bool
    {
        if (! $this->dictionaries->isLocality($token)) {
            return false;
        }

        if (! $atSentenceStart) {
            return true;
        }

        $after = substr($text, $position + strlen($token), 24);

        return (bool) preg_match('/^\s*,\s*(?:dn\.?\s*)?\d/u', $after);
    }

    /**
     * @param  array<int, int>  $positions
     * @param  array<int, string>  $tokens
     * @return array{0: int, 1: int, 2: string, 3: RedactionCategory}
     */
    private function edit(array $positions, array $tokens, int $i, int $length, RedactionCategory $category): array
    {
        $start = $positions[$i];
        $last = $i + $length - 1;
        $end = $positions[$last] + strlen($tokens[$last]);

        return [$start, $end - $start, $category->token(), $category];
    }

    private function isSentenceStart(string $text, int $offset): bool
    {
        $before = rtrim(substr($text, 0, $offset), " \t");

        if ($before === '') {
            return true;
        }

        $last = substr($before, -1);

        if (in_array($last, ["\n", "\r", '!', '?', '•', '*', ')'], true) || $last === '-' || str_ends_with($before, '–')) {
            return true;
        }

        if ($last !== '.') {
            return false;
        }

        // "ul." and "dr." abbreviate a word, they do not end a sentence.
        preg_match('/(\p{L}+)\.$/u', $before, $m);

        return ! in_array(WordIndex::fold($m[1] ?? ''), self::ABBREVIATIONS, true);
    }

    private function wordBefore(string $text, int $offset): ?string
    {
        if (preg_match('/(\p{L}+)\.?\s+$/u', substr($text, 0, $offset), $m)) {
            return $m[1];
        }

        return null;
    }
}
