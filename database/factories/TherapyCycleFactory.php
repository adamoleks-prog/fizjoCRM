<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapyCycle>
 */
class TherapyCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => User::factory()->operator(),
            'patient_id' => fn (array $attributes) => Patient::factory()->create([
                'operator_id' => $attributes['operator_id'],
            ]),
            'name' => 'Cykl od '.fake()->date('d.m.Y'),
        ];
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patient->id,
            'operator_id' => $patient->operator_id,
        ]);
    }
}
