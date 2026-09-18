<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();
    $this->cycle = TherapyCycle::factory()->forPatient($this->patient)->create();
});

it('collects only appointments linked to the cycle', function () {
    Appointment::factory()->forCycle($this->cycle)->count(2)->create();

    // Another appointment of the same patient, outside the cycle.
    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
    ]);

    expect($this->cycle->appointments()->count())->toBe(2);
});

it('orders cycle appointments chronologically', function () {
    $later = Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-12 10:00:00',
        'ends_at' => '2026-10-12 10:30:00',
    ]);
    $earlier = Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
    ]);

    expect($this->cycle->appointments()->pluck('id')->all())->toBe([$earlier->id, $later->id]);
});

it('detaches the cycle from view but keeps the column when the cycle is soft deleted', function () {
    $appointment = Appointment::factory()->forCycle($this->cycle)->create();

    $this->cycle->delete();

    expect($appointment->fresh()->therapyCycle)->toBeNull()
        ->and(DB::table('appointments')->where('id', $appointment->id)->value('therapy_cycle_id'))
        ->toBe($this->cycle->id);
});

it('restores the link when the cycle is restored', function () {
    $appointment = Appointment::factory()->forCycle($this->cycle)->create();

    $this->cycle->delete();
    $this->cycle->restore();

    expect($appointment->fresh()->therapyCycle?->id)->toBe($this->cycle->id);
});
