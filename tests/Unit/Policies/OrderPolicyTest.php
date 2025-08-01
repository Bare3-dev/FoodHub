<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Driver;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
    protected User $kitchenStaff;
    protected Order $order;
    protected Customer $customer;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Driver $driver;
    protected OrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with ACTIVE status
        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active'
        ]);
        
        $this->restaurantOwner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'status' => 'active'
        ]);
        
        $this->branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'status' => 'active'
        ]);
        
        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);
        
        $this->customerService = User::factory()->create([
            'role' => 'CUSTOMER_SERVICE',
            'status' => 'active'
        ]);
        
        $this->kitchenStaff = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);
        
        // Create test restaurant and branch
        $this->restaurant = Restaurant::factory()->create();
        
        // Associate restaurant owner with this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $this->restaurant->id]);
        
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        // Associate all staff users with the restaurant and branch
        $this->branchManager->update([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        $this->cashier->update([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        $this->customerService->update([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        $this->kitchenStaff->update([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        // Create test customer
        $this->customer = Customer::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);
        
        // Create test driver
        $this->driver = Driver::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Driver',
            'status' => 'active',
            'is_available' => true
        ]);
        
        // Create test order
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'driver_id' => $this->driver->id,
            'order_number' => 'ORD-001',
            'status' => 'pending',
            'total_amount' => 25.00,
            'delivery_fee' => 5.00,
            'tax_amount' => 2.50
        ]);
        
        $this->policy = new OrderPolicy();
    }

    /** @test */
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should have full access to all orders
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->order));
        $this->assertTrue($this->policy->create($this->superAdmin));
        $this->assertTrue($this->policy->update($this->superAdmin, $this->order));
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->order));
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->order));
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->order));
    }

    /** @test */
    public function restaurant_owner_can_manage_own_restaurant_orders()
    {
        // Restaurant owner should have full access to their restaurant's orders
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->order));
        $this->assertTrue($this->policy->create($this->restaurantOwner));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->order));
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $this->order));
        $this->assertTrue($this->policy->restore($this->restaurantOwner, $this->order));
        $this->assertTrue($this->policy->forceDelete($this->restaurantOwner, $this->order));
    }

    /** @test */
    public function restaurant_owner_cannot_manage_other_restaurant_orders()
    {
        // Create another restaurant and order
        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $otherRestaurant->id
        ]);
        $otherOrder = Order::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'restaurant_branch_id' => $otherBranch->id
        ]);
        
        // Restaurant owner should not have access to other restaurant's orders
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherOrder));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherOrder));
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $otherOrder));
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $otherOrder));
    }

    /** @test */
    public function branch_manager_can_manage_branch_orders()
    {
        // Branch manager should have full access to their branch's orders
        $this->assertTrue($this->policy->viewAny($this->branchManager));
        $this->assertTrue($this->policy->view($this->branchManager, $this->order));
        $this->assertTrue($this->policy->create($this->branchManager));
        $this->assertTrue($this->policy->update($this->branchManager, $this->order));
        $this->assertTrue($this->policy->delete($this->branchManager, $this->order));
        $this->assertTrue($this->policy->restore($this->branchManager, $this->order));
        $this->assertTrue($this->policy->forceDelete($this->branchManager, $this->order));
    }

    /** @test */
    public function cashier_can_view_and_update_orders()
    {
        // Cashier should be able to view and update orders
        $this->assertTrue($this->policy->viewAny($this->cashier));
        $this->assertTrue($this->policy->view($this->cashier, $this->order));
        $this->assertTrue($this->policy->update($this->cashier, $this->order));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->cashier));
        $this->assertFalse($this->policy->delete($this->cashier, $this->order));
        $this->assertFalse($this->policy->restore($this->cashier, $this->order));
        $this->assertFalse($this->policy->forceDelete($this->cashier, $this->order));
    }

    /** @test */
    public function customer_service_can_manage_orders()
    {
        // Customer service should have full access to orders
        $this->assertTrue($this->policy->viewAny($this->customerService));
        $this->assertTrue($this->policy->view($this->customerService, $this->order));
        $this->assertTrue($this->policy->create($this->customerService));
        $this->assertTrue($this->policy->update($this->customerService, $this->order));
        $this->assertTrue($this->policy->delete($this->customerService, $this->order));
        $this->assertTrue($this->policy->restore($this->customerService, $this->order));
        $this->assertTrue($this->policy->forceDelete($this->customerService, $this->order));
    }

    /** @test */
    public function kitchen_staff_can_view_and_update_orders()
    {
        // Kitchen staff should be able to view and update orders
        $this->assertTrue($this->policy->viewAny($this->kitchenStaff));
        $this->assertTrue($this->policy->view($this->kitchenStaff, $this->order));
        $this->assertTrue($this->policy->update($this->kitchenStaff, $this->order));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->order));
        $this->assertFalse($this->policy->restore($this->kitchenStaff, $this->order));
        $this->assertFalse($this->policy->forceDelete($this->kitchenStaff, $this->order));
    }

    /** @test */
    public function inactive_users_cannot_access_orders()
    {
        $inactiveUser = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'inactive'
        ]);
        
        $this->assertFalse($this->policy->viewAny($inactiveUser));
        $this->assertFalse($this->policy->view($inactiveUser, $this->order));
        $this->assertFalse($this->policy->create($inactiveUser));
        $this->assertFalse($this->policy->update($inactiveUser, $this->order));
        $this->assertFalse($this->policy->delete($inactiveUser, $this->order));
    }

    /** @test */
    public function unauthorized_users_cannot_access_orders()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'DRIVER', // Using DRIVER role which doesn't have order management permissions
            'status' => 'active'
        ]);
        
        $this->assertFalse($this->policy->viewAny($unauthorizedUser));
        $this->assertFalse($this->policy->view($unauthorizedUser, $this->order));
        $this->assertFalse($this->policy->create($unauthorizedUser));
        $this->assertFalse($this->policy->update($unauthorizedUser, $this->order));
        $this->assertFalse($this->policy->delete($unauthorizedUser, $this->order));
    }

    /** @test */
    public function it_handles_null_user_gracefully()
    {
        $this->assertFalse($this->policy->viewAny(null));
        $this->assertFalse($this->policy->view(null, $this->order));
        $this->assertFalse($this->policy->create(null));
        $this->assertFalse($this->policy->update(null, $this->order));
        $this->assertFalse($this->policy->delete(null, $this->order));
    }

    /** @test */
    public function it_handles_null_order_gracefully()
    {
        $this->assertFalse($this->policy->view($this->restaurantOwner, null));
        $this->assertFalse($this->policy->update($this->restaurantOwner, null));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, null));
        $this->assertFalse($this->policy->restore($this->restaurantOwner, null));
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, null));
    }

    /** @test */
    public function it_handles_order_with_different_statuses()
    {
        $pendingOrder = Order::factory()->create([
            'status' => 'pending',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        $completedOrder = Order::factory()->create([
            'status' => 'completed',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        $cancelledOrder = Order::factory()->create([
            'status' => 'cancelled',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        // Restaurant owner should be able to manage orders with all statuses
        $this->assertTrue($this->policy->view($this->restaurantOwner, $pendingOrder));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $pendingOrder));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $completedOrder));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $completedOrder));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $cancelledOrder));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $cancelledOrder));
    }

    /** @test */
    public function it_handles_order_with_assigned_driver()
    {
        $orderWithDriver = Order::factory()->create([
            'driver_id' => $this->driver->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        // Restaurant owner should be able to manage orders with assigned drivers
        $this->assertTrue($this->policy->view($this->restaurantOwner, $orderWithDriver));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $orderWithDriver));
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $orderWithDriver));
    }

    /** @test */
    public function it_handles_order_without_driver()
    {
        $orderWithoutDriver = Order::factory()->create([
            'driver_id' => null,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        // Restaurant owner should be able to manage orders without drivers
        $this->assertTrue($this->policy->view($this->restaurantOwner, $orderWithoutDriver));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $orderWithoutDriver));
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $orderWithoutDriver));
    }
} 