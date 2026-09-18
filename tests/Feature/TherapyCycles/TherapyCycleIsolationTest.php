<?php

use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;

beforeEach(function () {
    $this->operatorA = User::factory()->operator()->create();
    $this->operatorB = User::factory()->operator()->create();
    $this->admin = User::factory()->admin()->create();

    $this->patientA = Patient::factory()->forOperator($this->operatorA)->create();
    $this->cycleA = TherapyCycle::factory()->forPatient($this->patientA)->create();
});

it('hides another operator cycle behind the operator scope', function () {
    $this->actingAs($this->operatorB);

    expect(TherapyCycle::find($this->cycleA->id))->toBeNull()
        ->and(TherapyCycle::count())->toBe(0);
});

it('shows an operator their own cycle', function () {
    $this->actingAs($this->operatorA);

    expect(TherapyCycle::find($this->cycleA->id))->not->toBeNull();
});

it('lets an admin see every cycle', function () {
    $patientB = Patient::factory()->forOperator($this->operatorB)->create();
    TherapyCycle::factory()->forPatient($patientB)->create();

    $this->actingAs($this->admin);

    expect(TherapyCycle::count())->toBe(2);
});

it('denies another operator through the policy', function () {
    expect($this->operatorB->can('view', $this->cycleA))->toBeFalse()
        ->and($this->operatorB->can('update', $this->cycleA))->toBeFalse()
        ->and($this->operatorB->can('delete', $this->cycleA))->toBeFalse();
});

it('allows the owning operator and the admin through the policy', function () {
    expect($this->operatorA->can('view', $this->cycleA))->toBeTrue()
        ->and($this->operatorA->can('update', $this->cycleA))->toBeTrue()
        ->and($this->admin->can('view', $this->cycleA))->toBeTrue();
});
