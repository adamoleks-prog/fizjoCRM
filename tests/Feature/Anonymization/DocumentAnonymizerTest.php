<?php

use App\Enums\RedactionCategory;
use App\Models\Patient;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;

/**
 * Synthetic documents only — never real patient text. Every identifier below is
 * made up, which is what lets the suite assert that none of them survive.
 */
beforeEach(function () {
    $this->operator = User::factory()->operator()->create();

    $this->patient = Patient::factory()->forOperator($this->operator)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'phone' => '602 118 940',
        'email' => 'anna.kowalska@example.com',
        'address' => 'Lipowa 14/3, 32-600 Oświęcim',
        'date_offset_days' => -127,
    ]);

    $this->anonymizer = app(DocumentAnonymizer::class);
});

it('removes every critical identifier from a realistic referral', function () {
    $text = <<<'TXT'
    SKIEROWANIE NA ZABIEGI FIZJOTERAPEUTYCZNE
    Pacjent: Anna Kowalska
    PESEL: 85032012348
    Adres: ul. Lipowa 14/3, 32-600 Oświęcim
    Tel. 602 118 940, e-mail: anna.kowalska@example.com
    Rozpoznanie: bóle kręgosłupa lędźwiowego (M54.5)
    Wywiad: pani Kowalskiej dolegliwości od 12.08.2026.
    TXT;

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)
        ->not->toContain('Kowalska')
        ->not->toContain('Kowalskiej')
        ->not->toContain('85032012348')
        ->not->toContain('602 118 940')
        ->not->toContain('anna.kowalska@example.com')
        ->not->toContain('Lipowa')
        ->not->toContain('32-600')
        ->not->toContain('Oświęcim');
});

it('keeps the clinical content that the model actually needs', function () {
    $text = 'Rozpoznanie: bóle kręgosłupa lędźwiowego (M54.5). Zalecenia: terapia manualna.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)
        ->toContain('M54.5')
        ->toContain('kręgosłupa')
        ->toContain('terapia manualna');
});

it('catches the surname through polish declension', function () {
    $text = 'Zbadano Kowalską. Stan Kowalskiej stabilny. Wywiad z Kowalską przeprowadzono.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)->not->toContain('Kowalsk')
        ->and($result->count(RedactionCategory::Patient))->toBe(3);
});

it('validates the pesel checksum instead of blanking any eleven digits', function () {
    // First is a valid PESEL, second is an eleven digit sequence that is not one.
    $text = 'PESEL 85032012348 oraz numer partii 12345678901';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)
        ->not->toContain('85032012348')
        ->toContain('12345678901')
        ->and($result->count(RedactionCategory::Pesel))->toBe(1);
});

it('survives ocr confusing digits in a phone number', function () {
    // OCR read 0 as O and 1 as l.
    $text = 'Kontakt: 6O2 118 94O';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)->not->toContain('6O2');
});

it('shifts dates while preserving the interval between them', function () {
    $text = 'Uraz 01.03.2026, operacja 15.03.2026.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    preg_match_all('/(\d{2})\.(\d{2})\.(\d{4})/', $result->text, $m, PREG_SET_ORDER);

    expect($m)->toHaveCount(2);

    $first = Carbon\Carbon::createFromFormat('d.m.Y', $m[0][0]);
    $second = Carbon\Carbon::createFromFormat('d.m.Y', $m[1][0]);

    expect($first->diffInDays($second))->toBe(14.0)
        ->and($result->text)->not->toContain('01.03.2026');
});

it('uses one stable offset for every document of the same patient', function () {
    $first = $this->anonymizer->anonymize('Data 01.03.2026', $this->patient);
    $second = $this->anonymizer->anonymize('Data 01.03.2026', $this->patient);

    expect($first->text)->toBe($second->text);
});

it('generates an offset for a patient that does not have one yet', function () {
    $patient = Patient::factory()->forOperator($this->operator)->create(['date_offset_days' => null]);

    $this->anonymizer->anonymize('Data 01.03.2026', $patient);

    expect($patient->fresh()->date_offset_days)->not->toBeNull()
        ->and($patient->fresh()->date_offset_days)->not->toBe(0);
});

it('redacts the referring doctor', function () {
    $text = 'Kierujący: dr n. med. Jan Zieliński';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)->not->toContain('Zieliński')
        ->and($result->count(RedactionCategory::Doctor))->toBe(1);
});

it('reports counts per category and never the removed values', function () {
    $text = 'Anna Kowalska, PESEL 85032012348, tel. 602 118 940';

    $result = $this->anonymizer->anonymize($text, $this->patient);
    $encoded = json_encode($result->report);

    expect($result->count(RedactionCategory::Pesel))->toBe(1)
        ->and($encoded)->not->toContain('85032012348')
        ->and($encoded)->not->toContain('Kowalska')
        ->and($result->totalRedactions())->toBeGreaterThan(2);
});

it('removes a third party the patient record knows nothing about', function () {
    $text = 'Opiekuje się nią córka Marta Wiśniewska, która przywozi ją na zabiegi.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)
        ->not->toContain('Marta')
        ->not->toContain('Wiśniewska')
        ->toContain('przywozi ją na zabiegi')
        ->and($result->count(RedactionCategory::OtherPerson))->toBe(1);
});

it('refuses to call the result safe when a lone surname is left unresolved', function () {
    // A bare surname is too weak a signal to delete, but too strong to ignore.
    $text = 'Zdaniem rodziny dolegliwości zgłaszał także Szczepaniak podczas wizyty.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->isHighConfidence())->toBeFalse()
        ->and($result->suspicions)->toContain('Szczepaniak');
});

it('is confident when nothing name-shaped is left', function () {
    $text = 'Rozpoznanie: bóle kręgosłupa. Zalecenia: ćwiczenia wzmacniające.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->isHighConfidence())->toBeTrue();
});

it('replaces identifiers with typed tokens so the sentence still reads', function () {
    $text = 'Pacjent: Anna Kowalska zgłasza ból.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)->toContain('[PACJENT]')
        ->and($result->text)->toContain('zgłasza ból');
});
