<?php

namespace App\Services\TherapySuggestion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to OpenRouter's chat completions endpoint.
 *
 * Every request pins the routing: only the EU endpoints listed in config, only
 * endpoints with zero data retention that do not collect data, only endpoints
 * that honour the response schema — and no fallback, so when none of them is
 * available the request fails instead of quietly going somewhere else.
 *
 * Nothing from the request or the response is logged or put into an exception.
 */
class OpenRouterClient implements SuggestionProvider
{
    public function suggest(string $systemPrompt, string $userMessage): SuggestionResponse
    {
        $config = config('services.openrouter');

        if (! $config['enabled']) {
            throw new SuggestionFailed('Wysyłanie do asystenta jest wyłączone.');
        }

        if (blank($config['api_key'])) {
            throw new SuggestionFailed('Brak klucza API OpenRouter w konfiguracji serwera.');
        }

        if ($config['providers'] === []) {
            throw new SuggestionFailed('Nie skonfigurowano dozwolonych dostawców w UE.');
        }

        try {
            $response = Http::withToken($config['api_key'])
                ->acceptJson()
                ->timeout($config['timeout'])
                ->withHeaders(['X-Title' => config('app.name')])
                ->post(rtrim($config['base_url'], '/').'/chat/completions', $this->body($config, $systemPrompt, $userMessage));
        } catch (ConnectionException) {
            throw new SuggestionFailed('Nie udało się połączyć z OpenRouter (przekroczony czas lub brak sieci).');
        }

        if ($response->failed()) {
            throw new SuggestionFailed('OpenRouter odrzucił zapytanie (HTTP '.$response->status().').');
        }

        $choice = $response->json('choices.0');

        if (! is_array($choice) || ! is_string($choice['message']['content'] ?? null)) {
            throw new SuggestionFailed('Odpowiedź OpenRouter nie zawiera treści.');
        }

        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new SuggestionFailed('Odpowiedź została ucięta — przekroczono limit długości.');
        }

        $usage = $response->json('usage') ?? [];

        return new SuggestionResponse(
            content: $choice['message']['content'],
            model: $response->json('model'),
            provider: $response->json('provider'),
            promptTokens: isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            completionTokens: isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            cost: isset($usage['cost']) ? (float) $usage['cost'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function body(array $config, string $systemPrompt, string $userMessage): array
    {
        return [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'podpowiedz_terapii',
                    'strict' => true,
                    'schema' => SuggestionSchema::jsonSchema(),
                ],
            ],
            'max_tokens' => $config['max_tokens'],
            'temperature' => 0.2,
            'usage' => ['include' => true],
            'provider' => [
                'only' => $config['providers'],
                'allow_fallbacks' => false,
                'data_collection' => 'deny',
                'zdr' => true,
                'require_parameters' => true,
            ],
        ];
    }
}
