<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slotMinutes = (int) config('appointments.slot_minutes');
        [$hour, $minute] = explode(':', config('appointments.working_hours.start'));

        $startsAt = Carbon::parse(fake()->dateTimeBetween('-1 month', '+1 month'))
            ->setTime((int) $hour, (int) $minute)
            ->addMinutes($slotMinutes * fake()->numberBetween(0, 8));

        while (! in_array($startsAt->isoWeekday(), config('appointments.working_days'), true)) {
            $startsAt = $startsAt->addDay();
        }

        return [
            'operator_id' => User::factory()->operator(),
            'patient_id' => fn (array $attributes) => Patient::factory()->create([
                'operator_id' => $attributes['operator_id'],
            ]),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($slotMinutes),
            'status' => AppointmentStatus::Scheduled,
        ];
    }

    public function forOperator(User $operator): static
    {
        return $this->state(fn (array $attributes) => [
            'operator_id' => $operator->id,
        ]);
    }

    public function forCycle(TherapyCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'therapy_cycle_id' => $cycle->id,
            'patient_id' => $cycle->patient_id,
            'operator_id' => $cycle->operator_id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'icd10_code' => 'M54.5',
            'procedures' => 'Terapia manualna, ćwiczenia stabilizacyjne',
            'treatment_notes' => fake()->sentence(),
        ]);
    }
}
