<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

it('shows only the operators own appointments for today', function () {
    $operator = User::factory()->operator()->create();
    $ownPatient = Patient::factory()->forOperator($operator)->create(['last_name' => 'Dzisiejszy']);

    Appointment::factory()->forOperator($operator)->create([
        'patient_id' => $ownPatient->id,
        'starts_at' => now()->setTime(10, 0),
        'ends_at' => now()->setTime(10, 45),
    ]);

    $otherOperator = User::factory()->operator()->create();
    $foreignPatient = Patient::factory()->forOperator($otherOperator)->create(['last_name' => 'Obcy']);
    Appointment::factory()->forOperator($otherOperator)->create([
        'patient_id' => $foreignPatient->id,
        'starts_at' => now()->setTime(11, 0),
        'ends_at' => now()->setTime(11, 45),
    ]);

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dzisiejszy')
        ->assertDontSee('Obcy');
});

it('does not list appointments from other days', function () {
    $operator = User::factory()->operator()->create();
    $patient = Patient::factory()->forOperator($operator)->create(['last_name' => 'Jutrzejszy']);

    Appointment::factory()->forOperator($operator)->create([
        'patient_id' => $patient->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(10, 45),
    ]);

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Brak wizyt zaplanowanych na dziś');
});
