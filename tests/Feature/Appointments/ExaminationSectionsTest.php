<?php

use App\Models\Appointment;
use App\Models\Patient;
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

$base = [
    'starts_at' => '2026-10-05 09:00:00',
    'duration_minutes' => 30,
    'status' => 'completed',
];

it('saves every examination section', function () use ($base) {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), [
            ...$base,
            'interview' => 'Ból krzyża od trzech tygodni, nasila się przy dłuższym siedzeniu.',
            'detailed_examination' => 'Ograniczenie zgięcia w odcinku lędźwiowym.',
            'conclusions' => 'Prawdopodobne przeciążenie odcinka lędźwiowego.',
            'procedures' => 'Terapia manualna',
            'treatment_notes' => 'Pacjent dobrze zniósł zabieg.',
            'internal_notes' => 'Pacjent bagatelizuje zalecenia.',
            'patient_recommendations' => 'Ćwiczenia stabilizacyjne 2x dziennie.',
        ])
        ->assertSessionHasNoErrors();

    $appointment = $this->appointment->fresh();

    expect($appointment->interview)->toContain('Ból krzyża')
        ->and($appointment->detailed_examination)->toContain('Ograniczenie zgięcia')
        ->and($appointment->conclusions)->toContain('przeciążenie')
        ->and($appointment->internal_notes)->toContain('bagatelizuje')
        ->and($appointment->patient_recommendations)->toContain('stabilizacyjne');
});

it('allows leaving every section empty', function () use ($base) {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), $base)
        ->assertSessionHasNoErrors();

    $appointment = $this->appointment->fresh();

    expect($appointment->interview)->toBeNull()
        ->and($appointment->conclusions)->toBeNull()
        ->and($appointment->internal_notes)->toBeNull();
});

it('rejects a section longer than the limit', function () use ($base) {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), [
            ...$base,
            'interview' => str_repeat('a', 5001),
        ])
        ->assertSessionHasErrors('interview');
});

it('shows the sections on the appointment page', function () {
    $this->appointment->update([
        'interview' => 'Wywiad testowy',
        'conclusions' => 'Wnioski testowe',
        'internal_notes' => 'Notatka wewnetrzna testowa',
        'patient_recommendations' => 'Zalecenia testowe',
    ]);

    $this->actingAs($this->operator)
        ->get(route('appointments.show', $this->appointment))
        ->assertOk()
        ->assertSee('Wywiad testowy')
        ->assertSee('Wnioski testowe')
        ->assertSee('Notatka wewnetrzna testowa')
        ->assertSee('Zalecenia testowe')
        ->assertSee('nie trafia do wydruku dla pacjenta');
});

it('keeps the sections invisible to another operator', function () {
    $intruder = User::factory()->operator()->create();

    $this->appointment->update(['internal_notes' => 'Notatka wewnetrzna testowa']);

    $this->actingAs($intruder)
        ->get(route('appointments.show', $this->appointment))
        ->assertNotFound();
});
