<?php

namespace App\Services\Anonymization;

use Illuminate\Support\HtmlString;

/**
 * Renders the two panes of the review screen.
 *
 * The text comes from uploaded documents, so it is untrusted: every piece goes
 * through e() and only the highlight wrappers built here are markup.
 */
class ReviewPresenter
{
    /**
     * @param  array<int, string>  $findings  fragments the reviewer should look at
     * @return array{original: HtmlString, outgoing: HtmlString}
     */
    public function render(string $original, string $outgoing, array $findings): array
    {
        [$left, $right] = WordDiff::segments($original, $outgoing);

        $flagged = $this->flaggedWords($findings);

        return [
            'original' => new HtmlString($this->paint($left, fn () => 'removed', $flagged, false)),
            'outgoing' => new HtmlString($this->paint($right, fn (string $text) => $this->classifyOutgoing($text), $flagged, true)),
        ];
    }

    /**
     * @param  array<int, array{text: string, changed: bool}>  $segments
     * @param  callable(string): string  $classify
     * @param  array<string, true>  $flagged
     */
    private function paint(array $segments, callable $classify, array $flagged, bool $markFindings): string
    {
        $html = '';

        foreach ($segments as $segment) {
            $text = $segment['text'];
            $safe = e($text);

            if ($markFindings && isset($flagged[$this->bare($text)])) {
                $html .= '<mark class="rv-finding">'.$safe.'</mark>';
            } elseif ($segment['changed']) {
                $html .= '<mark class="rv-'.$classify($text).'">'.$safe.'</mark>';
            } else {
                $html .= $safe;
            }
        }

        return $html;
    }

    /** A token such as [PACJENT] is a redaction; anything else changed is a shifted date or a hand edit. */
    private function classifyOutgoing(string $text): string
    {
        return preg_match('/^\[[\p{Lu}]+\]/u', $text) ? 'token' : 'shifted';
    }

    /**
     * @param  array<int, string>  $findings
     * @return array<string, true>
     */
    private function flaggedWords(array $findings): array
    {
        $words = [];

        foreach ($findings as $finding) {
            foreach (preg_split('/\s+/u', $finding, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                if (($bare = $this->bare($word)) !== '') {
                    $words[$bare] = true;
                }
            }
        }

        return $words;
    }

    private function bare(string $word): string
    {
        return trim($word, " \t\n\r.,;:!?()\"'„”");
    }
}
