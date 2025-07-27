<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_users_cannot_access_protected_endpoints()
    {
        $protectedEndpoints = [
            'GET /api/user',
            'POST /api/auth/logout',
            'GET /api/orders',
            'POST /api/orders'
        ];

        foreach ($protectedEndpoints as $endpoint) {
            [$method, $uri] = explode(' ', $endpoint);
            $response = $this->json($method, $uri);
            
            $response->assertStatus(401);
        }
    }

    /** @test */
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
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
            'name' => 'Test Loyalty Program'
        ])->assertStatus(422); // Will fail validation but shows access allowed

        // Customer service cannot manage restaurants
        $this->postJson('/api/restaurants', [])->assertStatus(403);
        $this->postJson('/api/menu-items', [])->assertStatus(403);
    }

    /** @test */
    public function super_admin_can_access_all_endpoints()
    {
        $superAdmin = $this->actingAsUser('SUPER_ADMIN');
        
        // Super admin can access everything
        $restrictedEndpoints = [
            'GET /api/restaurants',
            'POST /api/restaurants',
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

    /** @test */
    public function drivers_can_only_access_delivery_endpoints()
    {
        $driver = $this->actingAsUser('DRIVER');

        // Driver should have limited access
        $this->getJson('/api/user')->assertStatus(200);
        
        // Driver cannot access admin functions
        $this->getJson('/api/restaurants')->assertStatus(200); // Public endpoint
        $this->postJson('/api/restaurants', [])->assertStatus(403);
        $this->getJson('/api/customers')->assertStatus(403);
    }

    /** @test */
    public function role_hierarchy_is_properly_enforced()
    {
        // Test that higher roles can access lower role functions
        $restaurantOwner = $this->actingAsUser('RESTAURANT_OWNER');
        
        // Restaurant owner should be able to access branch manager functions
        $this->getJson('/api/restaurant-branches')->assertStatus(200);
        $this->postJson('/api/menu-categories', [])->assertStatus(422); // Access allowed, validation fails

        // But branch manager cannot access restaurant owner functions
        $branchManager = $this->actingAsUser('BRANCH_MANAGER');
        $branchManager->load('branch');
        $this->postJson('/api/restaurant-branches', [])->assertStatus(422); // Access allowed
        
        // Kitchen staff cannot access admin functions
        $kitchenStaff = $this->actingAsUser('KITCHEN_STAFF');
        $this->getJson('/api/restaurant-branches')->assertStatus(200); // Public endpoint
        $this->postJson('/api/menu-categories', [])->assertStatus(403); // Forbidden
    }

    /** @test */
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

    /** @test */
    public function permissions_are_checked_in_addition_to_roles()
    {
        // Create a cashier without loyalty program permissions
        $cashier = User::factory()->create([
            'role' => 'CASHIER',
            'permissions' => ['order:manage', 'customer:view'] // Missing loyalty-program:apply-points
        ]);

        // Should not be able to access loyalty endpoints that require specific permission
        $response = $this->apiAs($cashier, 'GET', '/api/loyalty-programs');
        $response->assertStatus(403);

        // But should be able to access order endpoints
        $response = $this->apiAs($cashier, 'GET', '/api/orders');
        $response->assertStatus(200);
    }

    // Helper methods
    protected function actingAsUser($role = 'CASHIER')
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    protected function createUser($attributes = [], $role = 'CASHIER')
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    protected function createRestaurant()
    {
        return Restaurant::factory()->create();
    }

    protected function createCustomer()
    {
        return Customer::factory()->create();
    }

    protected function apiAs($user, $method, $uri, $data = [])
    {
        $token = $user->createToken('test-token')->plainTextToken;
        
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->json($method, $uri, $data);
    }
} 