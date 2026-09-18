<?php

use App\Enums\ComorbidityKind;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientComorbidity;
use App\Models\User;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();

    $this->appointment = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
    ]);

    $this->payload = fn (array $extra = []) => array_merge([
        'starts_at' => '2026-10-05 09:00:00',
        'duration_minutes' => 30,
        'status' => 'completed',
    ], $extra);
});

it('saves comorbidities on the patient, not on the visit', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [
                ['name' => 'Cukrzyca typu 2', 'kind' => 'chronic'],
                ['name' => 'Przebyty zawał', 'kind' => 'past'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($this->patient->fresh()->comorbidities)->toHaveCount(2)
        ->and(PatientComorbidity::first()->patient_id)->toBe($this->patient->id);
});

it('carries comorbidities over to another visit of the same patient', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['name' => 'Nadciśnienie tętnicze', 'kind' => 'active']],
        ]));

    $other = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => '2026-10-12 09:00:00',
        'ends_at' => '2026-10-12 09:30:00',
    ]);

    $this->actingAs($this->operator)
        ->get(route('appointments.show', $other))
        ->assertOk()
        ->assertSee('Nadciśnienie tętnicze');
});

it('orders active conditions before past ones', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [
                ['name' => 'Przebyta operacja', 'kind' => 'past'],
                ['name' => 'Astma', 'kind' => 'active'],
                ['name' => 'Cukrzyca', 'kind' => 'chronic'],
            ],
        ]));

    expect($this->patient->fresh()->orderedComorbidities()->pluck('name')->all())
        ->toBe(['Astma', 'Cukrzyca', 'Przebyta operacja']);
});

it('updates an existing entry instead of duplicating it', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['name' => 'Cukrzyca', 'kind' => 'chronic']],
        ]));

    $existing = PatientComorbidity::sole();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['id' => $existing->id, 'name' => 'Cukrzyca typu 2', 'kind' => 'active']],
        ]))
        ->assertSessionHasNoErrors();

    expect(PatientComorbidity::count())->toBe(1)
        ->and(PatientComorbidity::sole()->name)->toBe('Cukrzyca typu 2')
        ->and(PatientComorbidity::sole()->kind)->toBe(ComorbidityKind::Active);
});

it('removes entries that are no longer submitted', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [
                ['name' => 'Cukrzyca', 'kind' => 'chronic'],
                ['name' => 'Astma', 'kind' => 'active'],
            ],
        ]));

    $kept = PatientComorbidity::where('name', 'Astma')->sole();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['id' => $kept->id, 'name' => 'Astma', 'kind' => 'active']],
        ]));

    expect(PatientComorbidity::count())->toBe(1)
        ->and(PatientComorbidity::sole()->name)->toBe('Astma');
});

it('ignores rows with an empty name', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['name' => '  ', 'kind' => 'active']],
        ]))
        ->assertSessionHasNoErrors();

    expect(PatientComorbidity::count())->toBe(0);
});

it('leaves comorbidities untouched when the field is not submitted', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['name' => 'Cukrzyca', 'kind' => 'chronic']],
        ]));

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)());

    expect(PatientComorbidity::count())->toBe(1);
});

it('rejects an invalid kind', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['name' => 'Cukrzyca', 'kind' => 'nieznane']],
        ]))
        ->assertSessionHasErrors('comorbidities.0.kind');
});

it('hides comorbidities of another operator', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'comorbidities' => [['name' => 'Cukrzyca', 'kind' => 'chronic']],
        ]));

    $this->actingAs(User::factory()->operator()->create());

    expect(PatientComorbidity::count())->toBe(0);
});
