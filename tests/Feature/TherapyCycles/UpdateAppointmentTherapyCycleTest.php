<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();

    $this->appointment = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
    ]);
});

function updatePayload(array $overrides = []): array
{
    return array_merge([
        'starts_at' => '2026-10-05 09:00:00',
        'duration_minutes' => 30,
        'status' => 'completed',
    ], $overrides);
}

it('links an existing cycle of the same patient', function () {
    $cycle = TherapyCycle::factory()->forPatient($this->patient)->create();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'therapy_cycle_id' => $cycle->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->appointment->fresh()->therapy_cycle_id)->toBe($cycle->id);
});

it('rejects a cycle belonging to another patient', function () {
    $otherPatient = Patient::factory()->forOperator($this->operator)->create();
    $foreignCycle = TherapyCycle::factory()->forPatient($otherPatient)->create();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'therapy_cycle_id' => $foreignCycle->id,
        ]))
        ->assertSessionHasErrors('therapy_cycle_id');

    expect($this->appointment->fresh()->therapy_cycle_id)->toBeNull();
});

it('rejects a soft deleted cycle', function () {
    $cycle = TherapyCycle::factory()->forPatient($this->patient)->create();
    $cycle->delete();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'therapy_cycle_id' => $cycle->id,
        ]))
        ->assertSessionHasErrors('therapy_cycle_id');
});

it('creates a new named cycle and links it', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'new_therapy_cycle_name' => 'Rehabilitacja kolana',
        ]))
        ->assertSessionHasNoErrors();

    $cycle = TherapyCycle::sole();

    expect($cycle->name)->toBe('Rehabilitacja kolana')
        ->and($cycle->patient_id)->toBe($this->patient->id)
        ->and($cycle->operator_id)->toBe($this->operator->id)
        ->and($this->appointment->fresh()->therapy_cycle_id)->toBe($cycle->id);
});

it('falls back to a generated name when the new cycle name is empty', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'new_therapy_cycle_name' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect(TherapyCycle::sole()->name)->toStartWith('Cykl od ');
});

it('rejects picking an existing cycle and creating a new one at once', function () {
    $cycle = TherapyCycle::factory()->forPatient($this->patient)->create();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'therapy_cycle_id' => $cycle->id,
            'new_therapy_cycle_name' => 'Drugi cykl',
        ]))
        ->assertSessionHasErrors('therapy_cycle_id');

    expect(TherapyCycle::count())->toBe(1);
});

it('leaves the appointment without a cycle when neither field is sent', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload())
        ->assertSessionHasNoErrors();

    expect($this->appointment->fresh()->therapy_cycle_id)->toBeNull()
        ->and(TherapyCycle::count())->toBe(0);
});

it('detaches the cycle when an empty selection is submitted', function () {
    $cycle = TherapyCycle::factory()->forPatient($this->patient)->create();
    $this->appointment->therapy_cycle_id = $cycle->id;
    $this->appointment->save();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), updatePayload([
            'therapy_cycle_id' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($this->appointment->fresh()->therapy_cycle_id)->toBeNull();
});
