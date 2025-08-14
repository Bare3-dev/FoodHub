<?php

namespace Tests;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

/**
 * Optimized TestCase that only creates data when explicitly needed
 * This prevents the hanging issues caused by automatic data creation
 */
abstract class OptimizedTestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal setup - no automatic data creation
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 120);
    }

    protected function tearDown(): void
    {
        // Lightweight cleanup
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        parent::tearDown();
    }

    /**
     * Create a user ONLY when explicitly called (not automatically)
     */
    protected function createUser(array $attributes = [], string $role = 'CASHIER'): User
    {
        $userAttributes = [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'email_verified_at' => now(),
            'status' => 'active',
        ];

        return User::factory()->create(array_merge($userAttributes, $attributes));
    }

    /**
     * Create and authenticate a user for API testing
     */
    protected function actingAsUser(string $role = 'CASHIER', array $attributes = []): User
    {
        $user = $this->createUser($attributes, $role);
        Sanctum::actingAs($user);
        return $user;
    }

    /**
     * Create a restaurant ONLY when explicitly called
     */
    protected function createRestaurant(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'status' => 'active',
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->email(),
        ], $attributes));
    }

    /**
     * Create a restaurant branch ONLY when explicitly called
     */
    protected function createRestaurantBranch(array $attributes = []): RestaurantBranch
    {
        $restaurant = $attributes['restaurant_id'] ?? $this->createRestaurant()->id;
        
        return RestaurantBranch::factory()->create(array_merge([
            'restaurant_id' => $restaurant,
            'status' => 'active',
            'name' => fake()->company() . ' Branch',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
        ], $attributes));
    }

    /**
     * Create a customer ONLY when explicitly called
     */
    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Create a driver ONLY when explicitly called
     */
    protected function createDriver(array $attributes = []): Driver
    {
        return Driver::factory()->create(array_merge([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Assert API response structure
     */
    protected function assertApiResponse($response, ?array $expectedStructure = null): void
    {
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => $expectedStructure,
        ]);
    }

    /**
     * Create authorization headers for API requests
     */
    protected function authHeaders(?User $user = null): array
    {
        if (!$user) {
            $user = $this->createUser();
        }

        $token = $user->createToken('test-token')->plainTextToken;
        
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
