<?php

use App\Enums\BodyView;
use App\Models\Appointment;
use App\Models\PainPoint;
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

    $this->payload = fn (array $extra = []) => array_merge([
        'starts_at' => '2026-10-05 09:00:00',
        'duration_minutes' => 30,
        'status' => 'completed',
    ], $extra);
});

it('saves marks for both sides of the body', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [
                ['body_view' => 'front', 'position_x' => '42.5', 'position_y' => '30.25', 'note' => 'bark lewy'],
                ['body_view' => 'back', 'position_x' => '50', 'position_y' => '45', 'note' => 'odcinek lędźwiowy'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $points = $this->appointment->fresh()->painPoints;

    expect($points)->toHaveCount(2)
        ->and($points->first()->body_view)->toBe(BodyView::Front)
        ->and((float) $points->first()->position_x)->toBe(42.5)
        ->and($points->last()->body_view)->toBe(BodyView::Back);
});

it('rejects a position outside the silhouette', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [
                ['body_view' => 'front', 'position_x' => '140', 'position_y' => '30'],
            ],
        ]))
        ->assertSessionHasErrors('pain_points.0.position_x');

    expect(PainPoint::count())->toBe(0);
});

it('rejects an unknown body view', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [
                ['body_view' => 'bok', 'position_x' => '40', 'position_y' => '30'],
            ],
        ]))
        ->assertSessionHasErrors('pain_points.0.body_view');
});

it('allows a mark without a description', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [
                ['body_view' => 'front', 'position_x' => '40', 'position_y' => '30'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(PainPoint::sole()->note)->toBeNull();
});

it('replaces the marks on a later edit', function () {
    $submit = fn (array $points) => $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)(['pain_points' => $points]));

    $submit([['body_view' => 'front', 'position_x' => '40', 'position_y' => '30', 'note' => 'pierwszy']]);
    $submit([['body_view' => 'back', 'position_x' => '55', 'position_y' => '60', 'note' => 'drugi']]);

    expect(PainPoint::count())->toBe(1)
        ->and(PainPoint::sole()->note)->toBe('drugi');
});

it('clears the marks when none are submitted', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [['body_view' => 'front', 'position_x' => '40', 'position_y' => '30']],
        ]));

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)(['pain_points' => []]));

    expect(PainPoint::count())->toBe(0);
});

it('shows the marks on the appointment page', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [
                ['body_view' => 'front', 'position_x' => '40', 'position_y' => '30', 'note' => 'bark lewy'],
            ],
        ]));

    $this->actingAs($this->operator)
        ->get(route('appointments.show', $this->appointment))
        ->assertOk()
        ->assertSee('Wizualizacja dolegliwości')
        ->assertSee('bark lewy');
});

it('hides marks of another operator', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'pain_points' => [['body_view' => 'front', 'position_x' => '40', 'position_y' => '30']],
        ]));

    $this->actingAs(User::factory()->operator()->create());

    expect(PainPoint::count())->toBe(0);
});
