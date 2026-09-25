<?php

namespace App\Services\TherapySuggestion;

interface SuggestionProvider
{
    /**
     * Sends the approved case and returns the raw model output.
     *
     * @throws SuggestionFailed with a generic reason — never the request or response body
     */
    public function suggest(string $systemPrompt, string $userMessage): SuggestionResponse;
}
