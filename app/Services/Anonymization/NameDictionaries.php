<?php

namespace App\Services\Anonymization;

/**
 * Loads the reference lists once per process — the surname list alone is tens of
 * thousands of entries, too costly to rebuild for every document.
 */
class NameDictionaries
{
    private WordIndex $firstNames;

    private WordIndex $surnames;

    private WordIndex $localities;

    private WordIndex $stoplist;

    public function __construct(?string $directory = null)
    {
        $directory ??= config('documents.dictionary_path', resource_path('dictionaries'));

        $this->firstNames = WordIndex::fromFile($directory.'/first_names.txt');
        $this->surnames = WordIndex::fromFile($directory.'/surnames.txt');
        $this->localities = WordIndex::fromFile($directory.'/localities.txt');
        $this->stoplist = WordIndex::fromFile($directory.'/clinical_stoplist.txt');
    }

    /**
     * Anatomy, clinical vocabulary and medical eponyms. Checked before anything
     * else: a word on this list is never a person or a place.
     */
    public function isProtected(string $token): bool
    {
        return $this->stoplist->matches($token, 3);
    }

    public function isFirstName(string $token): bool
    {
        return ! $this->isProtected($token) && $this->firstNames->matches($token, 3);
    }

    public function isSurname(string $token): bool
    {
        return ! $this->isProtected($token) && $this->surnames->matches($token, 4);
    }

    public function isLocality(string $token): bool
    {
        return ! $this->isProtected($token) && $this->localities->matches($token, 4);
    }

    /**
     * Multi-word names ("Nowy Sącz", "Bielsko-Biała"). Only the last word is
     * matched through declension — the earlier ones would need per-word rules
     * ("Nowego Sącza"), which is not worth the false positives.
     *
     * @param  array<int, string>  $tokens
     */
    public function isLocalityPhrase(array $tokens): bool
    {
        if (count($tokens) < 2) {
            return false;
        }

        foreach ($tokens as $token) {
            if ($this->isProtected($token)) {
                return false;
            }
        }

        $head = array_map(fn (string $t) => WordIndex::fold($t), array_slice($tokens, 0, -1));
        $last = end($tokens);

        foreach ([' ', '-'] as $glue) {
            $prefix = implode($glue, $head).$glue;

            if ($this->localities->has($prefix.$last)) {
                return true;
            }
        }

        // Last word declined: try it against the stem of the same-shaped names.
        foreach ([' ', '-'] as $glue) {
            $prefix = implode($glue, $head).$glue;

            foreach ($this->declinedForms($last) as $form) {
                if ($this->localities->has($prefix.$form)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function declinedForms(string $token): array
    {
        $folded = WordIndex::fold($token);
        $forms = [];

        foreach (['iego', 'ego', 'iej', 'ej', 'ie', 'iu', 'ym', 'im', 'em', 'ą', 'a', 'y', 'e', 'u', 'i'] as $ending) {
            if (str_ends_with($folded, $ending) && strlen($folded) - strlen($ending) >= 3) {
                $base = substr($folded, 0, -strlen($ending));
                array_push($forms, $base, $base.'a', $base.'y', $base.'o', $base.'e', $base.'i');
            }
        }

        return array_values(array_unique($forms));
    }
}
