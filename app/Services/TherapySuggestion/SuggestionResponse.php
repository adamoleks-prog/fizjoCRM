<?php

namespace App\Services\TherapySuggestion;

class SuggestionResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $model,
        public readonly ?string $provider,
        public readonly ?int $promptTokens,
        public readonly ?int $completionTokens,
        public readonly ?float $cost,
    ) {}
}
