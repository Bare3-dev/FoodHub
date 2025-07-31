<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerChallenge>
 */
class CustomerChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'customer_id' => Customer::factory(),
            'challenge_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'target_value' => $this->faker->numberBetween(10, 100),
            'current_value' => $this->faker->numberBetween(0, 50),
            'is_completed' => $this->faker->boolean(30),
            'completed_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'reward_points' => $this->faker->numberBetween(100, 1000),
        ];
    }
} 