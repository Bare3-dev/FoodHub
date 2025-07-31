<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'CUSTOMER_SERVICE',
        ]);

        $this->assertAuthenticated();
        $response->assertStatus(201);
        $response->assertJson(['message' => 'User registered successfully']);
    }

    public function test_unauthorized_users_cannot_create_super_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Super Admin',
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'SUPER_ADMIN',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Only super administrators can create super admin accounts.',
        ]);
    }

    public function test_super_admin_can_create_other_super_admin(): void
    {
        // Create a super admin first
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin)
            ->post('/register/super-admin', [
                'name' => 'New Super Admin',
                'email' => 'newsuperadmin@example.com',
                'password' => 'password',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Super admin created successfully.',
        ]);
    }

    public function test_restaurant_owner_can_create_staff(): void
    {
        // Create a restaurant and restaurant owner
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'Test',
            'phone' => '1234567890',
            'email' => 'test@restaurant.com',
            'business_hours' => ['monday' => ['open' => '09:00', 'close' => '22:00']],
            'status' => 'active',
        ]);

        $restaurantOwner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($restaurantOwner)
            ->post('/register/staff', [
                'name' => 'New Staff',
                'email' => 'staff@example.com',
                'password' => 'password',
                'role' => 'CASHIER',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Staff member created successfully.',
        ]);
    }

    public function test_restaurant_owner_cannot_create_super_admin(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'Test',
            'phone' => '1234567890',
            'email' => 'test@restaurant.com',
            'business_hours' => ['monday' => ['open' => '09:00', 'close' => '22:00']],
            'status' => 'active',
        ]);

        $restaurantOwner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($restaurantOwner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/register/staff', [
                'name' => 'New Super Admin',
                'email' => 'superadmin@example.com',
                'password' => 'password',
                'role' => 'SUPER_ADMIN',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_branch_manager_can_create_staff(): void
    {
        // Create restaurant, branch, and branch manager
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'Test',
            'phone' => '1234567890',
            'email' => 'test@restaurant.com',
            'business_hours' => ['monday' => ['open' => '09:00', 'close' => '22:00']],
            'status' => 'active',
        ]);

        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'address' => 'Test Address',
            'city' => 'Riyadh',
            'state' => 'Riyadh Province',
            'postal_code' => '12345',
            'phone' => '1234567890',
            'operating_hours' => ['monday' => ['open' => '09:00', 'close' => '22:00']],
            'delivery_zones' => [],
            'delivery_fee' => 5.00,
            'minimum_order_amount' => 10.00,
            'estimated_delivery_time' => 30,
        ]);

        $branchManager = User::create([
            'name' => 'Branch Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('password'),
            'role' => 'BRANCH_MANAGER',
            'restaurant_id' => $restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($branchManager)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/register/staff', [
                'name' => 'New Cashier',
                'email' => 'cashier@example.com',
                'password' => 'password',
                'role' => 'CASHIER',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Staff member created successfully.',
        ]);
    }

    public function test_branch_manager_cannot_create_branch_manager(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'Test',
            'phone' => '1234567890',
            'email' => 'test@restaurant.com',
            'business_hours' => ['monday' => ['open' => '09:00', 'close' => '22:00']],
            'status' => 'active',
        ]);

        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'address' => 'Test Address',
            'city' => 'Riyadh',
            'state' => 'Riyadh Province',
            'postal_code' => '12345',
            'phone' => '1234567890',
            'operating_hours' => ['monday' => ['open' => '09:00', 'close' => '22:00']],
            'delivery_zones' => [],
            'delivery_fee' => 5.00,
            'minimum_order_amount' => 10.00,
            'estimated_delivery_time' => 30,
        ]);

        $branchManager = User::create([
            'name' => 'Branch Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('password'),
            'role' => 'BRANCH_MANAGER',
            'restaurant_id' => $restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($branchManager)
            ->post('/register/staff', [
                'name' => 'New Branch Manager',
                'email' => 'newmanager@example.com',
                'password' => 'password',
                'role' => 'BRANCH_MANAGER',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You do not have permission to create users with this role.',
        ]);
    }
}
