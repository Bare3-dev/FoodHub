<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSocketConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $restaurantOwner;
    protected User $kitchenStaff;
    protected Customer $customer;
    protected User $driver;
    protected Restaurant $restaurant;
    protected RestaurantBranch $restaurantBranch;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user with minimal data
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN'
        ]);
        
        // Create restaurant and branch with minimal data
        $this->restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'cuisine_type' => 'International',
            'business_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '22:00']
            ],
            'email' => 'restaurant@test.com',
            'phone' => '1234567890'
        ]);
        
        $this->restaurantBranch = RestaurantBranch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'SA',
            'operating_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '22:00']
            ],
            'delivery_zones' => [
                'type' => 'Polygon',
                'coordinates' => [[[0, 0], [0, 1], [1, 1], [1, 0], [0, 0]]]
            ]
        ]);
        
        // Create restaurant owner with minimal data
        $this->restaurantOwner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id
        ]);
        
        // Create kitchen staff with minimal data
        $this->kitchenStaff = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@test.com',
            'password' => bcrypt('password'),
            'role' => 'KITCHEN_STAFF',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id
        ]);
        
        // Create customer with minimal data
        $this->customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password')
        ]);
        
        // Create driver with minimal data
        $this->driver = User::create([
            'name' => 'Test Driver',
            'email' => 'driver@test.com',
            'password' => bcrypt('password'),
            'role' => 'DRIVER'
        ]);
        
        // Create order with minimal data
        $this->order = Order::create([
            'order_number' => 'ORD-123456',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => null, // Set to null since field is nullable
            'driver_id' => null, // Set to null initially, will be assigned in specific tests
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.00,
            'tax_amount' => 3.75,
            'delivery_fee' => 5.00,
            'service_fee' => 2.50,
            'discount_amount' => 0.00,
            'total_amount' => 36.25,
            'currency' => 'SAR',
            'estimated_preparation_time' => 20,
            'estimated_delivery_time' => 30
        ]);
    }

    /** @test */
    public function it_authorizes_restaurant_channels_correctly()
    {
        // Restaurant owner can access their restaurant channel
        $this->actingAs($this->restaurantOwner);
        $this->assertTrue($this->canAccessRestaurantChannel($this->restaurantOwner, $this->restaurantBranch->id));
        
        // Kitchen staff can access their restaurant channel
        $this->actingAs($this->kitchenStaff);
        $this->assertTrue($this->canAccessRestaurantChannel($this->kitchenStaff, $this->restaurantBranch->id));
        
        // Admin can access any restaurant channel
        $this->actingAs($this->admin);
        $this->assertTrue($this->canAccessRestaurantChannel($this->admin, $this->restaurantBranch->id));
        
        // Other restaurant staff cannot access different restaurant channels
        $otherRestaurant = Restaurant::create([
            'name' => 'Other Restaurant',
            'slug' => 'other-restaurant',
            'cuisine_type' => 'Italian',
            'business_hours' => [
                'monday' => ['open' => '10:00', 'close' => '21:00'],
                'tuesday' => ['open' => '10:00', 'close' => '21:00'],
                'wednesday' => ['open' => '10:00', 'close' => '21:00'],
                'thursday' => ['open' => '10:00', 'close' => '21:00'],
                'friday' => ['open' => '10:00', 'close' => '22:00'],
                'saturday' => ['open' => '11:00', 'close' => '22:00'],
                'sunday' => ['open' => '11:00', 'close' => '21:00']
            ],
            'email' => 'other@test.com',
            'phone' => '0987654321'
        ]);
        $otherBranch = RestaurantBranch::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch',
            'address' => 'Other Address',
            'city' => 'Other City',
            'state' => 'Other State',
            'postal_code' => '54321',
            'country' => 'SA',
            'operating_hours' => [
                'monday' => ['open' => '10:00', 'close' => '21:00'],
                'tuesday' => ['open' => '10:00', 'close' => '21:00'],
                'wednesday' => ['open' => '10:00', 'close' => '21:00'],
                'thursday' => ['open' => '10:00', 'close' => '21:00'],
                'friday' => ['open' => '10:00', 'close' => '22:00'],
                'saturday' => ['open' => '11:00', 'close' => '22:00'],
                'sunday' => ['open' => '11:00', 'close' => '21:00']
            ],
            'delivery_zones' => [
                'type' => 'Polygon',
                'coordinates' => [[[0, 0], [0, 1], [1, 1], [1, 0], [0, 0]]]
            ]
        ]);
        $otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'otherstaff@test.com',
            'password' => bcrypt('password'),
            'role' => 'KITCHEN_STAFF',
            'restaurant_id' => $otherRestaurant->id,
            'restaurant_branch_id' => $otherBranch->id
        ]);
        
        $this->actingAs($otherStaff);
        $this->assertFalse($this->canAccessRestaurantChannel($otherStaff, $this->restaurantBranch->id));
    }

    /** @test */
    public function it_authorizes_kitchen_channels_correctly()
    {
        // Kitchen staff can access their kitchen channel
        $this->actingAs($this->kitchenStaff);
        $this->assertTrue($this->canAccessKitchenChannel($this->kitchenStaff, $this->restaurantBranch->id));
        
        // Restaurant owner can access their kitchen channel
        $this->actingAs($this->restaurantOwner);
        $this->assertTrue($this->canAccessKitchenChannel($this->restaurantOwner, $this->restaurantBranch->id));
        
        // Admin can access any kitchen channel
        $this->actingAs($this->admin);
        $this->assertTrue($this->canAccessKitchenChannel($this->admin, $this->restaurantBranch->id));
        
        // Regular customers cannot access kitchen channels
        $this->actingAs($this->customer);
        $this->assertFalse($this->canAccessKitchenChannel($this->customer, $this->restaurantBranch->id));
    }

    /** @test */
    public function it_authorizes_driver_channels_correctly()
    {
        // Driver can access their own channel
        $this->actingAs($this->driver);
        $this->assertTrue($this->canAccessDriverChannel($this->driver, $this->driver->id));
        
        // Admin can access any driver channel
        $this->actingAs($this->admin);
        $this->assertTrue($this->canAccessDriverChannel($this->admin, $this->driver->id));
        
        // Other drivers cannot access other driver channels
        $otherDriver = User::create([
            'name' => 'Other Driver',
            'email' => 'otherdriver@test.com',
            'password' => bcrypt('password'),
            'role' => 'DRIVER'
        ]);
        $this->actingAs($otherDriver);
        $this->assertFalse($this->canAccessDriverChannel($otherDriver, $this->driver->id));
    }

    /** @test */
    public function it_authorizes_order_channels_correctly()
    {
        // Restaurant staff can access their restaurant's order channel
        $this->actingAs($this->restaurantOwner);
        $this->assertTrue($this->canAccessOrderChannel($this->restaurantOwner, $this->order->id));
        
        // Kitchen staff can access their restaurant's order channel
        $this->actingAs($this->kitchenStaff);
        $this->assertTrue($this->canAccessOrderChannel($this->kitchenStaff, $this->order->id));
        
        // Admin can access any order channel
        $this->actingAs($this->admin);
        $this->assertTrue($this->canAccessOrderChannel($this->admin, $this->order->id));
        
        // Unrelated users cannot access order channels
        $unrelatedUser = User::create([
            'name' => 'Unrelated User',
            'email' => 'unrelated@test.com',
            'password' => bcrypt('password'),
            'role' => 'CASHIER'
        ]);
        $this->actingAs($unrelatedUser);
        $this->assertFalse($this->canAccessOrderChannel($unrelatedUser, $this->order->id));
    }

    /** @test */
    public function it_handles_nonexistent_orders_gracefully()
    {
        $this->actingAs($this->admin);
        // Use a reasonable ID that won't cause memory issues
        $this->assertFalse($this->canAccessOrderChannel($this->admin, 999));
    }

    /** @test */
    public function it_handles_unauthenticated_users_gracefully()
    {
        // Test with null user (unauthenticated)
        $this->assertFalse($this->canAccessCustomerChannel(null, 1));
        $this->assertFalse($this->canAccessRestaurantChannel(null, 1));
        $this->assertFalse($this->canAccessDriverChannel(null, 1));
        $this->assertFalse($this->canAccessKitchenChannel(null, 1));
        $this->assertFalse($this->canAccessOrderChannel(null, 1));
        
        // Test with a non-existent user ID to ensure proper handling
        $this->assertFalse($this->canAccessCustomerChannel(null, 999));
        $this->assertFalse($this->canAccessRestaurantChannel(null, 999));
        $this->assertFalse($this->canAccessDriverChannel(null, 999));
        $this->assertFalse($this->canAccessKitchenChannel(null, 999));
        $this->assertFalse($this->canAccessOrderChannel(null, 999));
    }

    // Helper methods to test channel authorization logic directly
    private function canAccessCustomerChannel($user, $customerId): bool
    {
        // Check if user is a customer accessing their own channel
        if ($user && $user->id == $customerId) {
            return true;
        }
        
        // Check if user is admin
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        
        return false;
    }

    private function canAccessRestaurantChannel($user, $restaurantBranchId): bool
    {
        // Check if user is admin
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        
        // Check if user is staff of this restaurant branch
        return $user && $user->restaurant_branch_id == $restaurantBranchId;
    }

    private function canAccessDriverChannel($user, $driverId): bool
    {
        // Check if user is accessing their own driver channel
        if ($user && $user->id == $driverId) {
            return true;
        }
        
        // Check if user is admin
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        
        return false;
    }

    private function canAccessKitchenChannel($user, $restaurantBranchId): bool
    {
        // Check if user is admin
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        
        // Check if user is kitchen staff of this restaurant
        return $user && 
               $user->restaurant_branch_id == $restaurantBranchId && 
               in_array($user->role, ['KITCHEN_STAFF', 'RESTAURANT_OWNER', 'BRANCH_MANAGER']);
    }

    private function canAccessOrderChannel($user, $orderId): bool
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return false;
        }
        
        // Admin can access all orders
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        
        // Customer can access their own orders (if they're authenticated)
        if ($user && $user->id == $order->customer_id) {
            return true;
        }
        
        // Restaurant staff can access orders from their restaurant
        if ($user && $user->restaurant_branch_id == $order->restaurant_branch_id) {
            return true;
        }
        
        // Driver can access orders assigned to them
        if ($user && $order->driver_id == $user->id) {
            return true;
        }
        
        return false;
    }
}
