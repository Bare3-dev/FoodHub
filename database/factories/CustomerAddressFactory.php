<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => $this->faker->randomElement(['Home', 'Work', 'Office', 'Other']),
            'street_address' => $this->faker->streetAddress(),
            'apartment_number' => $this->faker->optional()->bothify('Apt ##?'),
            'building_name' => $this->faker->optional()->company() . ' Building',
            'floor_number' => $this->faker->optional()->numberBetween(1, 20),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'Saudi Arabia',
            'latitude' => $this->faker->latitude(21, 32), // Saudi Arabia bounds
            'longitude' => $this->faker->longitude(34, 56), // Saudi Arabia bounds
            'delivery_notes' => $this->faker->optional()->sentence(),
            'is_default' => $this->faker->boolean(20), // 20% chance of being default
            'is_validated' => $this->faker->boolean(80), // 80% chance of being validated
            'validated_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the address is the default address.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'is_validated' => true,
            'validated_at' => now(),
        ]);
    }

    /**
     * Indicate that the address is validated.
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_validated' => true,
            'validated_at' => now(),
        ]);
    }
} 