<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\SlotService;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 07:00')); // a Monday

    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();

    $this->savePattern = fn (array $overrides = []) => $this->actingAs($this->operator)->put(route('schedule.pattern'), array_replace_recursive([
        'slot_minutes' => 45,
        'works' => [1 => 1, 2 => 0, 3 => 1, 4 => 0, 5 => 0, 6 => 0, 7 => 0],
        'pattern' => [
            1 => [['start' => '08:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00']],
            3 => [['start' => '10:00', 'end' => '13:00']],
        ],
    ], $overrides));
});

it('falls back to the practice default until a pattern is saved', function () {
    $slots = app(SlotService::class)->daySlots($this->operator->id, Carbon::parse('2026-10-05'));

    expect($slots->first()['starts_at']->format('H:i'))->toBe('08:00')
        ->and($slots->last()['starts_at']->format('H:i'))->toBe('17:30');
});

it('cuts slots from the weekly pattern, repeated every week, with a break', function () {
    ($this->savePattern)()->assertSessionHasNoErrors();

    $service = app(SlotService::class);

    foreach (['2026-10-05', '2026-10-12', '2026-11-30'] as $monday) {
        $labels = $service->daySlots($this->operator->id, Carbon::parse($monday))->map(fn ($s) => $s['starts_at']->format('H:i'))->all();

        // 45-minute slots; a slot that would run past 12:00 or 17:00 is not offered.
        expect($labels)->toBe(['08:00', '08:45', '09:30', '10:15', '11:00', '14:00', '14:45', '15:30', '16:15']);
    }

    expect($service->isWorkingDay($this->operator->id, Carbon::parse('2026-10-06')))->toBeFalse()
        ->and($service->slotMinutes($this->operator->id))->toBe(45);
});

it('does not let a long visit run over the break', function () {
    ($this->savePattern)();

    expect(app(SlotService::class)->consecutiveFreeSlots($this->operator->id, Carbon::parse('2026-10-05 10:15')))->toBe(2);
});

it('overrides a single day without touching other weeks', function () {
    ($this->savePattern)();

    $this->actingAs($this->operator)
        ->post(route('schedule.day'), ['date' => '2026-10-12', 'periods' => [['start' => '12:00', 'end' => '15:00']]])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->operator)
        ->post(route('schedule.day'), ['date' => '2026-10-07', 'day_off' => 1])
        ->assertSessionHasNoErrors();

    $service = app(SlotService::class);

    expect($service->daySlots($this->operator->id, Carbon::parse('2026-10-12'))->first()['starts_at']->format('H:i'))->toBe('12:00')
        ->and($service->daySlots($this->operator->id, Carbon::parse('2026-10-19'))->first()['starts_at']->format('H:i'))->toBe('08:00')
        ->and($service->isWorkingDay($this->operator->id, Carbon::parse('2026-10-07')))->toBeFalse()
        ->and($service->isWorkingDay($this->operator->id, Carbon::parse('2026-10-14')))->toBeTrue();

    $this->actingAs($this->operator)
        ->delete(route('schedule.day.reset'), ['date' => '2026-10-12'])
        ->assertSessionHasNoErrors();

    expect($service->daySlots($this->operator->id, Carbon::parse('2026-10-12'))->first()['starts_at']->format('H:i'))->toBe('08:00');
});

it('rejects overlapping or reversed periods', function () {
    ($this->savePattern)(['pattern' => [1 => [['start' => '08:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '13:00']]]])
        ->assertSessionHasErrors('pattern.1');

    $this->actingAs($this->operator)
        ->post(route('schedule.day'), ['date' => '2026-10-12', 'periods' => [['start' => '15:00', 'end' => '12:00']]])
        ->assertSessionHasErrors('periods');
});

it('books only inside the working hours', function () {
    ($this->savePattern)();

    $book = fn (string $at) => $this->actingAs($this->operator)->post(route('appointments.store'), [
        'patient_id' => $this->patient->id,
        'starts_at' => $at,
        'duration_minutes' => 45,
    ]);

    $book('2026-10-05 12:30')->assertSessionHasErrors('starts_at');   // during the break
    $book('2026-10-06 09:00')->assertSessionHasErrors('starts_at');   // a day off
    $book('2026-10-05 08:30')->assertSessionHasErrors('starts_at');   // not on a 45-minute slot
    $book('2026-10-05 14:45')->assertSessionHasNoErrors();
});

it('still lets a past visit be documented after the hours change', function () {
    $visit = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => '2026-10-02 17:00',
        'ends_at' => '2026-10-02 17:30',
        'status' => AppointmentStatus::Scheduled,
    ]);

    ($this->savePattern)();

    $this->actingAs($this->operator)
        ->put(route('appointments.update', $visit), [
            'starts_at' => '2026-10-02 17:00:00',
            'duration_minutes' => 30,
            'status' => AppointmentStatus::Completed->value,
            'interview' => 'Ból barku.',
        ])
        ->assertSessionHasNoErrors();

    expect($visit->fresh()->status)->toBe(AppointmentStatus::Completed);
});

it('keeps each schedule to its owner, admins may edit any', function () {
    ($this->savePattern)();
    $other = User::factory()->operator()->create();

    $this->actingAs($other)->get(route('schedule.edit', ['operator' => $this->operator->id]))
        ->assertOk()
        ->assertDontSee('Grafik osoby');

    // The operator parameter is ignored for non-admins — they edit their own.
    $this->actingAs($other)->put(route('schedule.pattern', ['operator' => $this->operator->id]), ['slot_minutes' => 30, 'works' => []]);
    expect(app(SlotService::class)->slotMinutes($this->operator->id))->toBe(45);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('schedule.edit', ['operator' => $this->operator->id]))
        ->assertOk()
        ->assertSee('Czas pracy: '.$this->operator->name);
});

it('shows the schedule page with the next weeks', function () {
    $this->actingAs($this->operator)
        ->get(route('schedule.edit', ['date' => '2026-10-12']))
        ->assertOk()
        ->assertSee('Standardowy tydzień')
        ->assertSee('12.10.2026');
});

it('renders the calendar page with the physiotherapist working hours', function () {
    ($this->savePattern)();

    $this->actingAs($this->operator)
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('"00:45:00"', false)
        ->assertSee('"startTime":"14:00"', false);
});
