<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();

    $this->existing = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => '2026-10-01 10:00:00',
        'ends_at' => '2026-10-01 11:00:00',
    ]);
});

function book(array $overrides = []): array
{
    return array_merge([
        'patient_id' => test()->patient->id,
        'starts_at' => '2026-10-01 10:00:00',
        'duration_minutes' => 60,
    ], $overrides);
}

it('rejects an exactly overlapping slot', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book())
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::count())->toBe(1);
});

it('rejects a slot starting inside an existing appointment', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book(['starts_at' => '2026-10-01 10:30:00']))
        ->assertSessionHasErrors('starts_at');
});

it('rejects a slot ending inside an existing appointment', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book(['starts_at' => '2026-10-01 09:30:00']))
        ->assertSessionHasErrors('starts_at');
});

it('rejects a slot fully containing an existing appointment', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book(['starts_at' => '2026-10-01 09:00:00', 'duration_minutes' => 180]))
        ->assertSessionHasErrors('starts_at');
});

it('allows a back to back slot starting exactly when the previous ends', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book(['starts_at' => '2026-10-01 11:00:00']))
        ->assertSessionHasNoErrors();

    expect(Appointment::count())->toBe(2);
});

it('allows a back to back slot ending exactly when the next starts', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book(['starts_at' => '2026-10-01 09:00:00']))
        ->assertSessionHasNoErrors();

    expect(Appointment::count())->toBe(2);
});

it('allows the same slot for a different operator', function () {
    $otherOperator = User::factory()->operator()->create();
    $otherPatient = Patient::factory()->forOperator($otherOperator)->create();

    $this->actingAs($otherOperator)
        ->post(route('appointments.store'), [
            'patient_id' => $otherPatient->id,
            'starts_at' => '2026-10-01 10:00:00',
            'duration_minutes' => 60,
        ])
        ->assertSessionHasNoErrors();

    expect(Appointment::withoutGlobalScopes()->count())->toBe(2);
});

it('does not collide with itself when an appointment is edited', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->existing), [
            'starts_at' => '2026-10-01 10:00:00',
            'duration_minutes' => 90,
            'status' => AppointmentStatus::Scheduled->value,
        ])
        ->assertSessionHasNoErrors();

    expect($this->existing->fresh()->ends_at->format('H:i'))->toBe('11:30');
});

it('ignores cancelled appointments when checking collisions', function () {
    $this->existing->update(['status' => AppointmentStatus::Cancelled]);

    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book())
        ->assertSessionHasNoErrors();

    expect(Appointment::count())->toBe(2);
});

it('prevents booking an appointment for another operators patient', function () {
    $foreignPatient = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($this->operator)
        ->post(route('appointments.store'), book(['patient_id' => $foreignPatient->id, 'starts_at' => '2026-10-02 10:00:00']))
        ->assertSessionHasErrors('patient_id');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1);
});
