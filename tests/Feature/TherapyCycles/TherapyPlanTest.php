<?php

use App\Enums\MilestoneHorizon;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\TherapyMilestone;
use App\Models\User;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();
    $this->cycle = TherapyCycle::factory()->forPatient($this->patient)->create();

    $this->appointment = Appointment::factory()->forCycle($this->cycle)->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 09:30:00',
    ]);

    $this->payload = fn (array $extra = []) => array_merge([
        'starts_at' => '2026-10-05 09:00:00',
        'duration_minutes' => 30,
        'status' => 'completed',
        'therapy_cycle_id' => $this->cycle->id,
    ], $extra);
});

/**
 * operator_id is deliberately not fillable, so it is assigned before saving —
 * the same way the sync service does it.
 */
function makeMilestone(TherapyCycle $cycle, array $attributes): TherapyMilestone
{
    $milestone = new TherapyMilestone($attributes);
    $milestone->operator_id = $cycle->operator_id;
    $milestone->therapy_cycle_id = $cycle->id;
    $milestone->save();

    return $milestone;
}

it('saves the therapy plan on the cycle, not the visit', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'therapy_plan' => 'Etap 1: zmniejszenie bólu. Etap 2: wzmocnienie.',
        ]))
        ->assertSessionHasNoErrors();

    expect($this->cycle->fresh()->therapy_plan)->toContain('Etap 1');
});

it('creates milestones with their horizons', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['goal' => 'Zmniejszenie bólu do 3/10', 'horizon' => 'short'],
                ['goal' => 'Powrót do biegania', 'horizon' => 'long'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $milestones = $this->cycle->fresh()->orderedMilestones();

    expect($milestones)->toHaveCount(2)
        ->and($milestones[0]->horizon)->toBe(MilestoneHorizon::Short)
        ->and($milestones[1]->horizon)->toBe(MilestoneHorizon::Long);
});

it('marks a milestone as achieved and stamps the time', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['goal' => 'Zmniejszenie bólu', 'horizon' => 'short', 'achieved' => '1'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(TherapyMilestone::sole()->achieved_at)->not->toBeNull();
});

it('keeps the original achievement time when the goal text is edited', function () {
    $milestone = makeMilestone($this->cycle, [
        'goal' => 'Stary cel',
        'horizon' => MilestoneHorizon::Short,
        'achieved_at' => now()->subWeek(),
    ]);

    $achievedAt = $milestone->fresh()->achieved_at;

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['id' => $milestone->id, 'goal' => 'Poprawiony cel', 'horizon' => 'short', 'achieved' => '1'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $fresh = $milestone->fresh();

    expect($fresh->goal)->toBe('Poprawiony cel')
        ->and($fresh->achieved_at->timestamp)->toBe($achievedAt->timestamp);
});

it('clears the achievement when the milestone is unchecked', function () {
    $milestone = makeMilestone($this->cycle, [
        'goal' => 'Cel',
        'horizon' => MilestoneHorizon::Short,
        'achieved_at' => now(),
    ]);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['id' => $milestone->id, 'goal' => 'Cel', 'horizon' => 'short'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($milestone->fresh()->achieved_at)->toBeNull();
});

it('removes milestones that are no longer submitted', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['goal' => 'Pierwszy', 'horizon' => 'short'],
                ['goal' => 'Drugi', 'horizon' => 'medium'],
            ],
        ]));

    $kept = $this->cycle->fresh()->orderedMilestones()->first();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['id' => $kept->id, 'goal' => $kept->goal, 'horizon' => 'short'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(TherapyMilestone::count())->toBe(1)
        ->and(TherapyMilestone::sole()->goal)->toBe('Pierwszy');
});

it('ignores rows with an empty goal', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['goal' => '', 'horizon' => 'short'],
                ['goal' => '   ', 'horizon' => 'long'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(TherapyMilestone::count())->toBe(0);
});

it('rejects an invalid horizon', function () {
    $this->actingAs($this->operator)
        ->put(route('appointments.update', $this->appointment), ($this->payload)([
            'milestones' => [
                ['goal' => 'Cel', 'horizon' => 'forever'],
            ],
        ]))
        ->assertSessionHasErrors('milestones.0.horizon');
});

it('hides milestones of another operator behind the scope', function () {
    makeMilestone($this->cycle, ['goal' => 'Cel', 'horizon' => MilestoneHorizon::Short]);

    $this->actingAs(User::factory()->operator()->create());

    expect(TherapyMilestone::count())->toBe(0);
});

it('does not touch the plan when the visit has no cycle', function () {
    $loose = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => '2026-10-06 09:00:00',
        'ends_at' => '2026-10-06 09:30:00',
    ]);

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $loose), [
            'starts_at' => '2026-10-06 09:00:00',
            'duration_minutes' => 30,
            'status' => 'completed',
            'therapy_plan' => 'Plan bez cyklu',
            'milestones' => [['goal' => 'Cel', 'horizon' => 'short']],
        ])
        ->assertSessionHasNoErrors();

    expect(TherapyMilestone::count())->toBe(0)
        ->and($this->cycle->fresh()->therapy_plan)->toBeNull();
});
