<?php

use App\Enums\MeasurementType;
use App\Models\Appointment;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
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

    $this->template = function (MeasurementType $type, ?string $unit = null) {
        $template = new MeasurementTemplate([
            'name' => 'Pomiar '.$type->value,
            'type' => $type,
            'unit' => $unit,
        ]);
        $template->operator_id = $this->operator->id;
        $template->save();

        return $template;
    };

    $this->payload = fn (array $extra = []) => array_merge([
        'starts_at' => '2026-10-05 09:00:00',
        'duration_minutes' => 30,
        'status' => 'completed',
    ], $extra);
});

it('saves a bilateral measurement for both sides', function () {
    $template = ($this->template)(MeasurementType::Bilateral, '°');

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => '120',
                'value_right' => '95.5',
                'note' => 'pomiar goniometrem',
            ]],
        ]))
        ->assertSessionHasNoErrors();

    $measurement = Measurement::sole();

    expect((float) $measurement->value_left)->toBe(120.0)
        ->and((float) $measurement->value_right)->toBe(95.5)
        ->and($measurement->note)->toBe('pomiar goniometrem')
        ->and($measurement->formattedValue())->toBe('L: 120°   P: 95.5°');
});

it('saves a yes/no measurement', function () {
    $template = ($this->template)(MeasurementType::Boolean);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_boolean' => '1',
            ]],
        ]))
        ->assertSessionHasNoErrors();

    expect(Measurement::sole()->formattedValue())->toBe('Tak');
});

it('saves a 0-10 scale measurement', function () {
    $template = ($this->template)(MeasurementType::Scale);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => '7',
            ]],
        ]))
        ->assertSessionHasNoErrors();

    expect(Measurement::sole()->formattedValue())->toBe('7/10');
});

it('rejects a scale value outside 0-10', function () {
    $template = ($this->template)(MeasurementType::Scale);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => '12',
            ]],
        ]))
        ->assertSessionHasErrors('measurements.0.value_left');

    expect(Measurement::count())->toBe(0);
});

it('skips rows without any reading', function () {
    $template = ($this->template)(MeasurementType::Numeric, 'cm');

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [
                ['measurement_template_id' => $template->id, 'value_left' => ''],
                ['measurement_template_id' => '', 'value_left' => '5'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(Measurement::count())->toBe(0);
});

it('replaces the measurements on a later edit', function () {
    $template = ($this->template)(MeasurementType::Numeric, 'cm');

    $submit = fn (string $value) => $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => $value,
            ]],
        ]));

    $submit('46');
    $submit('48');

    expect(Measurement::count())->toBe(1)
        ->and((float) Measurement::sole()->value_left)->toBe(48.0);
});

it('keeps the label after the template is removed from the library', function () {
    $template = ($this->template)(MeasurementType::Numeric, 'cm');

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => '46',
            ]],
        ]));

    $template->delete();

    expect(Measurement::sole()->template->name)->toBe('Pomiar numeric')
        ->and(Measurement::sole()->formattedValue())->toBe('46 cm');
});

it('hides measurements of another operator', function () {
    $template = ($this->template)(MeasurementType::Numeric, 'cm');

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => '46',
            ]],
        ]));

    $this->actingAs(User::factory()->operator()->create());

    expect(Measurement::count())->toBe(0);
});

it('shows the measurements on the appointment page', function () {
    $template = ($this->template)(MeasurementType::Numeric, 'cm');

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'measurements' => [[
                'measurement_template_id' => $template->id,
                'value_left' => '46',
            ]],
        ]));

    $this->actingAs($this->operator)
        ->get(route('appointments.show', $this->appointment))
        ->assertOk()
        ->assertSee('Pomiar numeric')
        ->assertSee('46 cm');
});
