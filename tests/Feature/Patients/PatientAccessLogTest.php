<?php

use App\Enums\PatientAccessAction;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\User;

it('logs who viewed a patient record', function () {
    $operator = User::factory()->operator()->create();
    $patient = Patient::factory()->forOperator($operator)->create();

    $this->actingAs($operator)->get(route('patients.show', $patient))->assertOk();

    $log = PatientAccessLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->patient_id)->toBe($patient->id)
        ->and($log->user_id)->toBe($operator->id)
        ->and($log->action)->toBe(PatientAccessAction::Viewed);
});

it('logs an update to a patient record', function () {
    $operator = User::factory()->operator()->create();
    $patient = Patient::factory()->forOperator($operator)->create();

    $this->actingAs($operator)->put(route('patients.update', $patient), [
        'first_name' => 'Jan',
        'last_name' => 'Nowak',
    ])->assertRedirect();

    expect(PatientAccessLog::where('action', PatientAccessAction::Updated)->count())->toBe(1);
});

it('does not log when access was denied', function () {
    $operator = User::factory()->operator()->create();
    $foreignPatient = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)->get(route('patients.show', $foreignPatient))->assertNotFound();

    expect(PatientAccessLog::count())->toBe(0);
});
