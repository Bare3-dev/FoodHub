<?php

namespace Tests;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Http\Middleware\HttpsEnforcementMiddleware; // Add this import
use App\Http\Middleware\AdvancedRateLimitMiddleware; // Add this import
use App\Http\Middleware\ApiCorsMiddleware; // Add this import
use App\Http\Middleware\InputSanitizationMiddleware; // Add this import
use App\Http\Middleware\RoleAndPermissionMiddleware; // Add this import
use Illuminate\Support\Facades\Hash; // Add this import

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set memory limit for tests
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300);

        // Explicitly bind middleware aliases for testing environment
        // This resolves "Target class [...] does not exist" errors for route middleware aliases
        $this->app->singleton('https.security', HttpsEnforcementMiddleware::class);
        $this->app->singleton('advanced.rate.limit', AdvancedRateLimitMiddleware::class);
        $this->app->singleton('api.cors', ApiCorsMiddleware::class);
        $this->app->singleton('input.sanitization', InputSanitizationMiddleware::class);
        $this->app->singleton('role.permission', RoleAndPermissionMiddleware::class);

        // RefreshDatabase trait handles database setup efficiently for PostgreSQL
        // No need for manual migrate:fresh in setUp
    }

    protected function tearDown(): void
    {
        // Clean up memory after each test
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        parent::tearDown();
    }

    /**
     * Create a user with specific role and permissions
     */
    protected function createUser(array $attributes = [], string $role = 'CASHIER'): User
    {
        $userAttributes = [
            'role' => $role,
            'permissions' => $this->getDefaultPermissions($role),
            'email_verified_at' => now(),
            'status' => 'active',
        ];

        // Staff roles need restaurant/branch assignments
        if (in_array($role, ['RESTAURANT_OWNER', 'BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF'])) {
            $restaurant = $this->createRestaurant();
            $userAttributes['restaurant_id'] = $restaurant->id;
            
            if (in_array($role, ['BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF'])) {
                $branch = $this->createRestaurantBranch(['restaurant_id' => $restaurant->id]);
                $userAttributes['restaurant_branch_id'] = $branch->id;
            }
        }

        $user = User::factory()->create(array_merge($userAttributes, $attributes));

        return $user;
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
     * Create a restaurant with minimal data for testing
     */
    protected function createRestaurant(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'status' => 'active',  // Ensure restaurant is active for tests
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'cuisine_type' => fake()->randomElement(['italian', 'chinese', 'mexican', 'indian', 'american']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->email(),
            'website' => fake()->url(),
            'business_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '21:00']
            ],
            'settings' => [
                'accepts_cash' => true,
                'accepts_card' => true,
                'max_delivery_distance' => fake()->numberBetween(5, 15),
                'auto_accept_orders' => fake()->boolean(60),
                'peak_hours' => [
                    'lunch' => ['start' => '11:30', 'end' => '14:00'],
                    'dinner' => ['start' => '17:30', 'end' => '21:00']
                ]
            ],
            'commission_rate' => fake()->randomFloat(2, 5.00, 20.00),
            'is_featured' => fake()->boolean(20),
            'verified_at' => fake()->boolean(80) ? fake()->dateTimeBetween('-1 year', 'now') : null,
        ], $attributes));
    }

    /**
     * Create a restaurant branch with minimal data for testing
     */
    protected function createRestaurantBranch(array $attributes = []): RestaurantBranch
    {
        $restaurant = $attributes['restaurant_id'] ?? $this->createRestaurant()->id;
        
        return RestaurantBranch::factory()->create(array_merge([
            'restaurant_id' => $restaurant,
            'status' => 'active',
            'name' => fake()->company() . ' Branch',
            'slug' => fake()->unique()->slug(3),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'SA',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'phone' => fake()->phoneNumber(),
            'manager_name' => fake()->name(),
            'manager_phone' => fake()->phoneNumber(),
            'operating_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '21:00']
            ],
            'delivery_zones' => [
                [
                    'name' => 'Primary Zone',
                    'coordinates' => [
                        ['lat' => fake()->latitude(), 'lng' => fake()->longitude()],
                        ['lat' => fake()->latitude(), 'lng' => fake()->longitude()],
                        ['lat' => fake()->latitude(), 'lng' => fake()->longitude()],
                        ['lat' => fake()->latitude(), 'lng' => fake()->longitude()]
                    ],
                    'delivery_fee' => fake()->randomFloat(2, 2, 8),
                    'estimated_time' => fake()->numberBetween(20, 45)
                ]
            ],
            'delivery_fee' => fake()->randomFloat(2, 2, 8),
            'minimum_order_amount' => fake()->randomFloat(2, 10, 50),
            'estimated_delivery_time' => fake()->numberBetween(20, 45),
            'accepts_online_orders' => true,
            'accepts_delivery' => true,
            'accepts_pickup' => true,
            'settings' => [
                'auto_accept_orders' => fake()->boolean(60),
                'max_delivery_distance' => fake()->numberBetween(5, 15),
                'peak_hours' => [
                    'lunch' => ['start' => '11:30', 'end' => '14:00'],
                    'dinner' => ['start' => '17:30', 'end' => '21:00']
                ]
            ],
        ], $attributes));
    }

    /**
     * Create a customer with minimal data for testing
     */
    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'preferences' => json_encode([
                'dietary_restrictions' => fake()->randomElements(['vegetarian', 'vegan', 'gluten_free', 'dairy_free'], fake()->numberBetween(0, 2)),
                'favorite_cuisines' => fake()->randomElements(['italian', 'chinese', 'mexican', 'indian', 'american'], fake()->numberBetween(1, 3)),
                'spice_level' => fake()->randomElement(['mild', 'medium', 'hot', 'extra_hot']),
                'notifications' => [
                    'email' => fake()->boolean(80),
                    'sms' => fake()->boolean(60),
                    'push' => fake()->boolean(90)
                ]
            ]),
            'status' => 'active',
            'created_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ], $attributes));
    }

    /**
     * Create a driver with minimal data for testing
     */
    protected function createDriver(array $attributes = []): Driver
    {
        return Driver::factory()->create(array_merge([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date('Y-m-d', '-25 years'),
            'national_id' => fake()->numerify('##########'),
            'driver_license_number' => fake()->numerify('DL########'),
            'license_expiry_date' => fake()->dateTimeBetween('now', '+2 years'),
            'profile_image_url' => null,
            'license_image_url' => null,
            'vehicle_type' => fake()->randomElement(['motorcycle', 'car', 'bicycle']),
            'vehicle_make' => fake()->randomElement(['Honda', 'Toyota', 'Yamaha']),
            'vehicle_model' => fake()->randomElement(['CBR150R', 'Corolla', 'R15']),
            'vehicle_year' => fake()->numberBetween(2015, 2024),
            'vehicle_color' => fake()->colorName(),
            'vehicle_plate_number' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
            'vehicle_image_url' => null,
            'status' => 'active',
            'is_online' => fake()->boolean(30),
            'is_available' => fake()->boolean(70),
            'current_latitude' => fake()->latitude(),
            'current_longitude' => fake()->longitude(),
            'rating' => fake()->randomFloat(1, 3, 5),
            'total_deliveries' => fake()->numberBetween(0, 1000),
            'completed_deliveries' => fake()->numberBetween(0, 950),
            'cancelled_deliveries' => fake()->numberBetween(0, 50),
            'total_earnings' => fake()->randomFloat(2, 0, 10000),
            'documents' => json_encode([
                'license' => 'license_url',
                'insurance' => 'insurance_url'
            ]),
        ], $attributes));
    }

    /**
     * Get default permissions for role
     */
    protected function getDefaultPermissions(string $role): array
    {
        return match($role) {
            'SUPER_ADMIN' => ['*'],
            'RESTAURANT_OWNER' => ['restaurant:manage', 'menu:manage', 'staff:manage'],
            'BRANCH_MANAGER' => ['branch:manage', 'menu:manage', 'staff:manage'],
            'CASHIER' => ['order:manage', 'customer:view', 'loyalty-program:apply-points'],
            'KITCHEN_STAFF' => ['order:view', 'order:update-status-own-branch'],
            'DELIVERY_MANAGER' => ['driver:manage', 'delivery:manage'],
            'CUSTOMER_SERVICE' => ['customer:manage', 'loyalty-program:manage'],
            'DRIVER' => ['delivery:accept', 'delivery:update'],
            default => []
        };
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
     * Assert a standardized API error response.
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param int $statusCode
     * @param string|null $message The top-level message to assert.
     * @param array|null $validationFields If it's a 422, the specific validation fields to check (e.g., ['email']).
     */
    protected function assertApiError($response, int $statusCode, ?string $message = null, ?array $validationFields = null): void
    {
        $response->assertStatus($statusCode);

        // All API errors should have 'success' (false), 'message', and an 'errors' key.
        // We'll assert that 'errors' exists as an array, but its content will vary.
        $response->assertJsonStructure([
            'success',
            'message',
            'errors', // Assert that 'errors' key exists as an array (or object in JSON), without detailing its content here
        ]);
        
        $response->assertJson(['success' => false]);

        if ($message !== null) {
            $response->assertJson(['message' => $message]);
        }

        // Special handling for 422 (Validation Exceptions)
        if ($statusCode === 422 && $validationFields !== null) {
            // Use Laravel's built-in assertion for validation errors structure.
            // This checks that the specified fields exist in the 'errors' object and have messages.
            $response->assertJsonValidationErrors($validationFields);
        } else {
            // For non-422 errors, the 'errors' key will contain general error details from Handler.php.
            // We can optionally assert its structure if needed for general errors.
            // For now, ensuring it's present and is an array (or object) is sufficient via `assertJsonStructure(['errors'])`.
            // If more specific assertion on non-validation errors is needed, it would look like:
            // $response->assertJsonStructure(['errors' => ['error', 'error_code', 'timestamp', 'request_id']]);
            // But let's keep it flexible for now, as not all errors might have all these keys.
        }
    }

    /**
     * Helper to assert validation errors, specific to 422 responses.
     * This method can be used directly or by `assertApiError`.
     * Kept separate for clarity and direct use in validation tests.
     */
    protected function assertValidationErrors($response, array $fields): void
    {
        $response->assertStatus(422)
                 ->assertJsonValidationErrors($fields)
                 ->assertJson([
                     'success' => false,
                     'message' => 'The provided data is invalid.' // Default message from Handler.php for validation
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

    /**
     * Make authenticated API request
     */
    protected function apiAs(User $user, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->authHeaders($user))
                    ->json($method, $uri, $data);
    }
}
