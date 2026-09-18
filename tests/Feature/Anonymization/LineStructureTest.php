<?php

use App\Models\Patient;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;

/**
 * The review screen compares original and redacted text line by line, and the
 * model reads headings by their position — so redaction must never add or remove a
 * line break. Each case below puts an identifier at the very end of a line, which
 * is where a pattern that also matches whitespace would swallow the newline.
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

it('never changes the number of lines', function (string $document) {
    $result = $this->anonymizer->anonymize($document, $this->patient);

    expect(substr_count($result->text, "\n"))->toBe(substr_count($document, "\n"));
})->with([
    'phone at line end' => ["Tel. 602 118 940\nRozpoznanie: bóle kręgosłupa."],
    "the patient's phone written without spaces" => ["Tel. 602118940\nRozpoznanie: bóle kręgosłupa."],
    'a stranger phone at line end' => ["Kontakt: 500 100 200\nWywiad: ból."],
    'pesel at line end' => ["PESEL: 85032012348\nWywiad: ból."],
    'email at line end' => ["E-mail: anna.kowalska@example.com\nWywiad: ból."],
    'patient name at line end' => ["Pacjentka Anna Kowalska\nRozpoznanie: bóle kręgosłupa."],
    'a date at line end' => ["Data wizyty: 12.08.2026\nWywiad: ból."],
    'a locality at line end' => ["Dojeżdża z Kęt\nWywiad: ból."],
    'a person at line end' => ["Opiekuje się nią córka Marta Wiśniewska\nWywiad: ból."],
    'a doctor at line end' => ["Kierujący: dr n. med. Jan Zieliński\nRozpoznanie: bóle kręgosłupa."],
    'blank lines between sections' => ["PESEL: 85032012348\n\n\nWywiad: ból.\n\nZalecenia: ćwiczenia."],
    'windows line endings survive as text' => ["Tel. 602 118 940\r\nWywiad: ból."],
]);

it('keeps the line break after a phone number', function () {
    $result = $this->anonymizer->anonymize("Tel. 602 118 940\nRozpoznanie: bóle kręgosłupa.", $this->patient);

    expect($result->text)->toBe("Tel. [TELEFON]\nRozpoznanie: bóle kręgosłupa.");
});

it('does not treat a heading on the next line as part of a doctor', function () {
    $text = "Skierowanie wystawił lekarz\nBadanie Kliniczne wykazało ograniczenie ruchu.";

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)->toBe($text);
});

it('does not join a facility name with the line that follows it', function () {
    $result = $this->anonymizer->anonymize("Skierowanie z: Szpital\nWojewódzki oddział rehabilitacji", $this->patient);

    expect(substr_count($result->text, "\n"))->toBe(1);
});
