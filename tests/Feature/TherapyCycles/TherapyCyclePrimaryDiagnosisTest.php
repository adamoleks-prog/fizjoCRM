<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use Database\Seeders\Icd10CodeSeeder;

beforeEach(function () {
    $this->seed(Icd10CodeSeeder::class);

    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();
    $this->cycle = TherapyCycle::factory()->forPatient($this->patient)->create();
});

it('takes the diagnosis from the earliest visit that has one', function () {
    Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-12 10:00:00',
        'ends_at' => '2026-10-12 10:30:00',
        'icd10_code' => 'M17',
    ]);
    Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
        'icd10_code' => 'M54.3',
    ]);

    expect($this->cycle->primaryIcd10Code())->toBe('M54.3')
        ->and($this->cycle->primaryIcd10Name())->toContain('Rwa kulszowa');
});

it('skips earlier visits that have no diagnosis yet', function () {
    Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
        'icd10_code' => null,
    ]);
    Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-12 10:00:00',
        'ends_at' => '2026-10-12 10:30:00',
        'icd10_code' => 'M75.1',
    ]);

    expect($this->cycle->primaryIcd10Code())->toBe('M75.1');
});

it('returns null when no visit in the cycle has a diagnosis', function () {
    Appointment::factory()->forCycle($this->cycle)->create(['icd10_code' => null]);

    expect($this->cycle->primaryIcd10Code())->toBeNull()
        ->and($this->cycle->primaryIcd10Name())->toBeNull();
});
