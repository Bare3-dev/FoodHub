<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->date('Y-m-d', '-18 years'),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'preferences' => json_encode([
                'dietary_restrictions' => $this->faker->randomElements(['vegetarian', 'vegan', 'gluten_free', 'dairy_free'], $this->faker->numberBetween(0, 2)),
                'favorite_cuisines' => $this->faker->randomElements(['italian', 'chinese', 'mexican', 'indian', 'american'], $this->faker->numberBetween(1, 3)),
                'spice_level' => $this->faker->randomElement(['mild', 'medium', 'hot', 'extra_hot']),
                'notifications' => [
                    'email' => $this->faker->boolean(80),
                    'sms' => $this->faker->boolean(60),
                    'push' => $this->faker->boolean(90)
                ]
            ]),
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
