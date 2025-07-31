<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderAssignment>
 */
class OrderAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'order_id' => Order::factory(),
            'assigned_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'status' => $this->faker->randomElement(['assigned', 'in_progress', 'completed', 'cancelled']),
        ];
    }
} 