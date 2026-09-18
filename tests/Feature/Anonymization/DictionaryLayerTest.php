<?php

use App\Enums\RedactionCategory;
use App\Models\Patient;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;

/**
 * The dictionaries are large and full of ordinary words: Kolano, Bark and Krzyż
 * are villages, Parkinson and Baker are surnames. These tests hold both halves of
 * the promise — the identifiers go, the clinical text stays.
 */
beforeEach(function () {
    $operator = User::factory()->operator()->create();

    $this->patient = Patient::factory()->forOperator($operator)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'phone' => '602 118 940',
        'email' => null,
        'address' => 'Lipowa 14/3, 32-600 Oświęcim',
        'date_offset_days' => -127,
    ]);

    $this->anonymizer = app(DocumentAnonymizer::class);
});

/* ---------- what must disappear ---------- */

it('removes a locality that appears outside the address', function () {
    $result = $this->anonymizer->anonymize('Pacjentka dojeżdża z Kęt na zabiegi.', $this->patient);

    expect($result->text)->not->toContain('Kęt')
        ->and($result->count(RedactionCategory::Locality))->toBe(1);
});

/*
 * The patient's own address contains Oświęcim, so layer 1 would remove it and the
 * test would prove nothing about the register. Every place below is deliberately
 * one the patient record does not mention.
 */
it('removes a locality through declension', function (string $sentence, string $gone) {
    $result = $this->anonymizer->anonymize($sentence, $this->patient);

    expect($result->text)->not->toContain($gone)
        ->and($result->count(RedactionCategory::Locality))->toBe(1);
})->with([
    'locative -ie' => ['Mieszka w Tarnowie od dziesięciu lat.', 'Tarnowie'],
    'locative -iu' => ['Mieszka w Rybniku od dziesięciu lat.', 'Rybniku'],
    'locative plural -ach' => ['Pracuje w Wadowicach jako księgowa.', 'Wadowicach'],
    'genitive -a' => ['Pochodzi z okolic Rybnika.', 'Rybnika'],
    'instrumental -em' => ['Zajmuje się nią lekarz z Krakowem w tle.', 'Krakowem'],
]);

it('keeps the sentence around a removed locality intact', function () {
    $result = $this->anonymizer->anonymize('Mieszka w Tarnowie od dziesięciu lat.', $this->patient);

    expect($result->text)->toContain('Mieszka w')->toContain('od dziesięciu lat');
});

it('removes a multi word locality', function () {
    $result = $this->anonymizer->anonymize('Pochodzi z okolic Nowy Sącz i pracuje zdalnie.', $this->patient);

    expect($result->text)->not->toContain('Sącz');
});

it('removes a locality even when ocr dropped the polish letters', function () {
    $result = $this->anonymizer->anonymize('Pacjentka dojezdza z Krakowa.', $this->patient);

    expect($result->text)->not->toContain('Krakowa');
});

it('removes the town in a letter header', function () {
    $result = $this->anonymizer->anonymize("Kraków, 12.08.2026\nSkierowanie na zabiegi", $this->patient);

    expect($result->text)->not->toContain('Kraków');
});

it('removes a first name and surname pair the record does not know', function () {
    $result = $this->anonymizer->anonymize('Wywiad przeprowadzono w obecności Piotra Nowaka.', $this->patient);

    expect($result->text)->not->toContain('Piotra')->not->toContain('Nowaka');
});

it('removes a person named through a family relation', function () {
    $result = $this->anonymizer->anonymize('Do wizyt przywozi ją syn Tomasz.', $this->patient);

    expect($result->text)->not->toContain('Tomasz')->toContain('przywozi ją');
});

it('knows first names of the older generation', function () {
    $result = $this->anonymizer->anonymize('Opiekuje się nią siostra Genowefa.', $this->patient);

    expect($result->text)->not->toContain('Genowefa');
});

/* ---------- what must survive ---------- */

it('keeps anatomical words that are also village names', function (string $sentence, string $kept) {
    $result = $this->anonymizer->anonymize($sentence, $this->patient);

    expect($result->text)->toContain($kept);
})->with([
    'kolano at the start of a sentence' => ['Kolano prawe bolesne przy zgięciu.', 'Kolano prawe'],
    'bark at the start of a sentence' => ['Bark lewy z ograniczeniem ruchu.', 'Bark lewy'],
    'krzyż after a colon' => ['Lokalizacja: Krzyż i odcinek lędźwiowy.', 'Krzyż'],
    'staw after a colon' => ['Badanie: Staw skokowy stabilny.', 'Staw skokowy'],
    'uraz after a colon' => ['Rozpoznanie: Uraz kolana po upadku.', 'Uraz kolana'],
]);

it('keeps medical eponyms that are also surnames', function (string $sentence, string $kept) {
    $result = $this->anonymizer->anonymize($sentence, $this->patient);

    expect($result->text)->toContain($kept)
        ->and($result->isHighConfidence())->toBeTrue();
})->with([
    'parkinson' => ['Wywiad w kierunku choroby Parkinsona negatywny.', 'Parkinsona'],
    'baker' => ['USG wykazało torbiel Bakera w dole podkolanowym.', 'Bakera'],
    'lachman' => ['Test Lachmana ujemny, test szuflady ujemny.', 'Lachmana'],
    'bechterew' => ['Wykluczono zesztywniające zapalenie stawów typu Bechterewa.', 'Bechterewa'],
    'lasegue' => ['Objaw Lasègue’a dodatni po stronie lewej.', 'Lasègue'],
]);

it('keeps the whole clinical picture when a document mixes both', function () {
    $text = 'Kolano prawe bolesne, test Lachmana ujemny. Pacjentka dojeżdża z Kęt, opiekuje się nią córka Marta.';

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->text)
        ->toContain('Kolano prawe bolesne')
        ->toContain('Lachmana ujemny')
        ->not->toContain('Kęt')
        ->not->toContain('Marta');
});

it('does not flag ordinary capitalised headings as people', function () {
    $text = "Wywiad: ból od trzech tygodni.\nBadanie: ograniczona ruchomość.\nZalecenia: ćwiczenia domowe.";

    $result = $this->anonymizer->anonymize($text, $this->patient);

    expect($result->isHighConfidence())->toBeTrue()
        ->and($result->totalRedactions())->toBe(0);
});

it('does not touch an abbreviation followed by a name-like word', function () {
    // "ul." ends in a full stop but does not end the sentence.
    $result = $this->anonymizer->anonymize('Mieszka przy ul. Lipowej, blisko przychodni.', $this->patient);

    expect($result->text)->toContain('blisko przychodni');
});
