<?php

namespace App\Services\Anonymization;

/**
 * Word level comparison of an original text and its redacted version.
 *
 * The redaction engine changes words in place and never moves a line break, so
 * the two texts can be compared line by line. Each side comes back as a list of
 * segments flagged as changed or not, which is all the review screen needs to
 * highlight what was removed and what replaced it.
 */
class WordDiff
{
    /** Above this many word pairs the exact comparison is skipped for that line. */
    private const MAX_CELLS = 2_000_000;

    /**
     * @return array{0: array<int, array{text: string, changed: bool}>, 1: array<int, array{text: string, changed: bool}>}
     */
    public static function segments(string $original, string $redacted): array
    {
        $left = explode("\n", $original);
        $right = explode("\n", $redacted);

        // A structural change (a person edited the line count) leaves nothing
        // reliable to align, so everything is shown as changed rather than guessed.
        if (count($left) !== count($right)) {
            return [
                [['text' => $original, 'changed' => true]],
                [['text' => $redacted, 'changed' => true]],
            ];
        }

        $a = [];
        $b = [];

        foreach ($left as $index => $line) {
            [$sa, $sb] = self::compareLine($line, $right[$index]);

            array_push($a, ...$sa);
            array_push($b, ...$sb);

            if ($index < count($left) - 1) {
                $a[] = ['text' => "\n", 'changed' => false];
                $b[] = ['text' => "\n", 'changed' => false];
            }
        }

        return [$a, $b];
    }

    /**
     * How many words differ — the larger of the two sides, so a replaced word
     * counts once and not twice.
     */
    public static function changedWords(string $original, string $redacted): int
    {
        [$a, $b] = self::segments($original, $redacted);

        $count = fn (array $segments) => count(array_filter(
            $segments,
            fn (array $s) => $s['changed'] && trim($s['text']) !== '',
        ));

        return max($count($a), $count($b));
    }

    /**
     * @return array{0: array<int, array{text: string, changed: bool}>, 1: array<int, array{text: string, changed: bool}>}
     */
    private static function compareLine(string $left, string $right): array
    {
        // Unchanged lines are still split into words: the review screen looks for
        // flagged fragments word by word, and an unchanged line is exactly where a
        // name the engine left in place sits.
        if ($left === $right) {
            $words = self::tokens($left);
            $unchanged = self::mark($words, array_fill_keys(array_keys($words), true));

            return [$unchanged, $unchanged];
        }

        $a = self::tokens($left);
        $b = self::tokens($right);

        $wordsA = array_keys(array_filter($a, fn (string $t) => trim($t) !== ''));
        $wordsB = array_keys(array_filter($b, fn (string $t) => trim($t) !== ''));

        [$keptA, $keptB] = self::commonWords($a, $b, $wordsA, $wordsB);

        return [self::mark($a, $keptA), self::mark($b, $keptB)];
    }

    /**
     * Longest common subsequence of words, after trimming the shared prefix and
     * suffix — nearly every line differs in one or two places, so the expensive
     * part is usually tiny.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @param  array<int, int>  $wordsA  positions of the words in $a
     * @param  array<int, int>  $wordsB
     * @return array{0: array<int, true>, 1: array<int, true>} positions kept unchanged
     */
    private static function commonWords(array $a, array $b, array $wordsA, array $wordsB): array
    {
        $keptA = [];
        $keptB = [];

        $start = 0;
        $endA = count($wordsA);
        $endB = count($wordsB);

        while ($start < $endA && $start < $endB && $a[$wordsA[$start]] === $b[$wordsB[$start]]) {
            $keptA[$wordsA[$start]] = true;
            $keptB[$wordsB[$start]] = true;
            $start++;
        }

        while ($endA > $start && $endB > $start && $a[$wordsA[$endA - 1]] === $b[$wordsB[$endB - 1]]) {
            $endA--;
            $endB--;
            $keptA[$wordsA[$endA]] = true;
            $keptB[$wordsB[$endB]] = true;
        }

        $midA = array_slice($wordsA, $start, $endA - $start);
        $midB = array_slice($wordsB, $start, $endB - $start);

        if ($midA === [] || $midB === [] || count($midA) * count($midB) > self::MAX_CELLS) {
            return [$keptA, $keptB];
        }

        $m = count($midA);
        $n = count($midB);
        $table = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$midA[$i]] === $b[$midB[$j]]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        for ($i = 0, $j = 0; $i < $m && $j < $n;) {
            if ($a[$midA[$i]] === $b[$midB[$j]]) {
                $keptA[$midA[$i]] = true;
                $keptB[$midB[$j]] = true;
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return [$keptA, $keptB];
    }

    /**
     * @return array<int, string> words and the whitespace between them, alternating
     */
    private static function tokens(string $line): array
    {
        return preg_split('/(\s+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, true>  $kept
     * @return array<int, array{text: string, changed: bool}>
     */
    private static function mark(array $tokens, array $kept): array
    {
        $segments = [];

        foreach ($tokens as $position => $token) {
            $isSpace = trim($token) === '';

            $segments[] = ['text' => $token, 'changed' => ! $isSpace && ! isset($kept[$position])];
        }

        return $segments;
    }
}
