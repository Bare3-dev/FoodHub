<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Driver;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\DriverWorkingZone;
use App\Policies\DriverPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class DriverPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
    protected User $kitchenStaff;
    protected Driver $driver;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected DriverWorkingZone $workingZone;
    protected DriverPolicy $policy;

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
        
        // Create working zone
        $this->workingZone = DriverWorkingZone::factory()->create([
            'zone_name' => 'Downtown Zone',
            'zone_description' => 'Downtown delivery area'
        ]);
        
        // Create test driver
        $this->driver = Driver::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Driver',
            'email' => 'john.driver@example.com',
            'phone' => '+1234567890',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_number' => 'ABC123',
            'status' => 'active',
            'is_available' => true
        ]);
        
        $this->policy = new DriverPolicy();
    }

    #[Test]
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should have full access to all drivers
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->driver));
        $this->assertTrue($this->policy->create($this->superAdmin));
        $this->assertTrue($this->policy->update($this->superAdmin, $this->driver));
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->driver));
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->driver));
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->driver));
    }

    #[Test]
    public function restaurant_owner_can_view_and_update_drivers()
    {
        // Restaurant owner should be able to view and update drivers
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->driver));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->driver));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->restaurantOwner));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $this->driver));
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->driver));
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->driver));
    }

    #[Test]
    public function branch_manager_can_manage_drivers()
    {
        // Branch manager should have full access to drivers
        $this->assertTrue($this->policy->viewAny($this->branchManager));
        $this->assertTrue($this->policy->view($this->branchManager, $this->driver));
        $this->assertTrue($this->policy->create($this->branchManager));
        $this->assertTrue($this->policy->update($this->branchManager, $this->driver));
        $this->assertTrue($this->policy->delete($this->branchManager, $this->driver));
        $this->assertTrue($this->policy->restore($this->branchManager, $this->driver));
        $this->assertTrue($this->policy->forceDelete($this->branchManager, $this->driver));
    }

    #[Test]
    public function cashier_can_view_drivers()
    {
        // Cashier should only be able to view drivers
        $this->assertTrue($this->policy->viewAny($this->cashier));
        $this->assertTrue($this->policy->view($this->cashier, $this->driver));
        
        // But should not be able to modify them
        $this->assertFalse($this->policy->create($this->cashier));
        $this->assertFalse($this->policy->update($this->cashier, $this->driver));
        $this->assertFalse($this->policy->delete($this->cashier, $this->driver));
        $this->assertFalse($this->policy->restore($this->cashier, $this->driver));
        $this->assertFalse($this->policy->forceDelete($this->cashier, $this->driver));
    }

    #[Test]
    public function customer_service_can_view_drivers()
    {
        // Customer service should only be able to view drivers
        $this->assertTrue($this->policy->viewAny($this->customerService));
        $this->assertTrue($this->policy->view($this->customerService, $this->driver));
        
        // But should not be able to modify them
        $this->assertFalse($this->policy->create($this->customerService));
        $this->assertFalse($this->policy->update($this->customerService, $this->driver));
        $this->assertFalse($this->policy->delete($this->customerService, $this->driver));
        $this->assertFalse($this->policy->restore($this->customerService, $this->driver));
        $this->assertFalse($this->policy->forceDelete($this->customerService, $this->driver));
    }

    #[Test]
    public function kitchen_staff_cannot_manage_drivers()
    {
        // Kitchen staff should not have access to driver management
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->driver));
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->driver));
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->driver));
        $this->assertFalse($this->policy->restore($this->kitchenStaff, $this->driver));
        $this->assertFalse($this->policy->forceDelete($this->kitchenStaff, $this->driver));
    }

    #[Test]
    public function inactive_users_cannot_access_drivers()
    {
        $inactiveUser = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'status' => 'inactive'
        ]);
        
        $this->assertFalse($this->policy->viewAny($inactiveUser));
        $this->assertFalse($this->policy->view($inactiveUser, $this->driver));
        $this->assertFalse($this->policy->create($inactiveUser));
        $this->assertFalse($this->policy->update($inactiveUser, $this->driver));
        $this->assertFalse($this->policy->delete($inactiveUser, $this->driver));
    }

    #[Test]
    public function unauthorized_users_cannot_access_drivers()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);
        
        $this->assertFalse($this->policy->viewAny($unauthorizedUser));
        $this->assertFalse($this->policy->view($unauthorizedUser, $this->driver));
        $this->assertFalse($this->policy->create($unauthorizedUser));
        $this->assertFalse($this->policy->update($unauthorizedUser, $this->driver));
        $this->assertFalse($this->policy->delete($unauthorizedUser, $this->driver));
    }

    #[Test]
    public function it_handles_null_user_gracefully()
    {
        $this->assertFalse($this->policy->viewAny(null));
        $this->assertFalse($this->policy->view(null, $this->driver));
        $this->assertFalse($this->policy->create(null));
        $this->assertFalse($this->policy->update(null, $this->driver));
        $this->assertFalse($this->policy->delete(null, $this->driver));
    }

    #[Test]
    public function it_handles_null_driver_gracefully()
    {
        $this->assertFalse($this->policy->view($this->branchManager, null));
        $this->assertFalse($this->policy->update($this->branchManager, null));
        $this->assertFalse($this->policy->delete($this->branchManager, null));
        $this->assertFalse($this->policy->restore($this->branchManager, null));
        $this->assertFalse($this->policy->forceDelete($this->branchManager, null));
    }

    #[Test]
    public function it_handles_driver_with_inactive_status()
    {
        $inactiveDriver = Driver::factory()->create([
            'status' => 'inactive'
        ]);
        
        // Branch manager should still be able to manage inactive drivers
        $this->assertTrue($this->policy->view($this->branchManager, $inactiveDriver));
        $this->assertTrue($this->policy->update($this->branchManager, $inactiveDriver));
        $this->assertTrue($this->policy->delete($this->branchManager, $inactiveDriver));
    }

    #[Test]
    public function it_handles_driver_with_unavailable_status()
    {
        $unavailableDriver = Driver::factory()->create([
            'is_available' => false
        ]);
        
        // Branch manager should still be able to manage unavailable drivers
        $this->assertTrue($this->policy->view($this->branchManager, $unavailableDriver));
        $this->assertTrue($this->policy->update($this->branchManager, $unavailableDriver));
        $this->assertTrue($this->policy->delete($this->branchManager, $unavailableDriver));
    }

    #[Test]
    public function it_handles_driver_with_working_zone()
    {
        // Create driver without working zone (since it's not in the migration)
        $driverWithZone = Driver::factory()->create();
        
        // Branch manager should be able to manage drivers
        $this->assertTrue($this->policy->view($this->branchManager, $driverWithZone));
        $this->assertTrue($this->policy->update($this->branchManager, $driverWithZone));
        $this->assertTrue($this->policy->delete($this->branchManager, $driverWithZone));
    }
} 