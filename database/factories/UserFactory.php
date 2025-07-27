<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roles = [
            'SUPER_ADMIN', 'RESTAURANT_OWNER', 'BRANCH_MANAGER',
            'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 
            'CUSTOMER_SERVICE'
        ];

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement($roles),
            'permissions' => [],
            'status' => fake()->randomElement(['active', 'inactive', 'suspended']),
            'phone' => fake()->phoneNumber(),
            'is_email_verified' => fake()->boolean(80),
            'remember_token' => Str::random(10),
            'role' => 'CUSTOMER_SERVICE',
            'permissions' => [],
            'status' => 'active',
            'is_email_verified' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
