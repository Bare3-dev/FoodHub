<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Restaurant;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RestaurantBranch>
 */
class RestaurantBranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $this->faker->streetName() . ' Branch',
            'slug' => $this->faker->unique()->slug(2),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'United States',
            'latitude' => $this->faker->latitude(25, 49), // US bounds
            'longitude' => $this->faker->longitude(-125, -66), // US bounds
            'phone' => $this->faker->phoneNumber(),
            'manager_name' => $this->faker->name(),
            'manager_phone' => $this->faker->phoneNumber(),
            'operating_hours' => [
                'monday' => ['open' => '10:00', 'close' => '22:00'],
                'tuesday' => ['open' => '10:00', 'close' => '22:00'],
                'wednesday' => ['open' => '10:00', 'close' => '22:00'],
                'thursday' => ['open' => '10:00', 'close' => '22:00'],
                'friday' => ['open' => '10:00', 'close' => '23:00'],
                'saturday' => ['open' => '09:00', 'close' => '23:00'],
                'sunday' => ['open' => '11:00', 'close' => '21:00']
            ],
            'delivery_zones' => [
                [
                    'name' => 'Zone 1',
                    'radius' => 5,
                    'fee' => 2.99
                ],
                [
                    'name' => 'Zone 2', 
                    'radius' => 10,
                    'fee' => 4.99
                ]
            ],
            'delivery_fee' => $this->faker->randomFloat(2, 0, 5),
            'minimum_order_amount' => $this->faker->randomFloat(2, 10, 25),
            'estimated_delivery_time' => $this->faker->numberBetween(20, 45),
            'status' => $this->faker->randomElement(['active', 'inactive', 'temporarily_closed']),
            'accepts_online_orders' => $this->faker->boolean(95),
            'accepts_delivery' => $this->faker->boolean(85),
            'accepts_pickup' => $this->faker->boolean(95),
            'settings' => [],
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
