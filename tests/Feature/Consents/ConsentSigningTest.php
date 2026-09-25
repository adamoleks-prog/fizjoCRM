<?php

use App\Enums\DocumentType;
use App\Models\ConsentTemplate;
use App\Models\Document;
use App\Models\Patient;
use App\Models\SignedConsent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function signaturePng(bool $drawn = true): string
{
    $image = imagecreatetruecolor(300, 100);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    if ($drawn) {
        imagesetthickness($image, 4);
        imageline($image, 20, 70, 280, 30, imagecolorallocate($image, 17, 24, 39));
        imageline($image, 20, 30, 150, 80, imagecolorallocate($image, 17, 24, 39));
    }

    ob_start();
    imagepng($image);
    $png = ob_get_clean();

    return 'data:image/png;base64,'.base64_encode($png);
}

beforeEach(function () {
    Storage::fake('patient_documents');

    $this->operator = User::factory()->operator()->create(['practice_name' => 'FizjoRoom']);
    $this->patient = Patient::factory()->forOperator($this->operator)->create(['first_name' => 'Anna', 'last_name' => 'Nowak']);
});

it('creates starter templates and shows the signing screen with the patient filled in', function () {
    $this->actingAs($this->operator)
        ->get(route('consents.create', $this->patient))
        ->assertOk()
        ->assertSee('Klauzula informacyjna RODO')
        ->assertSee('Anna Nowak')
        ->assertSee('FizjoRoom')
        ->assertDontSee('{PACJENT}');

    expect(ConsentTemplate::where('operator_id', $this->operator->id)->count())->toBe(2);
});

it('stores the signed consent as a PDF document with evidence', function () {
    $this->actingAs($this->operator)->get(route('consents.create', $this->patient));
    $template = ConsentTemplate::where('name', 'Klauzula informacyjna RODO')->sole();

    $this->actingAs($this->operator)
        ->post(route('consents.store', $this->patient), [
            'consent_template_id' => $template->id,
            'signature' => signaturePng(),
            'read' => 1,
        ])
        ->assertRedirect(route('patients.show', $this->patient))
        ->assertSessionHasNoErrors();

    $document = Document::sole();
    $consent = SignedConsent::sole();

    expect($document->type)->toBe(DocumentType::Consent)
        ->and($document->ocr_text)->toContain('Anna Nowak')
        ->and($consent->witnessed_by_user_id)->toBe($this->operator->id)
        ->and($consent->body_hash)->toBe(hash('sha256', $document->ocr_text));

    expect(substr(Storage::disk('patient_documents')->get($document->disk_path), 0, 5))->toBe('%PDF-');
    expect($this->patient->accessLogs()->where('action', 'consent_signed')->count())->toBe(1);
});

it('rejects an empty signature or an unread consent', function () {
    $this->actingAs($this->operator)->get(route('consents.create', $this->patient));
    $template = ConsentTemplate::first();

    $this->actingAs($this->operator)
        ->post(route('consents.store', $this->patient), ['consent_template_id' => $template->id, 'signature' => signaturePng(false), 'read' => 1])
        ->assertSessionHasErrors('signature');

    $this->actingAs($this->operator)
        ->post(route('consents.store', $this->patient), ['consent_template_id' => $template->id, 'signature' => signaturePng()])
        ->assertSessionHasErrors('read');

    $this->actingAs($this->operator)
        ->post(route('consents.store', $this->patient), ['consent_template_id' => $template->id, 'signature' => 'data:image/png;base64,bm90IGEgcG5n', 'read' => 1])
        ->assertSessionHasErrors('signature');

    expect(Document::count())->toBe(0);
});

it('does not let another operator sign for the patient or use their templates', function () {
    $other = User::factory()->operator()->create();
    $this->actingAs($this->operator)->get(route('consents.create', $this->patient));
    $template = ConsentTemplate::first();

    $this->actingAs($other)->get(route('consents.create', $this->patient))->assertNotFound();
    $this->actingAs($other)->post(route('consents.store', $this->patient), [
        'consent_template_id' => $template->id, 'signature' => signaturePng(), 'read' => 1,
    ])->assertNotFound();

    $this->actingAs($other)
        ->put(route('consent-templates.update', $template), ['name' => 'X', 'body' => 'Y'])
        ->assertNotFound();
});

it('lets the physiotherapist edit templates without touching signed consents', function () {
    $this->actingAs($this->operator)->get(route('consent-templates.index'))->assertOk()->assertSee('Wzory startowe to szkic');
    $template = ConsentTemplate::first();

    $this->actingAs($this->operator)->post(route('consents.store', $this->patient), [
        'consent_template_id' => $template->id, 'signature' => signaturePng(), 'read' => 1,
    ]);
    $signedText = Document::sole()->ocr_text;

    $this->actingAs($this->operator)
        ->put(route('consent-templates.update', $template), ['name' => $template->name, 'body' => 'Nowa treść {PACJENT}.'])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->ocr_text)->toBe($signedText);
});
