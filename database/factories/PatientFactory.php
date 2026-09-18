<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => User::factory()->operator(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('### ### ###'),
            'email' => fake()->unique()->safeEmail(),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'address' => fake()->streetAddress().', '.fake()->postcode().' '.fake()->city(),
            'notes' => null,
        ];
    }

    public function forOperator(User $operator): static
    {
        return $this->state(fn (array $attributes) => [
            'operator_id' => $operator->id,
        ]);
    }
}
