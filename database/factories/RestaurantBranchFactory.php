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
        $name = $this->faker->streetName() . ' Branch';
        
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'United States',
            'latitude' => $this->faker->latitude(25, 49), // US bounds
            'longitude' => $this->faker->longitude(-125, -66), // US bounds
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'operating_hours' => json_encode([
                'monday' => ['open' => '10:00', 'close' => '22:00'],
                'tuesday' => ['open' => '10:00', 'close' => '22:00'],
                'wednesday' => ['open' => '10:00', 'close' => '22:00'],
                'thursday' => ['open' => '10:00', 'close' => '22:00'],
                'friday' => ['open' => '10:00', 'close' => '23:00'],
                'saturday' => ['open' => '09:00', 'close' => '23:00'],
                'sunday' => ['open' => '11:00', 'close' => '21:00']
            ]),
            'delivery_zones' => json_encode([
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
            ]),
            'status' => $this->faker->randomElement(['active', 'inactive', 'temporarily_closed']),
            'accepts_online_orders' => $this->faker->boolean(95),
            'accepts_delivery' => $this->faker->boolean(95),
            'accepts_pickup' => $this->faker->boolean(95),
            'delivery_fee' => $this->faker->randomFloat(2, 0, 10),
            'minimum_order_amount' => $this->faker->randomFloat(2, 0, 25),
            'estimated_delivery_time' => $this->faker->numberBetween(15, 60),
            'settings' => json_encode($this->faker->randomElements([
                'parking', 'wifi', 'outdoor_seating', 'takeout', 'delivery', 
                'wheelchair_accessible', 'kids_menu', 'live_music'
            ], $this->faker->numberBetween(2, 5))),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
