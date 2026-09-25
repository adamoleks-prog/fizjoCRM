<?php

use App\Jobs\RequestTherapySuggestion;
use App\Models\AiClinicalCase;
use App\Models\AiRecommendation;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function validSuggestion(array $overrides = []): array
{
    return array_merge([
        'status' => 'mozna_zaproponowac_plan',
        'podsumowanie_kliniczne' => [['fakt' => 'Ból lędźwiowy od 3 tygodni', 'zrodlo' => 'WIZYTA 1, Wywiad']],
        'braki_w_danych' => [],
        'czerwone_flagi' => [],
        'cele' => ['krotkoterminowe' => ['Zmniejszenie bólu'], 'srednioterminowe' => [], 'dlugoterminowe' => []],
        'plan' => [[
            'etap' => 'Etap 1',
            'czas_trwania' => '2 tygodnie',
            'dzialania' => [['dzialanie' => 'Ćwiczenia stabilizacyjne', 'uzasadnienie' => 'Deficyt kontroli', 'warunki_bezpieczenstwa' => 'Bez bólu powyżej 5/10']],
            'kryteria_progresji' => ['Ból poniżej 3/10'],
            'kryteria_przerwania' => ['Objawy neurologiczne'],
        ]],
        'metody_specjalistyczne' => [],
        'miary_efektu' => [['miara' => 'NRS', 'jak_mierzyc' => 'Na każdej wizycie']],
        'wyjasnienie' => [['wniosek' => 'Plan stabilizacyjny', 'na_podstawie' => ['WIZYTA 1'], 'pewnosc' => 'srednia']],
        'pytania_do_terapeuty' => [],
    ], $overrides);
}

function openRouterReply(string $content, string $finishReason = 'stop'): array
{
    return [
        'model' => 'anthropic/claude-sonnet-5',
        'provider' => 'Amazon Bedrock',
        'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finishReason]],
        'usage' => ['prompt_tokens' => 1800, 'completion_tokens' => 900, 'cost' => 0.0126],
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.openrouter.enabled' => true,
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.providers' => ['amazon-bedrock/eu-west-1', 'google-vertex/europe'],
        'services.openrouter.daily_limit_per_operator' => 3,
    ]);

    $this->operator = User::factory()->operator()->create(['competency_profile' => 'Terapia Cyriax, suche igłowanie.']);

    $this->patient = Patient::factory()->forOperator($this->operator)->create([
        'first_name' => 'Anna',
        'last_name' => 'Szczepaniak',
        'email' => null,
        'address' => null,
        'date_offset_days' => -30,
    ]);

    $this->cycle = TherapyCycle::factory()->forPatient($this->patient)->create();

    $this->visit = Appointment::factory()->forCycle($this->cycle)->completed()->create([
        'interview' => 'Ból lędźwiowy od 3 tygodni.',
    ]);

    $this->actingAs($this->operator)->post(route('clinical-cases.store', $this->cycle));
    $this->actingAs($this->operator)->post(route('clinical-cases.approve', $this->cycle), ['acknowledge' => 1]);

    $this->case = AiClinicalCase::sole();
    expect($this->case->isApproved())->toBeTrue();
});

/* ---------- requesting ---------- */

it('sends exactly the approved text with EU-only, zero-retention routing', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(validSuggestion())))]);

    $this->actingAs($this->operator)
        ->post(route('recommendations.store', $this->cycle))
        ->assertRedirect();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $body['model'] === 'anthropic/claude-sonnet-5'
            && $body['provider'] === [
                'only' => ['amazon-bedrock/eu-west-1', 'google-vertex/europe'],
                'allow_fallbacks' => false,
                'data_collection' => 'deny',
                'zdr' => true,
                'require_parameters' => true,
            ]
            && $body['response_format']['type'] === 'json_schema'
            && $body['response_format']['json_schema']['strict'] === true
            && ! array_key_exists('temperature', $body)
            && $body['messages'][1]['content'] === "<dokumentacja>\n".$this->case->anonymized_text."\n</dokumentacja>"
            && str_contains($body['messages'][0]['content'], 'Terapia Cyriax, suche igłowanie.')
            && ! str_contains(json_encode($body, JSON_UNESCAPED_UNICODE), 'Szczepaniak');
    });

    $recommendation = AiRecommendation::sole();

    expect($recommendation->status)->toBe('completed')
        ->and($recommendation->response['plan'][0]['etap'])->toBe('Etap 1')
        ->and($recommendation->prompt_tokens)->toBe(1800)
        ->and($recommendation->completion_tokens)->toBe(900)
        ->and((float) $recommendation->cost)->toBe(0.0126)
        ->and($recommendation->provider)->toBe('Amazon Bedrock')
        ->and($recommendation->case_text_hash)->toBe($this->case->textHash());

    expect($this->patient->accessLogs()->where('action', 'sent_to_ai')->count())->toBe(1);
});

it('drops fields the schema does not know', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(validSuggestion(['diagnoza_lekarska' => 'X']))))]);

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    expect(AiRecommendation::sole()->response)->not->toHaveKey('diagnoza_lekarska');
});

it('sends nothing while the feature is disabled', function () {
    config(['services.openrouter.enabled' => false]);
    Http::fake();

    $this->actingAs($this->operator)
        ->post(route('recommendations.store', $this->cycle))
        ->assertSessionHasErrors('recommendation');

    Http::assertNothingSent();
    expect(AiRecommendation::count())->toBe(0);
});

it('sends nothing for a case that is not approved', function () {
    Http::fake();
    $this->actingAs($this->operator)->delete(route('clinical-cases.revoke', $this->cycle));

    $this->actingAs($this->operator)
        ->post(route('recommendations.store', $this->cycle))
        ->assertSessionHasErrors('recommendation');

    Http::assertNothingSent();
});

it('sends nothing for a stale case', function () {
    Http::fake();
    $this->visit->update(['conclusions' => 'Nowy wniosek.']);

    $this->actingAs($this->operator)
        ->post(route('recommendations.store', $this->cycle))
        ->assertSessionHasErrors('recommendation');

    Http::assertNothingSent();
});

it('enforces the daily limit per operator', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(validSuggestion())))]);

    foreach (range(1, 3) as $i) {
        $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle))->assertSessionHasNoErrors();
    }

    $this->actingAs($this->operator)
        ->post(route('recommendations.store', $this->cycle))
        ->assertSessionHasErrors('recommendation');

    Http::assertSentCount(3);
});

it('keeps another operator from requesting or reading suggestions', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(validSuggestion())))]);
    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));
    $recommendation = AiRecommendation::sole();

    $other = User::factory()->operator()->create();

    $this->actingAs($other)->post(route('recommendations.store', $this->cycle))->assertNotFound();
    $this->actingAs($other)->get(route('recommendations.show', $recommendation))->assertNotFound();
    $this->actingAs($other)->patch(route('recommendations.decide', $recommendation), ['decision' => 'useful'])->assertNotFound();

    Http::assertSentCount(1);
});

it('queues the job instead of calling out during the request', function () {
    Queue::fake();

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    Queue::assertPushed(RequestTherapySuggestion::class);
    expect(AiRecommendation::sole()->status)->toBe('queued');
});

/* ---------- the job ---------- */

it('refuses to send when the case was edited after the request', function () {
    Queue::fake();
    Http::fake();

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));
    $recommendation = AiRecommendation::sole();

    // Edited and re-approved while the job was waiting: approved, but not what was requested.
    $this->actingAs($this->operator)->put(route('clinical-cases.update', $this->cycle), ['anonymized_text' => 'Inny tekst o bólu.']);
    $this->actingAs($this->operator)->post(route('clinical-cases.approve', $this->cycle), ['acknowledge' => 1]);

    app()->call([new RequestTherapySuggestion($recommendation), 'handle']);

    Http::assertNothingSent();
    expect($recommendation->fresh()->status)->toBe('failed');
});

it('fails without retrying on invalid JSON, keeping the cost', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply('To nie jest JSON'))]);

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    $recommendation = AiRecommendation::sole();

    expect($recommendation->status)->toBe('failed')
        ->and($recommendation->failure_reason)->toContain('JSON')
        ->and((float) $recommendation->cost)->toBe(0.0126);

    Http::assertSentCount(1);
});

it('fails when the answer does not match the schema', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(validSuggestion(['status' => 'diagnoza']))))]);

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    expect(AiRecommendation::sole())
        ->status->toBe('failed')
        ->response->toBeNull();
});

it('fails on a truncated answer', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply('{"status":', 'length'))]);

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    expect(AiRecommendation::sole()->status)->toBe('failed');
});

it('fails with a generic reason on an HTTP error, never echoing the body', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'No endpoints found matching your data policy']], 404)]);

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    $recommendation = AiRecommendation::sole();

    expect($recommendation->status)->toBe('failed')
        ->and($recommendation->failure_reason)->toBe('OpenRouter odrzucił zapytanie (HTTP 404): No endpoints found matching your data policy.')
        ->and($recommendation->failure_reason)->not->toContain($this->case->anonymized_text);

    Http::assertSentCount(1);
});

/* ---------- the page and the verdict ---------- */

it('shows the suggestion with the disclaimer and records the verdict', function () {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(validSuggestion())))]);
    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));
    $recommendation = AiRecommendation::sole();

    $this->actingAs($this->operator)
        ->get(route('recommendations.show', $recommendation))
        ->assertOk()
        ->assertSee('Podpowiedź do oceny przez fizjoterapeutę')
        ->assertSee('Ćwiczenia stabilizacyjne');

    $this->actingAs($this->operator)
        ->patch(route('recommendations.decide', $recommendation), ['decision' => 'useful', 'decision_note' => 'Trafne.'])
        ->assertRedirect();

    expect($recommendation->fresh())
        ->decision->toBe('useful')
        ->decision_note->toBe('Trafne.')
        ->decided_by_user_id->toBe($this->operator->id);
});

it('shows the disabled banner instead of sending when the flag is off', function () {
    config(['services.openrouter.enabled' => false]);

    $this->actingAs($this->operator)
        ->get(route('therapy-cycles.show', $this->cycle))
        ->assertOk()
        ->assertSee('Wysyłanie do asystenta jest wyłączone');
});

it('does not repeat a provider error that quotes the case text', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Invalid input near: '.mb_substr($this->case->anonymized_text, 0, 120)]], 400)]);

    $this->actingAs($this->operator)->post(route('recommendations.store', $this->cycle));

    expect(AiRecommendation::sole()->failure_reason)->toBe('OpenRouter odrzucił zapytanie (HTTP 400).');
});
