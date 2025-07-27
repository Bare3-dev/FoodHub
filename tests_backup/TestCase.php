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

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

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
     * Create a restaurant
     */
    protected function createRestaurant(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'status' => 'active',  // Ensure restaurant is active for tests
        ], $attributes));
    }

    /**
     * Create a restaurant branch
     */
    protected function createRestaurantBranch(array $attributes = []): RestaurantBranch
    {
        $restaurant = $attributes['restaurant_id'] ?? $this->createRestaurant()->id;
        
        return RestaurantBranch::factory()->create(array_merge([
            'restaurant_id' => $restaurant,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Create a customer with address
     */
    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes);
    }

    /**
     * Create a driver
     */
    protected function createDriver(array $attributes = []): Driver
    {
        return Driver::factory()->create($attributes);
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
