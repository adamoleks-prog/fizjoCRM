<?php

use App\Enums\AppointmentStatus;
use App\Enums\DocumentType;
use App\Enums\TextExtractionStatus;
use App\Models\AiClinicalCase;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\DocumentAnonymization;
use App\Models\MeasurementTemplate;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;
use App\Services\TherapySuggestion\ClinicalCaseAssembler;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();

    $this->patient = Patient::factory()->forOperator($this->operator)->create([
        'first_name' => 'Anna',
        'last_name' => 'Szczepaniak',
        'phone' => '602 118 940',
        'email' => null,
        'address' => null,
        'date_of_birth' => now()->subYears(47)->subMonths(2),
        'date_offset_days' => -30,
    ]);

    $this->cycle = TherapyCycle::factory()->forPatient($this->patient)->create([
        'name' => 'Rehabilitacja — Szczepaniak',
        'therapy_plan' => 'Stabilizacja odcinka lędźwiowego.',
    ]);

    $this->visit = Appointment::factory()->forCycle($this->cycle)->completed()->create([
        'starts_at' => '2026-09-01 10:00',
        'ends_at' => '2026-09-01 10:45',
        'interview' => 'Pani Szczepaniak zgłasza ból od 3 tygodni.',
        'internal_notes' => 'Pacjentka zdenerwowana, tel. 602 118 940.',
        'treatment_notes' => 'Terapia manualna.',
    ]);

    $this->visit->forceFill(['examination' => 'Ograniczone zgięcie tułowia.'])->save();

    $this->makeApprovedDocument = function (string $approvedText, DocumentType $type = DocumentType::Referral, string $status = 'approved'): Document {
        $document = Document::create([
            'patient_id' => $this->patient->id,
            'uploaded_by_user_id' => $this->operator->id,
            'disk_path' => 'patients/1/doc.pdf',
            'original_filename' => 'szczepaniak.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'type' => $type,
            'title' => 'Skierowanie Szczepaniak',
        ]);
        $document->forceFill(['ocr_text' => 'oryginał', 'text_extraction_status' => TextExtractionStatus::FromPdfLayer])->save();

        $record = new DocumentAnonymization([
            'anonymized_text' => $approvedText,
            'generated_text' => $approvedText,
            'source_hash' => hash('sha256', 'oryginał'),
            'redaction_report' => [],
            'status' => $status,
            'ruleset_version' => DocumentAnonymizer::RULESET_VERSION,
        ]);
        $record->operator_id = $this->operator->id;
        $record->document_id = $document->id;
        $record->save();

        return $document;
    };
});

/* ---------- assembling ---------- */

it('includes every documented section, the internal note included', function () {
    $source = app(ClinicalCaseAssembler::class)->assemble($this->cycle);

    expect($source->raw)
        ->toContain('Wiek: 45–49 lat')
        ->toContain('## PLAN TERAPII')
        ->toContain('Stabilizacja odcinka lędźwiowego.')
        ->toContain('## WIZYTA 1 — 01.09.2026')
        ->toContain('Rozpoznanie ICD-10: M54.5')
        ->toContain('Badanie stanu funkcjonowania:')
        ->toContain('Notatka wewnętrzna:')
        ->toContain('Wykonane zabiegi:');
});

it('never includes the cycle name or document titles', function () {
    ($this->makeApprovedDocument)('Skierowanie: ból lędźwiowy.');

    $source = app(ClinicalCaseAssembler::class)->assemble($this->cycle);

    expect($source->original())
        ->not->toContain('Rehabilitacja —')
        ->not->toContain('Skierowanie Szczepaniak')
        ->toContain('## DOKUMENT 1 (Skierowanie)');
});

it('uses an open-ended band from 90 years on', function () {
    $this->patient->update(['date_of_birth' => now()->subYears(93)]);

    expect(app(ClinicalCaseAssembler::class)->assemble($this->cycle)->raw)->toContain('Wiek: 90+ lat');
});

it('skips visits that did not take place', function () {
    Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-09-08 10:00',
        'ends_at' => '2026-09-08 10:45',
        'status' => AppointmentStatus::Cancelled,
        'interview' => 'odwołana',
    ]);

    expect(app(ClinicalCaseAssembler::class)->assemble($this->cycle)->raw)
        ->not->toContain('WIZYTA 2')
        ->not->toContain('odwołana');
});

it('lists measurements and pain points of a visit', function () {
    $template = new MeasurementTemplate(['name' => 'Ból NRS', 'type' => 'scale']);
    $template->operator_id = $this->operator->id;
    $template->save();

    $measurement = $this->visit->measurements()->make(['value_left' => 6]);
    $measurement->operator_id = $this->operator->id;
    $measurement->measurement_template_id = $template->id;
    $measurement->save();

    $point = $this->visit->painPoints()->make(['body_view' => 'back', 'position_x' => 50, 'position_y' => 60, 'note' => 'L5, promieniuje do pośladka']);
    $point->operator_id = $this->operator->id;
    $point->save();

    expect(app(ClinicalCaseAssembler::class)->assemble($this->cycle)->raw)
        ->toContain('- Ból NRS: 6/10')
        ->toContain('- Tył: L5, promieniuje do pośladka');
});

it('appends approved clinical documents only, verbatim', function () {
    ($this->makeApprovedDocument)('Rozpoznanie z [DATA] 03.08.2026.');
    ($this->makeApprovedDocument)('szkic niezatwierdzony', DocumentType::Referral, 'draft');
    ($this->makeApprovedDocument)('zgoda na zabiegi', DocumentType::Consent);

    $approved = app(ClinicalCaseAssembler::class)->assemble($this->cycle)->approved;

    expect($approved)
        ->toContain('Rozpoznanie z [DATA] 03.08.2026.')
        ->not->toContain('szkic niezatwierdzony')
        ->not->toContain('zgoda na zabiegi');
});

/* ---------- preparing and reviewing ---------- */

it('prepares a draft without identifiers and shifts only the raw part', function () {
    ($this->makeApprovedDocument)('Badanie z 03.08.2026.');

    $this->actingAs($this->operator)
        ->post(route('clinical-cases.store', $this->cycle))
        ->assertRedirect(route('clinical-cases.show', $this->cycle));

    $case = AiClinicalCase::sole();

    expect($case->status)->toBe('draft')
        ->and($case->anonymized_text)
        ->not->toContain('Szczepaniak')
        ->not->toContain('602 118 940')
        ->not->toContain('01.09.2026')   // visit date shifted by the patient offset
        ->toContain('02.08.2026')
        ->toContain('Badanie z 03.08.2026.'); // already shifted once — not a second time
});

it('shows the review screen and logs the access', function () {
    $this->actingAs($this->operator)->post(route('clinical-cases.store', $this->cycle));

    $this->actingAs($this->operator)
        ->get(route('clinical-cases.show', $this->cycle))
        ->assertOk()
        ->assertSee('Do wysłania');

    expect($this->patient->accessLogs()->where('action', 'viewed')->count())->toBe(1);
});

it('approves the case and marks it stale when a visit note changes', function () {
    $this->actingAs($this->operator)->post(route('clinical-cases.store', $this->cycle));

    $this->actingAs($this->operator)
        ->post(route('clinical-cases.approve', $this->cycle), ['acknowledge' => 1])
        ->assertSessionHasNoErrors();

    expect(AiClinicalCase::sole()->isApproved())->toBeTrue();

    $this->visit->update(['conclusions' => 'Nowy wniosek.']);

    $this->actingAs($this->operator)
        ->get(route('therapy-cycles.show', $this->cycle))
        ->assertSee('Nieaktualne');
});

it('becomes stale when a document approval is withdrawn', function () {
    $document = ($this->makeApprovedDocument)('Badanie z 03.08.2026.');

    $this->actingAs($this->operator)->post(route('clinical-cases.store', $this->cycle));
    $this->actingAs($this->operator)->post(route('clinical-cases.approve', $this->cycle), ['acknowledge' => 1]);

    $document->anonymization->update(['status' => 'draft']);

    $this->actingAs($this->operator)
        ->post(route('clinical-cases.approve', $this->cycle), ['acknowledge' => 1]);

    $this->actingAs($this->operator)
        ->get(route('therapy-cycles.show', $this->cycle))
        ->assertSee('Nieaktualne');
});

it('refuses approval when an identifier was typed back in', function () {
    $this->actingAs($this->operator)->post(route('clinical-cases.store', $this->cycle));

    $this->actingAs($this->operator)
        ->put(route('clinical-cases.update', $this->cycle), ['anonymized_text' => 'Pani Szczepaniak, tel. 602 118 940.'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->operator)
        ->post(route('clinical-cases.approve', $this->cycle), ['acknowledge' => 1])
        ->assertSessionHasErrors('approval');

    expect(AiClinicalCase::sole()->isApproved())->toBeFalse();
});

it('refuses to prepare a cycle with nothing documented', function () {
    $empty = TherapyCycle::factory()->forPatient($this->patient)->create();

    $this->actingAs($this->operator)
        ->post(route('clinical-cases.store', $empty))
        ->assertSessionHasErrors('case');
});

it('keeps another operator out of the cycle pages', function () {
    $other = User::factory()->operator()->create();

    $this->actingAs($this->operator)->post(route('clinical-cases.store', $this->cycle));

    $this->actingAs($other)->get(route('therapy-cycles.show', $this->cycle))->assertNotFound();
    $this->actingAs($other)->get(route('clinical-cases.show', $this->cycle))->assertNotFound();
    $this->actingAs($other)->post(route('clinical-cases.store', $this->cycle))->assertNotFound();
    $this->actingAs($other)->post(route('clinical-cases.approve', $this->cycle))->assertNotFound();
});
