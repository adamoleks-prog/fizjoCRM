<?php

use App\Models\Appointment;
use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\Icd10CodeSeeder;

beforeEach(function () {
    $this->seed(Icd10CodeSeeder::class);
    $this->operator = User::factory()->operator()->create();
});

it('finds a diagnosis by icd10 code', function () {
    $response = $this->actingAs($this->operator)
        ->getJson(route('icd10.search', ['q' => 'M54.3']));

    $response->assertOk();

    expect($response->json())->not->toBeEmpty()
        ->and($response->json('0.code'))->toBe('M54.3')
        ->and($response->json('0.name'))->toContain('Rwa kulszowa');
});

it('finds diagnoses by a code prefix', function () {
    $response = $this->actingAs($this->operator)
        ->getJson(route('icd10.search', ['q' => 'M75']));

    $codes = collect($response->json())->pluck('code');

    expect($codes)->toContain('M75', 'M75.0', 'M75.1', 'M75.4');
});

it('finds a diagnosis by its polish name', function () {
    $response = $this->actingAs($this->operator)
        ->getJson(route('icd10.search', ['q' => 'cieśni nadgarstka']));

    expect($response->json('0.code'))->toBe('G56.0');
});

it('limits the number of returned results', function () {
    $response = $this->actingAs($this->operator)->getJson(route('icd10.search', ['q' => 'M']));

    expect(count($response->json()))->toBeLessThanOrEqual(30);
});

it('requires authentication', function () {
    $this->getJson(route('icd10.search', ['q' => 'M54']))->assertUnauthorized();
});

it('saves a diagnosis from the dictionary on an appointment', function () {
    $patient = Patient::factory()->forOperator($this->operator)->create();
    $appointment = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $patient->id,
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
    ]);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $appointment), [
            'starts_at' => '2026-10-05 09:00:00',
            'duration_minutes' => 30,
            'status' => 'completed',
            'icd10_code' => 'M54.3',
            'procedures' => 'Terapia manualna',
        ])
        ->assertSessionHasNoErrors();

    expect($appointment->fresh()->icd10_code)->toBe('M54.3')
        ->and($appointment->fresh()->icd10Name())->toContain('Rwa kulszowa');
});

it('rejects a diagnosis code that is not in the dictionary', function () {
    $patient = Patient::factory()->forOperator($this->operator)->create();
    $appointment = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $patient->id,
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
    ]);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $appointment), [
            'starts_at' => '2026-10-05 09:00:00',
            'duration_minutes' => 30,
            'status' => 'completed',
            'icd10_code' => 'XX99.9',
        ])
        ->assertSessionHasErrors('icd10_code');
});

it('imports codes from a csv file', function () {
    $path = sys_get_temp_dir().'/icd10-test.csv';
    file_put_contents($path, "kod;nazwa;rozdzial\nA00;Cholera;Choroby zakaźne\nA01;Dury brzuszne;Choroby zakaźne\n");

    $this->artisan('icd10:import', ['file' => $path, '--skip-header' => true])
        ->assertSuccessful();

    expect(Icd10Code::where('code', 'A00')->value('name'))->toBe('Cholera')
        ->and(Icd10Code::where('code', 'A01')->value('chapter'))->toBe('Choroby zakaźne');

    unlink($path);
});
