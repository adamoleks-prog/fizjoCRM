<?php

namespace App\Jobs;

use App\Models\AiRecommendation;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use App\Services\TherapySuggestion\ClinicalCaseService;
use App\Services\TherapySuggestion\SuggestionFailed;
use App\Services\TherapySuggestion\SuggestionPrompt;
use App\Services\TherapySuggestion\SuggestionProvider;
use App\Services\TherapySuggestion\SuggestionResponse;
use App\Services\TherapySuggestion\SuggestionSchema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

/**
 * Sends one approved clinical case for a suggestion.
 *
 * Exactly one attempt: a retry would send the medical text again and pay for it
 * again, so trying once more is the physiotherapist's decision (a new request).
 */
class RequestTherapySuggestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public AiRecommendation $recommendation)
    {
        $this->timeout = (int) config('services.openrouter.timeout') + 30;
    }

    public function handle(ClinicalCaseService $cases, SuggestionProvider $provider, SuggestionPrompt $prompt): void
    {
        $recommendation = $this->recommendation;

        if (! $recommendation->isQueued()) {
            return;
        }

        if (! config('services.openrouter.enabled')) {
            $this->markFailed($recommendation, 'Wysyłanie do asystenta jest wyłączone.');

            return;
        }

        $case = $recommendation->clinicalCase()->withoutGlobalScope(OperatorScope::class)->first();
        $cycle = $recommendation->therapyCycle()->withoutGlobalScope(OperatorScope::class)->first();

        // Checked again here, not only when the request was made: the case may have
        // been edited, revoked or outdated while this job waited in the queue, and
        // then the text about to go out is not the text that was approved.
        if (! $case || ! $cycle
            || ! $cases->isSendable($case, $cycle)
            || $case->textHash() !== $recommendation->case_text_hash) {
            $this->markFailed($recommendation, 'Dane przypadku zmieniły się po zleceniu. Niczego nie wysłano — przejrzyj je i zleć ponownie.');

            return;
        }

        $physiotherapist = User::find($cycle->operator_id);

        try {
            $response = $provider->suggest($prompt->system($physiotherapist), $prompt->user($case->anonymized_text));
        } catch (SuggestionFailed $e) {
            $this->markFailed($recommendation, $e->getMessage());

            return;
        }

        $this->recordUsage($recommendation, $response);

        try {
            $data = json_decode($response->content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->markFailed($recommendation, 'Odpowiedź modelu nie jest poprawnym JSON-em.');

            return;
        }

        $validator = Validator::make(is_array($data) ? $data : [], SuggestionSchema::rules());

        if (! is_array($data) || $validator->fails()) {
            $this->markFailed($recommendation, 'Odpowiedź modelu nie jest zgodna z oczekiwanym schematem.');

            return;
        }

        $recommendation->response = $validator->validated();
        $recommendation->status = 'completed';
        $recommendation->save();
    }

    /** Anything unexpected ends the same way — a generic reason, never the exception text. */
    public function failed(?Throwable $exception): void
    {
        $recommendation = $this->recommendation->fresh();

        if ($recommendation?->isQueued()) {
            $this->markFailed($recommendation, 'Nieoczekiwany błąd podczas przygotowania podpowiedzi.');
        }
    }

    /** Tokens are paid for even when the answer is then rejected, so they are kept. */
    private function recordUsage(AiRecommendation $recommendation, SuggestionResponse $response): void
    {
        $recommendation->provider = $response->provider;
        $recommendation->model = $response->model ?? $recommendation->model;
        $recommendation->prompt_tokens = $response->promptTokens;
        $recommendation->completion_tokens = $response->completionTokens;
        $recommendation->cost = $response->cost;
    }

    private function markFailed(AiRecommendation $recommendation, string $reason): void
    {
        $recommendation->status = 'failed';
        $recommendation->failure_reason = $reason;
        $recommendation->save();
    }
}
