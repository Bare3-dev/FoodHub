<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryReview>
 */
class DeliveryReviewFactory extends Factory
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
            'rating' => $this->faker->randomFloat(2, 1, 5),
            'comment' => $this->faker->optional()->sentence(),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }
} 