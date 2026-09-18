<?php

use App\Models\Patient;
use App\Models\User;
use App\Services\Anonymization\DocumentAnonymizer;

beforeEach(function () {
    $operator = User::factory()->operator()->create();

    $this->patient = Patient::factory()->forOperator($operator)->create([
        'first_name' => 'Anna', 'last_name' => 'Kowalska',
        'phone' => null, 'email' => null, 'address' => null, 'date_offset_days' => -127,
    ]);

    $this->anonymizer = app(DocumentAnonymizer::class);
});

it('does not mistake a range of motion for a postal code', function (string $line) {
    $result = $this->anonymizer->anonymize($line, $this->patient);

    expect($result->text)->toBe($line);
})->with([
    'flexion range' => ['Zgięcie stawu kolanowego 10-120 stopni.'],
    'force range' => ['Siła chwytu 20-150 N.'],
    'ratio' => ['Zakres ruchu 15-140° w płaszczyźnie strzałkowej.'],
]);

it('still removes a real postal code followed by a town', function () {
    $result = $this->anonymizer->anonymize('Adres: ul. Długa 5, 32-600 Kęty', $this->patient);

    expect($result->text)->not->toContain('32-600');
});
