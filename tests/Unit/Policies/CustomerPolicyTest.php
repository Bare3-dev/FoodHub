<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Policies\CustomerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CustomerPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
    protected User $kitchenStaff;
    protected Customer $customer;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected CustomerPolicy $policy;

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
        
        // Create test customer
        $this->customer = Customer::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'status' => 'active'
        ]);
        
        $this->policy = new CustomerPolicy();
    }

    #[Test]
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should have full access to all customers
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->customer));
        $this->assertTrue($this->policy->create($this->superAdmin));
        $this->assertTrue($this->policy->update($this->superAdmin, $this->customer));
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->customer));
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->customer));
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->customer));
    }

    #[Test]
    public function restaurant_owner_can_view_and_update_customers()
    {
        // Restaurant owner should be able to view and update customers
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->customer));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->customer));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->restaurantOwner));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $this->customer));
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->customer));
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->customer));
    }

    #[Test]
    public function branch_manager_can_view_and_update_customers()
    {
        // Branch manager should be able to view and update customers
        $this->assertTrue($this->policy->viewAny($this->branchManager));
        $this->assertTrue($this->policy->view($this->branchManager, $this->customer));
        $this->assertTrue($this->policy->update($this->branchManager, $this->customer));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->branchManager));
        $this->assertFalse($this->policy->delete($this->branchManager, $this->customer));
        $this->assertFalse($this->policy->restore($this->branchManager, $this->customer));
        $this->assertFalse($this->policy->forceDelete($this->branchManager, $this->customer));
    }

    #[Test]
    public function cashier_can_view_customers()
    {
        // Cashier should only be able to view customers
        $this->assertTrue($this->policy->viewAny($this->cashier));
        $this->assertTrue($this->policy->view($this->cashier, $this->customer));
        
        // But should not be able to modify them
        $this->assertFalse($this->policy->create($this->cashier));
        $this->assertFalse($this->policy->update($this->cashier, $this->customer));
        $this->assertFalse($this->policy->delete($this->cashier, $this->customer));
        $this->assertFalse($this->policy->restore($this->cashier, $this->customer));
        $this->assertFalse($this->policy->forceDelete($this->cashier, $this->customer));
    }

    #[Test]
    public function customer_service_can_manage_customers()
    {
        // Customer service should have full access to customers
        $this->assertTrue($this->policy->viewAny($this->customerService));
        $this->assertTrue($this->policy->view($this->customerService, $this->customer));
        $this->assertTrue($this->policy->create($this->customerService));
        $this->assertTrue($this->policy->update($this->customerService, $this->customer));
        $this->assertTrue($this->policy->delete($this->customerService, $this->customer));
        $this->assertTrue($this->policy->restore($this->customerService, $this->customer));
        $this->assertTrue($this->policy->forceDelete($this->customerService, $this->customer));
    }

    #[Test]
    public function kitchen_staff_cannot_manage_customers()
    {
        // Kitchen staff should not have access to customer management
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->customer));
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->customer));
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->customer));
        $this->assertFalse($this->policy->restore($this->kitchenStaff, $this->customer));
        $this->assertFalse($this->policy->forceDelete($this->kitchenStaff, $this->customer));
    }

    #[Test]
    public function inactive_users_cannot_access_customers()
    {
        $inactiveUser = User::factory()->create([
            'role' => 'CUSTOMER_SERVICE',
            'status' => 'inactive'
        ]);
        
        $this->assertFalse($this->policy->viewAny($inactiveUser));
        $this->assertFalse($this->policy->view($inactiveUser, $this->customer));
        $this->assertFalse($this->policy->create($inactiveUser));
        $this->assertFalse($this->policy->update($inactiveUser, $this->customer));
        $this->assertFalse($this->policy->delete($inactiveUser, $this->customer));
    }

    #[Test]
    public function unauthorized_users_cannot_access_customers()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);
        
        $this->assertFalse($this->policy->viewAny($unauthorizedUser));
        $this->assertFalse($this->policy->view($unauthorizedUser, $this->customer));
        $this->assertFalse($this->policy->create($unauthorizedUser));
        $this->assertFalse($this->policy->update($unauthorizedUser, $this->customer));
        $this->assertFalse($this->policy->delete($unauthorizedUser, $this->customer));
    }

    #[Test]
    public function it_handles_null_user_gracefully()
    {
        $this->assertFalse($this->policy->viewAny(null));
        $this->assertFalse($this->policy->view(null, $this->customer));
        $this->assertFalse($this->policy->create(null));
        $this->assertFalse($this->policy->update(null, $this->customer));
        $this->assertFalse($this->policy->delete(null, $this->customer));
    }

    #[Test]
    public function it_handles_null_customer_gracefully()
    {
        $this->assertFalse($this->policy->view($this->customerService, null));
        $this->assertFalse($this->policy->update($this->customerService, null));
        $this->assertFalse($this->policy->delete($this->customerService, null));
        $this->assertFalse($this->policy->restore($this->customerService, null));
        $this->assertFalse($this->policy->forceDelete($this->customerService, null));
    }

    #[Test]
    public function it_handles_customer_with_deleted_restaurant()
    {
        // Create a customer associated with a restaurant
        $restaurant = Restaurant::factory()->create();
        
        // Associate restaurant owner with this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $restaurant->id]);
        
        $customer = Customer::factory()->create();
        
        // Delete the restaurant
        $restaurant->delete();
        
        // Customer service should still be able to view the customer
        $this->assertTrue($this->policy->view($this->customerService, $customer));
        $this->assertTrue($this->policy->update($this->customerService, $customer));
    }

    #[Test]
    public function it_handles_customer_with_inactive_status()
    {
        $inactiveCustomer = Customer::factory()->create([
            'status' => 'inactive'
        ]);
        
        // Customer service should still be able to manage inactive customers
        $this->assertTrue($this->policy->view($this->customerService, $inactiveCustomer));
        $this->assertTrue($this->policy->update($this->customerService, $inactiveCustomer));
        $this->assertTrue($this->policy->delete($this->customerService, $inactiveCustomer));
    }
} 