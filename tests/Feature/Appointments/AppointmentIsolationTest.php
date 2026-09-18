<?php

use App\Models\Appointment;
use App\Models\User;

it('blocks an operator from viewing another operators appointment', function () {
    $operator = User::factory()->operator()->create();
    $foreign = Appointment::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)
        ->get(route('appointments.show', $foreign))
        ->assertNotFound();
});

it('blocks an operator from editing another operators appointment', function () {
    $operator = User::factory()->operator()->create();
    $foreign = Appointment::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)
        ->put(route('appointments.update', $foreign), [
            'starts_at' => '2026-11-01 09:00:00',
            'duration_minutes' => 30,
            'status' => 'completed',
        ])
        ->assertNotFound();
});

it('returns only own appointments in the calendar feed', function () {
    $operator = User::factory()->operator()->create();

    Appointment::factory()->forOperator($operator)->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 10:00:00',
    ]);
    Appointment::factory()->forOperator(User::factory()->operator()->create())->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 10:00:00',
    ]);

    $response = $this->actingAs($operator)
        ->getJson(route('appointments.calendar-feed', ['start' => '2026-10-01', 'end' => '2026-10-31']));

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

it('lets an admin see appointments of all operators in the feed', function () {
    $admin = User::factory()->admin()->create();

    Appointment::factory()->forOperator(User::factory()->operator()->create())->create([
        'starts_at' => '2026-10-05 09:00:00',
        'ends_at' => '2026-10-05 10:00:00',
    ]);
    Appointment::factory()->forOperator(User::factory()->operator()->create())->create([
        'starts_at' => '2026-10-06 09:00:00',
        'ends_at' => '2026-10-06 10:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('appointments.calendar-feed', ['start' => '2026-10-01', 'end' => '2026-10-31']));

    expect($response->json())->toHaveCount(2);
});
