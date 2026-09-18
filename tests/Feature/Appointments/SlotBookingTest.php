<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\SlotService;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();
    // 2026-10-05 to poniedziałek — dzień roboczy.
    $this->monday = '2026-10-05';
});

it('generates slots covering the whole working day in 30 minute steps', function () {
    $slots = app(SlotService::class)->daySlots($this->operator->id, Carbon\Carbon::parse($this->monday));

    // 08:00–18:00 to 10 godzin, czyli 20 slotów po 30 minut.
    expect($slots)->toHaveCount(20)
        ->and($slots->first()['starts_at']->format('H:i'))->toBe('08:00')
        ->and($slots->last()['starts_at']->format('H:i'))->toBe('17:30');
});

it('marks booked slots as unavailable', function () {
    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => $this->monday.' 09:00:00',
        'ends_at' => $this->monday.' 10:00:00',
    ]);

    $slots = app(SlotService::class)->daySlots($this->operator->id, Carbon\Carbon::parse($this->monday));
    $byHour = $slots->keyBy(fn ($slot) => $slot['starts_at']->format('H:i'));

    expect($byHour['08:30']['available'])->toBeTrue()
        ->and($byHour['09:00']['available'])->toBeFalse()
        ->and($byHour['09:30']['available'])->toBeFalse()
        ->and($byHour['10:00']['available'])->toBeTrue();
});

it('returns no slots on a non working day', function () {
    // 2026-10-10 to sobota.
    $slots = app(SlotService::class)->daySlots($this->operator->id, Carbon\Carbon::parse('2026-10-10'));

    expect($slots)->toBeEmpty();
});

it('rejects a booking that does not start on a slot boundary', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), [
            'patient_id' => $this->patient->id,
            'starts_at' => $this->monday.' 09:15:00',
            'duration_minutes' => 30,
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::count())->toBe(0);
});

it('rejects a duration that is not a multiple of the slot length', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), [
            'patient_id' => $this->patient->id,
            'starts_at' => $this->monday.' 09:00:00',
            'duration_minutes' => 45,
        ])
        ->assertSessionHasErrors('duration_minutes');

    expect(Appointment::count())->toBe(0);
});

it('rejects a booking on a non working day', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), [
            'patient_id' => $this->patient->id,
            'starts_at' => '2026-10-10 09:00:00',
            'duration_minutes' => 30,
        ])
        ->assertSessionHasErrors('starts_at');
});

it('accepts a booking on a free slot', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), [
            'patient_id' => $this->patient->id,
            'starts_at' => $this->monday.' 09:00:00',
            'duration_minutes' => 60,
        ])
        ->assertSessionHasNoErrors();

    expect(Appointment::count())->toBe(1)
        ->and(Appointment::sole()->ends_at->format('H:i'))->toBe('10:00');
});

it('counts consecutive free slots so a visit cannot span a booked one', function () {
    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => $this->monday.' 10:00:00',
        'ends_at' => $this->monday.' 10:30:00',
    ]);

    $free = app(SlotService::class)->consecutiveFreeSlots(
        $this->operator->id,
        Carbon\Carbon::parse($this->monday.' 09:00:00'),
    );

    // 09:00 i 09:30 są wolne, 10:00 zajęty — czyli 2 sloty z rzędu.
    expect($free)->toBe(2);
});

it('exposes free slots through the slots endpoint scoped to the operator', function () {
    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => $this->monday.' 09:00:00',
        'ends_at' => $this->monday.' 09:30:00',
    ]);

    $response = $this->actingAs($this->operator)
        ->getJson(route('appointments.slots', ['date' => $this->monday]));

    $response->assertOk();

    $slots = collect($response->json('slots'));

    expect($response->json('working_day'))->toBeTrue()
        ->and($slots)->toHaveCount(20)
        ->and($slots->firstWhere('label', '09:00')['available'])->toBeFalse()
        ->and($slots->firstWhere('label', '09:30')['available'])->toBeTrue();
});
