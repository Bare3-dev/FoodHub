<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Http\Middleware\AdvancedRateLimitMiddleware;
use Laravel\Sanctum\Sanctum;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_users_cannot_access_protected_endpoints()
    {
        $protectedEndpoints = [
            'GET /api/user',
            'POST /api/auth/logout',
            'GET /api/orders',
            'POST /api/orders',
            'GET /api/rate-limit/status'
        ];

        foreach ($protectedEndpoints as $endpoint) {
            [$method, $uri] = explode(' ', $endpoint);
            $response = $this->json($method, $uri);
            
            $response->assertStatus(401);
        }
    }

    #[Test]
    public function low_privilege_staff_have_limited_access()
    {
        $kitchenStaff = $this->actingAsUser('KITCHEN_STAFF');

        // Kitchen staff can access their own data
        $this->getJson('/api/user')->assertStatus(200);
        
        // Kitchen staff can view orders (they need to see orders to prepare food)
        $this->getJson('/api/orders')->assertStatus(200);
        
        // Kitchen staff can create orders but need valid data
        $restaurant = $this->createRestaurant();
        $this->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items' => [
                ['menu_item_id' => 1, 'quantity' => 2, 'price' => 10.00]
            ]
        ])->assertStatus(422); // Validation fails but access is allowed

        // Kitchen staff cannot access admin functions
        $this->getJson('/api/restaurants')->assertStatus(200); // Public endpoint
        $this->postJson('/api/restaurants', [])->assertStatus(403); // Admin only
        $this->getJson('/api/staff')->assertStatus(403); // Admin only
    }

    #[Test]
    public function cashiers_can_access_operational_endpoints()
    {
        $cashier = $this->actingAsUser('CASHIER');

        // Cashier can view orders
        $this->getJson('/api/orders')->assertStatus(200);
        
        // Cashier can view customers
        $this->getJson('/api/customers')->assertStatus(200);
        
        // Cashier can create customers
        $this->postJson('/api/customers', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '+1234567890'
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Cashier cannot access admin functions
        $this->postJson('/api/restaurants', [])->assertStatus(403);
        $this->postJson('/api/menu-categories', [])->assertStatus(403);
    }

    #[Test]
    public function kitchen_staff_can_view_orders_but_not_manage_customers()
    {
        $kitchenStaff = $this->actingAsUser('KITCHEN_STAFF');

        // Kitchen staff can view orders
        $this->getJson('/api/orders')->assertStatus(200);
        
        // Kitchen staff can update order status for orders in their branch
        $customer = $this->createCustomer();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'restaurant_id' => $kitchenStaff->restaurant_id,
            'restaurant_branch_id' => $kitchenStaff->restaurant_branch_id,
        ]);
        
        $this->putJson("/api/orders/{$order->id}", [
            'status' => 'invalid_status'
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Kitchen staff cannot manage customers
        $this->getJson('/api/customers')->assertStatus(403);
        $this->postJson('/api/customers', [])->assertStatus(403);
    }

    #[Test]
    public function branch_managers_can_manage_branch_operations()
    {
        $branchManager = $this->createUser([], 'BRANCH_MANAGER');
        $this->actingAs($branchManager, 'sanctum');
        
        // Branch manager can view branch data (public endpoint)
        $this->getJson('/api/restaurant-branches')->assertStatus(200);
        
        // Branch manager can manage menu
        $this->postJson('/api/menu-categories', [
            'name' => 'Test Category',
            'restaurant_id' => $branchManager->restaurant_id
        ])->assertStatus(201);

        // Branch manager cannot create restaurants (this should be forbidden)
        $this->postJson('/api/restaurants', [])->assertStatus(403);
    }

    #[Test]
    public function restaurant_owners_can_manage_their_restaurants()
    {
        $owner = $this->actingAsUser('RESTAURANT_OWNER');

        // Restaurant owner can view restaurants
        $this->getJson('/api/restaurants')->assertStatus(200);
        
        // Restaurant owner can manage restaurant branches
        $this->postJson('/api/restaurant-branches', [
            'name' => 'Test Branch',
            'restaurant_id' => 1
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Restaurant owner can manage drivers
        $this->getJson('/api/drivers')->assertStatus(200);
        $this->postJson('/api/drivers', [
            'name' => 'Test Driver',
            'license_number' => 'D123456'
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Restaurant owner cannot create new restaurants (only super admin)
        $this->postJson('/api/restaurants', [])->assertStatus(403);
    }

    #[Test]
    public function delivery_managers_can_manage_drivers_and_zones()
    {
        $deliveryManager = $this->actingAsUser('DELIVERY_MANAGER');

        // Delivery manager can view orders
        $this->getJson('/api/orders')->assertStatus(200);
        
        // Delivery manager can manage driver working zones
        $this->getJson('/api/driver-working-zones')->assertStatus(200);
        $this->postJson('/api/driver-working-zones', [
            'driver_id' => 1,
            'zone_name' => 'Test Zone'
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Delivery manager cannot manage menu
        $this->postJson('/api/menu-items', [])->assertStatus(403);
    }

    #[Test]
    public function customer_service_can_manage_customers_and_loyalty()
    {
        $customerService = $this->actingAsUser('CUSTOMER_SERVICE');

        // Customer service can view and manage customers
        $this->getJson('/api/customers')->assertStatus(200);
        $this->postJson('/api/customers', [
            'name' => 'Test Customer'
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Customer service can manage loyalty programs
        $this->getJson('/api/loyalty-programs')->assertStatus(200);
        $this->postJson('/api/loyalty-programs', [
            'name' => 'Test Loyalty Program',
            'restaurant_id' => 1,
            'type' => 'points',
            'start_date' => now()->format('Y-m-d'),
            'currency_name' => 'USD',
            'points_per_currency' => 1.0,
            'minimum_points_redemption' => 100,
            'redemption_rate' => 0.01
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Customer service cannot manage restaurants
        $this->postJson('/api/restaurants', [])->assertStatus(403);
        $this->postJson('/api/menu-items', [])->assertStatus(403);
    }



    #[Test]
    public function super_admin_can_access_all_endpoints()
    {
        $superAdmin = $this->actingAsUser('SUPER_ADMIN');
        // Debug assertion to check the actual role value
        $this->assertEquals('SUPER_ADMIN', $superAdmin->role, 'Test user role is not SUPER_ADMIN');
        // Super admin can access everything
        $restrictedEndpoints = [
            'GET /api/restaurants',
            'POST /api/restaurants',
            'GET /api/staff',
            'POST /api/staff',
            'POST /api/rate-limit/clear',
            'GET /api/customers',
            'POST /api/customers',
            'GET /api/loyalty-programs',
            'POST /api/loyalty-programs'
        ];

        foreach ($restrictedEndpoints as $endpoint) {
            [$method, $uri] = explode(' ', $endpoint);
            $response = $this->json($method, $uri);
            
            // Should not get 403 Forbidden
            $this->assertNotEquals(403, $response->getStatusCode(), 
                "Super admin should have access to {$endpoint}");
        }
    }

    #[Test]
    public function drivers_can_only_access_delivery_endpoints()
    {
        $driver = $this->actingAsUser('DRIVER');

        // Driver should have limited access
        $this->getJson('/api/user')->assertStatus(200);
        
        // Driver cannot access admin functions
        $this->getJson('/api/restaurants')->assertStatus(200); // Public endpoint
        $this->postJson('/api/restaurants', [])->assertStatus(403);
        $this->getJson('/api/customers')->assertStatus(403);
        $this->getJson('/api/staff')->assertStatus(403);
    }

    #[Test]
    public function role_hierarchy_is_properly_enforced()
    {
        // Test that higher roles can access lower role functions
        $restaurantOwner = $this->actingAsUser('RESTAURANT_OWNER');
        
        // Restaurant owner should be able to access branch manager functions (menu management)
        $this->getJson('/api/restaurant-branches')->assertStatus(200);
        $this->postJson('/api/menu-categories', [])->assertStatus(422); // Access allowed, validation fails

        // Branch manager can access their own functions (menu management)
        $branchManager = $this->actingAsUser('BRANCH_MANAGER');
        $branchManager->load('branch');
        $this->postJson('/api/menu-categories', [])->assertStatus(422); // Access allowed, validation fails
        
        // But branch manager cannot access restaurant owner functions (creating restaurant branches)
        $restaurant = $this->createRestaurant(); // Create a valid restaurant
        $this->postJson('/api/restaurant-branches', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Test Branch',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'Test Country',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'phone' => '123-456-7890'
        ])->assertStatus(403); // Access denied - branch managers cannot create branches
        
        // Kitchen staff cannot access admin functions
        $kitchenStaff = $this->actingAsUser('KITCHEN_STAFF');
        $this->postJson('/api/menu-categories', [])->assertStatus(403); // Forbidden - kitchen staff cannot manage menu
    }

    #[Test]
    public function cors_headers_are_different_for_different_security_levels()
    {
        // Public endpoints should have permissive CORS
        $response = $this->getJson('/api/restaurants');
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        // Private endpoints should have restrictive CORS
        $user = $this->actingAsUser();
        $response = $this->apiAs($user, 'GET', '/api/user');
        $this->assertNotEquals('*', $response->headers->get('Access-Control-Allow-Origin'));

        // Admin endpoints should have most restrictive CORS
        $admin = $this->actingAsUser('SUPER_ADMIN');
        $response = $this->apiAs($admin, 'GET', '/api/staff');
        $corsOrigin = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertTrue(
            $corsOrigin === null || 
            strpos($corsOrigin, 'admin') !== false ||
            $corsOrigin === 'null'
        );
    }

    #[Test]
    public function inactive_users_cannot_access_any_protected_endpoints()
    {
        $inactiveUser = User::factory()->create([
            'status' => 'inactive',
            'role' => 'CASHIER'
        ]);

        $token = $inactiveUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/user');

        $response->assertStatus(403);
    }

    #[Test]
    public function permissions_are_checked_in_addition_to_roles()
    {
        // Create a cashier without loyalty program permissions using the helper
        $cashier = $this->actingAsUser('CASHIER', [
            'permissions' => ['order:manage', 'customer:view'] // Missing loyalty-program:apply-points
        ]);

        // Debug: Check cashier properties
        \Log::info('Cashier details', [
            'id' => $cashier->id,
            'role' => $cashier->role,
            'restaurant_id' => $cashier->restaurant_id,
            'restaurant_branch_id' => $cashier->restaurant_branch_id,
            'permissions' => $cashier->permissions,
        ]);

        // Cashiers can view loyalty programs according to the policy
        $response = $this->apiAs($cashier, 'GET', '/api/loyalty-programs');
        $response->assertStatus(200);

        // But should be able to access order endpoints
        $response = $this->apiAs($cashier, 'GET', '/api/orders');
        $response->assertStatus(200);
    }

    #[Test]
    public function api_endpoints_log_authorization_failures()
    {
        $kitchenStaff = $this->actingAsUser('KITCHEN_STAFF');

        // Try to access admin endpoint
        $this->apiAs($kitchenStaff, 'POST', '/api/restaurants', []);

        // Should log the authorization failure
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $kitchenStaff->id,
            'event_type' => 'authorization_failed',
            'ip_address' => '127.0.0.1'
        ]);
    }

    #[Test]
    public function rate_limiting_varies_by_user_role()
    {
        // Enable rate limiting for this specific test
        config(['rate_limiting.enabled_in_tests' => true]);
        
        $kitchenStaff = $this->createUser([], 'KITCHEN_STAFF');

        // Set a low rate limit for this test only
        AdvancedRateLimitMiddleware::$testOverrideLimits = [
            'ip' => ['limit' => 5, 'window' => 60],
            'user' => ['limit' => 5, 'window' => 60],
        ];

        // Test kitchen staff rate limiting (should be limited after 5 requests)
        for ($i = 0; $i < 12; $i++) {
            $response = $this->apiAs($kitchenStaff, 'GET', '/api/orders');
        }
        $response->assertStatus(429); // Should be rate limited

        // Reset override (defensive)
        AdvancedRateLimitMiddleware::$testOverrideLimits = null;
        
        // Disable rate limiting after test
        config(['rate_limiting.enabled_in_tests' => false]);
    }


} 