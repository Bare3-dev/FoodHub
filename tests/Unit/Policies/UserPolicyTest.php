<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private User $kitchenStaff;
    private User $unauthorizedUser;
    private User $staffMember;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new UserPolicy();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->restaurantOwner = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $this->branchManager = User::factory()->create(['role' => 'BRANCH_MANAGER']);
        $this->cashier = User::factory()->create(['role' => 'CASHIER']);
        $this->kitchenStaff = User::factory()->create(['role' => 'KITCHEN_STAFF']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'CUSTOMER_SERVICE']);
        
        // Create test restaurant and branch
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        // Create test staff member
        $this->staffMember = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
        
        // Update restaurant owner to have this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $this->restaurant->id]);
        
        // Update branch manager to have this branch
        $this->branchManager->update([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);
    }

    #[Test]
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should be able to view any staff
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->staffMember));
        
        // Super admin should be able to create staff
        $this->assertTrue($this->policy->create($this->superAdmin));
        
        // Super admin should be able to update any staff
        $this->assertTrue($this->policy->update($this->superAdmin, $this->staffMember));
        
        // Super admin should be able to delete any staff
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->staffMember));
        
        // Super admin should be able to restore deleted staff
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->staffMember));
        
        // Super admin should be able to force delete staff
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->staffMember));
    }

    #[Test]
    public function restaurant_owner_can_manage_own_restaurant_staff()
    {
        // Restaurant owner should be able to view staff in their restaurant
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->staffMember));
        
        // Restaurant owner should be able to create staff for their restaurant
        $this->assertTrue($this->policy->create($this->restaurantOwner));
        
        // Restaurant owner should be able to update staff in their restaurant
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->staffMember));
        
        // Restaurant owner should be able to delete staff in their restaurant
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $this->staffMember));
        
        // Restaurant owner should NOT be able to restore staff (only super admin can)
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->staffMember));
        
        // Restaurant owner should NOT be able to force delete staff (only super admin can)
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->staffMember));
    }

    #[Test]
    public function restaurant_owner_cannot_manage_other_restaurant_staff()
    {
        // Create another restaurant and staff
        $otherRestaurant = Restaurant::factory()->create();
        $otherStaff = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $otherRestaurant->id
        ]);
        
        // Restaurant owner should NOT be able to view other restaurant's staff
        $this->assertFalse($this->policy->view($this->restaurantOwner, $otherStaff));
        
        // Restaurant owner should NOT be able to update other restaurant's staff
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherStaff));
        
        // Restaurant owner should NOT be able to delete other restaurant's staff
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherStaff));
    }

    #[Test]
    public function branch_manager_can_manage_own_branch_staff()
    {
        // Branch manager should be able to view staff in their branch
        $this->assertTrue($this->policy->viewAny($this->branchManager));
        $this->assertTrue($this->policy->view($this->branchManager, $this->staffMember));
        
        // Branch manager should be able to create staff for their branch
        $this->assertTrue($this->policy->create($this->branchManager));
        
        // Branch manager should be able to update staff in their branch
        $this->assertTrue($this->policy->update($this->branchManager, $this->staffMember));
        
        // Branch manager should be able to delete staff in their branch
        $this->assertTrue($this->policy->delete($this->branchManager, $this->staffMember));
        
        // Branch manager should NOT be able to restore staff (only super admin can)
        $this->assertFalse($this->policy->restore($this->branchManager, $this->staffMember));
        
        // Branch manager should NOT be able to force delete staff (only super admin can)
        $this->assertFalse($this->policy->forceDelete($this->branchManager, $this->staffMember));
    }

    #[Test]
    public function branch_manager_cannot_manage_other_branch_staff()
    {
        // Create another branch and staff
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        $otherStaff = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $otherBranch->id
        ]);
        
        // Branch manager should NOT be able to view other branch's staff
        $this->assertFalse($this->policy->view($this->branchManager, $otherStaff));
        
        // Branch manager should NOT be able to update other branch's staff
        $this->assertFalse($this->policy->update($this->branchManager, $otherStaff));
        
        // Branch manager should NOT be able to delete other branch's staff
        $this->assertFalse($this->policy->delete($this->branchManager, $otherStaff));
    }

    #[Test]
    public function cashier_cannot_manage_staff()
    {
        // Cashier should NOT be able to view staff list
        $this->assertFalse($this->policy->viewAny($this->cashier));
        
        // Cashier should NOT be able to view individual staff
        $this->assertFalse($this->policy->view($this->cashier, $this->staffMember));
        
        // Cashier should NOT be able to create staff
        $this->assertFalse($this->policy->create($this->cashier));
        
        // Cashier should NOT be able to update staff
        $this->assertFalse($this->policy->update($this->cashier, $this->staffMember));
        
        // Cashier should NOT be able to delete staff
        $this->assertFalse($this->policy->delete($this->cashier, $this->staffMember));
        
        // Cashier should NOT be able to restore staff
        $this->assertFalse($this->policy->restore($this->cashier, $this->staffMember));
        
        // Cashier should NOT be able to force delete staff
        $this->assertFalse($this->policy->forceDelete($this->cashier, $this->staffMember));
    }

    #[Test]
    public function kitchen_staff_cannot_manage_staff()
    {
        // Kitchen staff should NOT be able to view staff list
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        
        // Kitchen staff should NOT be able to view individual staff
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->staffMember));
        
        // Kitchen staff should NOT be able to create staff
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        
        // Kitchen staff should NOT be able to update staff
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->staffMember));
        
        // Kitchen staff should NOT be able to delete staff
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->staffMember));
        
        // Kitchen staff should NOT be able to restore staff
        $this->assertFalse($this->policy->restore($this->kitchenStaff, $this->staffMember));
        
        // Kitchen staff should NOT be able to force delete staff
        $this->assertFalse($this->policy->forceDelete($this->kitchenStaff, $this->staffMember));
    }

    #[Test]
    public function unauthorized_users_cannot_access_protected_resources()
    {
        // Unauthorized users should NOT be able to view staff list
        $this->assertFalse($this->policy->viewAny($this->unauthorizedUser));
        
        // Unauthorized users should NOT be able to view individual staff
        $this->assertFalse($this->policy->view($this->unauthorizedUser, $this->staffMember));
        
        // Unauthorized users should NOT be able to create staff
        $this->assertFalse($this->policy->create($this->unauthorizedUser));
        
        // Unauthorized users should NOT be able to update staff
        $this->assertFalse($this->policy->update($this->unauthorizedUser, $this->staffMember));
        
        // Unauthorized users should NOT be able to delete staff
        $this->assertFalse($this->policy->delete($this->unauthorizedUser, $this->staffMember));
        
        // Unauthorized users should NOT be able to restore staff
        $this->assertFalse($this->policy->restore($this->unauthorizedUser, $this->staffMember));
        
        // Unauthorized users should NOT be able to force delete staff
        $this->assertFalse($this->policy->forceDelete($this->unauthorizedUser, $this->staffMember));
    }

    #[Test]
    public function it_handles_null_user_gracefully()
    {
        // Policy should handle null user gracefully
        $this->assertFalse($this->policy->viewAny(null));
        $this->assertFalse($this->policy->view(null, $this->staffMember));
        $this->assertFalse($this->policy->create(null));
        $this->assertFalse($this->policy->update(null, $this->staffMember));
        $this->assertFalse($this->policy->delete(null, $this->staffMember));
        $this->assertFalse($this->policy->restore(null, $this->staffMember));
        $this->assertFalse($this->policy->forceDelete(null, $this->staffMember));
    }

    #[Test]
    public function it_handles_deleted_staff()
    {
        // Delete the staff member
        $this->staffMember->delete();
        
        // Super admin should still be able to restore deleted staff
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->staffMember));
        
        // Restaurant owner should NOT be able to restore deleted staff
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->staffMember));
        
        // Super admin should be able to force delete staff
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->staffMember));
        
        // Restaurant owner should NOT be able to force delete staff
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->staffMember));
    }

    #[Test]
    public function it_enforces_role_hierarchy()
    {
        // Test that higher roles can manage lower roles but not vice versa
        
        // Super admin should be able to manage any role
        $this->assertTrue($this->policy->create($this->superAdmin));
        
        // Restaurant owner should be able to create lower roles
        $this->assertTrue($this->policy->create($this->restaurantOwner));
        
        // Branch manager should be able to create lower roles
        $this->assertTrue($this->policy->create($this->branchManager));
        
        // Cashier should NOT be able to create any roles
        $this->assertFalse($this->policy->create($this->cashier));
        
        // Kitchen staff should NOT be able to create any roles
        $this->assertFalse($this->policy->create($this->kitchenStaff));
    }

    #[Test]
    public function it_validates_cross_restaurant_permissions()
    {
        // Create staff from different restaurant
        $otherRestaurant = Restaurant::factory()->create();
        $otherStaff = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $otherRestaurant->id
        ]);
        
        // Restaurant owner should NOT be able to manage staff from other restaurant
        $this->assertFalse($this->policy->view($this->restaurantOwner, $otherStaff));
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherStaff));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherStaff));
        
        // Super admin should be able to manage staff from any restaurant
        $this->assertTrue($this->policy->view($this->superAdmin, $otherStaff));
        $this->assertTrue($this->policy->update($this->superAdmin, $otherStaff));
        $this->assertTrue($this->policy->delete($this->superAdmin, $otherStaff));
    }

    #[Test]
    public function it_validates_branch_specific_permissions()
    {
        // Create staff from different branch
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        $otherStaff = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $otherBranch->id
        ]);
        
        // Branch manager should NOT be able to manage staff from other branch
        $this->assertFalse($this->policy->view($this->branchManager, $otherStaff));
        $this->assertFalse($this->policy->update($this->branchManager, $otherStaff));
        $this->assertFalse($this->policy->delete($this->branchManager, $otherStaff));
        
        // Restaurant owner should be able to manage staff from any branch in their restaurant
        $this->assertTrue($this->policy->view($this->restaurantOwner, $otherStaff));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $otherStaff));
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $otherStaff));
    }
} 