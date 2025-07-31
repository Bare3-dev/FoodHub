<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StampCard>
 */
class StampCardFactory extends Factory
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
            'stamps_earned' => $this->faker->numberBetween(0, 10),
            'stamps_required' => $this->faker->numberBetween(5, 15),
            'is_completed' => $this->faker->boolean(20),
            'completed_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }
} 