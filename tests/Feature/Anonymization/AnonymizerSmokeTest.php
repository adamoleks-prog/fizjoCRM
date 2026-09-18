<?php

use App\Models\Patient;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;

/**
 * Guards the three defects a manual read of the output caught after the first
 * green run: a surviving facility name, a birth year left unshifted by a small
 * offset, and one person rendered as two tokens.
 */
beforeEach(function () {
    $operator = User::factory()->operator()->create();

    $this->patient = Patient::factory()->forOperator($operator)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'phone' => '602 118 940',
        'email' => 'anna.kowalska@example.com',
        'address' => 'Lipowa 14/3, 32-600 Oświęcim',
        'date_offset_days' => -127,
    ]);

    $this->anonymizer = app(DocumentAnonymizer::class);
});

it('redacts a facility name that carries the town with it', function () {
    $result = $this->anonymizer->anonymize('Kierujący do NZOZ Medikor w sprawie rehabilitacji.', $this->patient);

    expect($result->text)->not->toContain('Medikor')->toContain('[PLACÓWKA]');
});

it('shifts a bare year even when the day offset rounds to less than a year', function () {
    // -127 days rounds to zero years; the shift must still move the year.
    $result = $this->anonymizer->anonymize('Poród w 2019 r.', $this->patient);

    expect($result->text)->not->toContain('2019');
});

it('does not shift the year inside a date it already shifted', function () {
    $result = $this->anonymizer->anonymize('Uraz 12.08.2026.', $this->patient);

    // -127 days from 12.08.2026 lands in April of the same year.
    expect($result->text)->toContain('07.04.2026');
});

it('renders one person as a single token, not two', function () {
    $result = $this->anonymizer->anonymize('Pacjent: Anna Kowalska zgłasza ból.', $this->patient);

    expect($result->text)
        ->toContain('[PACJENT] zgłasza')
        ->not->toContain('[PACJENT] [PACJENT]');
});

it('leaves the whole clinical line untouched', function () {
    $line = 'Rozpoznanie: bóle kręgosłupa lędźwiowego (M54.5)';

    $result = $this->anonymizer->anonymize($line, $this->patient);

    expect($result->text)->toBe($line);
});
