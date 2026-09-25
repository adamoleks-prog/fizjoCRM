<?php

namespace App\Services\TherapySuggestion;

use App\Models\User;

/**
 * Builds the two messages sent to the model. The version is stored with every
 * recommendation, so a change to the prompt file needs a new version.
 */
class SuggestionPrompt
{
    public const VERSION = 'v1';

    public function system(User $physiotherapist): string
    {
        $template = file_get_contents(resource_path('prompts/therapy_suggestion_'.self::VERSION.'.md'));

        $profile = trim((string) $physiotherapist->competency_profile);

        return str_replace(
            '{{PROFIL_KOMPETENCJI}}',
            $profile !== '' ? $profile : 'Brak zadeklarowanych kursów specjalistycznych — proponuj wyłącznie metody podstawowe.',
            $template,
        );
    }

    /** The approved text, unchanged, inside the data markers the system prompt refers to. */
    public function user(string $approvedText): string
    {
        return "<dokumentacja>\n".$approvedText."\n</dokumentacja>";
    }
}
