<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        $userTypes = ['customer', 'driver', 'user'];
        $userType = $this->faker->randomElement($userTypes);
        
        // Generate appropriate user ID based on user type
        $userId = match ($userType) {
            'customer' => Customer::factory(),
            'driver' => Driver::factory(),
            'user' => User::factory(),
        };

        return [
            'user_type' => $userType,
            'user_id' => $userId,
            'token' => $this->faker->regexify('[A-Za-z0-9]{152}'), // FCM token format
            'platform' => $this->faker->randomElement(['ios', 'android']),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'last_used_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function ios(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'ios',
        ]);
    }

    public function android(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'android',
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'customer',
            'user_id' => Customer::factory(),
        ]);
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'driver',
            'user_id' => Driver::factory(),
        ]);
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'user',
            'user_id' => User::factory(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
