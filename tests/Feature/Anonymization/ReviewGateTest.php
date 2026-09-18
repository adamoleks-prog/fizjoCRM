<?php

use App\Enums\DocumentType;
use App\Enums\TextExtractionStatus;
use App\Models\Document;
use App\Models\DocumentAnonymization;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\User;

const REFERRAL_TEXT = "SKIEROWANIE NA ZABIEGI\nPacjent: Anna Kowalska\nPESEL: 85032012348\nTel. 602 118 940\n"
    ."Rozpoznanie: bóle kręgosłupa lędźwiowego (M54.5)\nZgięcie kolana 10-120 stopni.\nWywiad od 12.08.2026.";

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();

    $this->patient = Patient::factory()->forOperator($this->operator)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'phone' => '602 118 940',
        'email' => null,
        'address' => null,
        'date_offset_days' => -127,
    ]);

    $this->makeDocument = function (
        string $text = REFERRAL_TEXT,
        DocumentType $type = DocumentType::Referral,
        TextExtractionStatus $status = TextExtractionStatus::FromPdfLayer,
    ): Document {
        $document = Document::create([
            'patient_id' => $this->patient->id,
            'uploaded_by_user_id' => $this->operator->id,
            'disk_path' => 'patients/1/doc.pdf',
            'original_filename' => 'skierowanie.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'type' => $type,
        ]);

        $document->forceFill(['ocr_text' => $text, 'text_extraction_status' => $status])->save();

        return $document;
    };
});

/* ---------- preparing the draft ---------- */

it('prepares a draft with the identifiers removed', function () {
    $document = ($this->makeDocument)();

    $this->actingAs($this->operator)
        ->post(route('anonymizations.store', $document))
        ->assertRedirect(route('anonymizations.show', $document));

    $record = DocumentAnonymization::sole();

    expect($record->status)->toBe('draft')
        ->and($record->anonymized_text)->not->toContain('Kowalska')
        ->not->toContain('85032012348')
        ->not->toContain('602 118 940')
        ->toContain('M54.5')
        ->and($record->generated_text)->toBe($record->anonymized_text)
        ->and($record->source_hash)->toBe(hash('sha256', REFERRAL_TEXT))
        ->and($record->redaction_report)->toHaveKey('pesel')
        ->and($record->operator_id)->toBe($this->operator->id);
});

it('keeps a range of motion intact through the whole gate', function () {
    $document = ($this->makeDocument)();

    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    expect(DocumentAnonymization::sole()->anonymized_text)->toContain('10-120 stopni');
});

it('refuses a document type that is never sent for analysis', function () {
    $document = ($this->makeDocument)(REFERRAL_TEXT, DocumentType::Consent);

    $this->actingAs($this->operator)
        ->post(route('anonymizations.store', $document))
        ->assertSessionHasErrors('document');

    expect(DocumentAnonymization::count())->toBe(0);
});

it('refuses a document nothing could be read from', function () {
    $document = ($this->makeDocument)('', DocumentType::Referral, TextExtractionStatus::NoText);

    $this->actingAs($this->operator)
        ->post(route('anonymizations.store', $document))
        ->assertSessionHasErrors('document');
});

it('replaces the draft instead of creating a second one when regenerated', function () {
    $document = ($this->makeDocument)();

    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    expect(DocumentAnonymization::count())->toBe(1);
});

it('discards manual edits and approval when regenerated', function () {
    $document = ($this->makeDocument)();

    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));
    $this->actingAs($this->operator)->post(route('anonymizations.approve', $document));
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $record = DocumentAnonymization::sole();

    expect($record->status)->toBe('draft')
        ->and($record->approved_at)->toBeNull()
        ->and($record->manual_edits)->toBe(0);
});

/* ---------- isolation ---------- */

it('keeps another operator out of every gate action', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $intruder = User::factory()->operator()->create();

    $this->actingAs($intruder)->post(route('anonymizations.store', $document))->assertForbidden();
    $this->actingAs($intruder)->get(route('anonymizations.show', $document))->assertForbidden();
    $this->actingAs($intruder)->put(route('anonymizations.update', $document), ['anonymized_text' => 'x'])->assertForbidden();
    $this->actingAs($intruder)->post(route('anonymizations.approve', $document))->assertForbidden();
    $this->actingAs($intruder)->delete(route('anonymizations.revoke', $document))->assertForbidden();
});

/* ---------- the review screen ---------- */

it('shows what was removed and what replaced it', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->get(route('anonymizations.show', $document))
        ->assertOk()
        ->assertSee('<mark class="rv-removed">Kowalska</mark>', false)
        ->assertSee('<mark class="rv-token">[PACJENT]</mark>', false);
});

it('records that the original text was viewed', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)->get(route('anonymizations.show', $document));

    expect(PatientAccessLog::where('patient_id', $this->patient->id)->where('action', 'viewed')->count())->toBe(1);
});

it('never lets document text inject markup into the page', function () {
    $hostile = 'Wywiad: <script>alert(1)</script> ból od tygodnia. Rozpoznanie: bóle kręgosłupa.';
    $document = ($this->makeDocument)($hostile);
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->get(route('anonymizations.show', $document))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('redirects to the patient when nothing has been prepared yet', function () {
    $document = ($this->makeDocument)();

    $this->actingAs($this->operator)
        ->get(route('anonymizations.show', $document))
        ->assertRedirect(route('patients.show', $this->patient));
});

/* ---------- manual edits ---------- */

it('saves a hand edit and counts the changed words', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $record = DocumentAnonymization::sole();
    $edited = str_replace('Wywiad', 'Wywiad kliniczny', $record->anonymized_text);

    $this->actingAs($this->operator)
        ->put(route('anonymizations.update', $document), ['anonymized_text' => $edited])
        ->assertSessionHasNoErrors();

    $record->refresh();

    expect($record->anonymized_text)->toBe($edited)
        ->and($record->generated_text)->not->toBe($edited)
        ->and($record->manual_edits)->toBeGreaterThan(0);
});

it('withdraws the approval when the text is edited afterwards', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));
    $this->actingAs($this->operator)->post(route('anonymizations.approve', $document));

    expect(DocumentAnonymization::sole()->isApproved())->toBeTrue();

    $this->actingAs($this->operator)
        ->put(route('anonymizations.update', $document), ['anonymized_text' => 'Zmieniony tekst kliniczny.']);

    $record = DocumentAnonymization::sole();

    expect($record->isApproved())->toBeFalse()
        ->and($record->approved_by_user_id)->toBeNull()
        ->and($record->mayBeSent())->toBeFalse();
});

it('refuses an empty text', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->put(route('anonymizations.update', $document), ['anonymized_text' => ''])
        ->assertSessionHasErrors('anonymized_text');
});

/* ---------- approval ---------- */

it('approves a clean draft and records who did it', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->post(route('anonymizations.approve', $document))
        ->assertSessionHasNoErrors();

    $record = DocumentAnonymization::sole();

    expect($record->mayBeSent())->toBeTrue()
        ->and($record->approved_by_user_id)->toBe($this->operator->id)
        ->and($record->approved_at)->not->toBeNull();
});

it('blocks approval when a hand edit typed an identifier back in', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->put(route('anonymizations.update', $document), ['anonymized_text' => 'Kontakt: 602 118 940, ból od tygodnia.']);

    $response = $this->actingAs($this->operator)->post(route('anonymizations.approve', $document));

    $response->assertSessionHasErrors('approval');

    expect(DocumentAnonymization::sole()->mayBeSent())->toBeFalse();
});

it('names the category in the error but never repeats the value', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->put(route('anonymizations.update', $document), ['anonymized_text' => 'Kontakt: 602 118 940.']);

    $this->actingAs($this->operator)->post(route('anonymizations.approve', $document));

    // Whatever shape the session keeps the errors in, the whole of it must carry the
    // category and none of the digits.
    $everything = json_encode(session('errors'), JSON_UNESCAPED_UNICODE);

    expect($everything)->toContain('Telefon')->not->toContain('602')->not->toContain('118');
});

it('blocks approval when the patient name was typed back in', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->put(route('anonymizations.update', $document), ['anonymized_text' => 'Pacjentka Kowalska zgłasza ból.']);

    $this->actingAs($this->operator)
        ->post(route('anonymizations.approve', $document))
        ->assertSessionHasErrors('approval');
});

it('needs an explicit acknowledgement while fragments are still flagged', function () {
    $document = ($this->makeDocument)('Zdaniem rodziny dolegliwości zgłaszał także Szczepaniak. Rozpoznanie: bóle kręgosłupa.');
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->post(route('anonymizations.approve', $document))
        ->assertSessionHasErrors('acknowledge');

    expect(DocumentAnonymization::sole()->mayBeSent())->toBeFalse();

    $this->actingAs($this->operator)
        ->post(route('anonymizations.approve', $document), ['acknowledge' => '1'])
        ->assertSessionHasNoErrors();

    expect(DocumentAnonymization::sole()->mayBeSent())->toBeTrue();
});

it('lists the flagged fragment on the review screen', function () {
    $document = ($this->makeDocument)('Zdaniem rodziny dolegliwości zgłaszał także Szczepaniak.');
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $this->actingAs($this->operator)
        ->get(route('anonymizations.show', $document))
        ->assertSee('Do sprawdzenia')
        ->assertSee('<mark class="rv-finding">Szczepaniak.</mark>', false);
});

it('refuses to approve a draft made from text that has since been re-read', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));

    $document->forceFill(['ocr_text' => REFERRAL_TEXT.' Nowy fragment po ponownym odczycie.'])->save();

    $this->actingAs($this->operator)
        ->post(route('anonymizations.approve', $document))
        ->assertSessionHasErrors('approval');

    $this->actingAs($this->operator)
        ->get(route('anonymizations.show', $document))
        ->assertSee('nieaktualna');
});

it('lets the reviewer withdraw an approval', function () {
    $document = ($this->makeDocument)();
    $this->actingAs($this->operator)->post(route('anonymizations.store', $document));
    $this->actingAs($this->operator)->post(route('anonymizations.approve', $document));

    $this->actingAs($this->operator)->delete(route('anonymizations.revoke', $document));

    expect(DocumentAnonymization::sole()->mayBeSent())->toBeFalse();
});

it('lets an admin review a document of any operator', function () {
    $document = ($this->makeDocument)();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('anonymizations.store', $document))->assertRedirect();

    expect(DocumentAnonymization::withoutGlobalScopes()->sole()->operator_id)->toBe($this->operator->id);
});
