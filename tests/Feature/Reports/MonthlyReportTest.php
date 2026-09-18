<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

it('shows only the operators own appointments in the monthly report', function () {
    $operator = User::factory()->operator()->create();
    $ownPatient = Patient::factory()->forOperator($operator)->create(['last_name' => 'Wlasny']);

    Appointment::factory()->forOperator($operator)->create([
        'patient_id' => $ownPatient->id,
        'starts_at' => '2026-10-10 09:00:00',
        'ends_at' => '2026-10-10 10:00:00',
    ]);

    $otherOperator = User::factory()->operator()->create();
    $foreignPatient = Patient::factory()->forOperator($otherOperator)->create(['last_name' => 'Obcy']);
    Appointment::factory()->forOperator($otherOperator)->create([
        'patient_id' => $foreignPatient->id,
        'starts_at' => '2026-10-11 09:00:00',
        'ends_at' => '2026-10-11 10:00:00',
    ]);

    $this->actingAs($operator)
        ->get(route('reports.monthly', ['month' => '2026-10']))
        ->assertOk()
        ->assertSee('Wlasny')
        ->assertDontSee('Obcy');
});

it('excludes appointments outside the selected month', function () {
    $operator = User::factory()->operator()->create();
    $patient = Patient::factory()->forOperator($operator)->create(['last_name' => 'Pazdziernik']);
    $otherPatient = Patient::factory()->forOperator($operator)->create(['last_name' => 'Listopad']);

    Appointment::factory()->forOperator($operator)->create([
        'patient_id' => $patient->id,
        'starts_at' => '2026-10-10 09:00:00',
        'ends_at' => '2026-10-10 10:00:00',
    ]);
    Appointment::factory()->forOperator($operator)->create([
        'patient_id' => $otherPatient->id,
        'starts_at' => '2026-11-10 09:00:00',
        'ends_at' => '2026-11-10 10:00:00',
    ]);

    $this->actingAs($operator)
        ->get(route('reports.monthly', ['month' => '2026-10']))
        ->assertOk()
        ->assertSee('Pazdziernik')
        ->assertDontSee('Listopad');
});

it('lets an admin filter the report by operator', function () {
    $admin = User::factory()->admin()->create();

    $operatorA = User::factory()->operator()->create();
    $patientA = Patient::factory()->forOperator($operatorA)->create(['last_name' => 'PacjentA']);
    Appointment::factory()->forOperator($operatorA)->create([
        'patient_id' => $patientA->id,
        'starts_at' => '2026-10-10 09:00:00',
        'ends_at' => '2026-10-10 10:00:00',
    ]);

    $operatorB = User::factory()->operator()->create();
    $patientB = Patient::factory()->forOperator($operatorB)->create(['last_name' => 'PacjentB']);
    Appointment::factory()->forOperator($operatorB)->create([
        'patient_id' => $patientB->id,
        'starts_at' => '2026-10-12 09:00:00',
        'ends_at' => '2026-10-12 10:00:00',
    ]);

    $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-10']))
        ->assertSee('PacjentA')
        ->assertSee('PacjentB');

    $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-10', 'operator_id' => $operatorA->id]))
        ->assertSee('PacjentA')
        ->assertDontSee('PacjentB');
});
