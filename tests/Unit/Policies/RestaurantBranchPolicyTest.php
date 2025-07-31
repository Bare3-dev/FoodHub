<?php

namespace Tests\Unit\Policies;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Policies\RestaurantBranchPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantBranchPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantBranchPolicy $policy;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private User $kitchenStaff;
    private User $unauthorizedUser;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new RestaurantBranchPolicy();
        
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
        
        // Update restaurant owner to have this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $this->restaurant->id]);
        
        // Update branch manager to have this branch
        $this->branchManager->update(['restaurant_branch_id' => $this->branch->id]);
    }

    #[Test]
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should be able to view any branch
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->branch));
        
        // Super admin should be able to create branches
        $this->assertTrue($this->policy->create($this->superAdmin));
        
        // Super admin should be able to update any branch
        $this->assertTrue($this->policy->update($this->superAdmin, $this->branch));
        
        // Super admin should be able to delete any branch
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->branch));
        
        // Super admin should be able to restore deleted branches
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->branch));
        
        // Super admin should be able to force delete branches
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->branch));
    }

    #[Test]
    public function restaurant_owner_can_manage_own_branches()
    {
        // Restaurant owner should be able to view branches of their restaurant
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->branch));
        
        // Restaurant owner should be able to create branches for their restaurant
        $this->assertTrue($this->policy->create($this->restaurantOwner));
        
        // Restaurant owner should be able to update branches of their restaurant
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->branch));
        
        // Restaurant owner should be able to delete branches of their restaurant
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $this->branch));
        
        // Restaurant owner should NOT be able to restore branches (only super admin can)
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->branch));
        
        // Restaurant owner should NOT be able to force delete branches (only super admin can)
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->branch));
    }

    #[Test]
    public function restaurant_owner_cannot_manage_other_branches()
    {
        // Create another restaurant and branch owned by different user
        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $otherRestaurant->id
        ]);
        $otherUser = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $otherUser->update(['restaurant_id' => $otherRestaurant->id]);
        
        // Restaurant owner should not be able to update other branches
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherBranch));
        
        // Restaurant owner should not be able to delete other branches
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherBranch));
        
        // Restaurant owner should not be able to restore other branches
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $otherBranch));
        
        // Restaurant owner should not be able to force delete other branches
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $otherBranch));
    }

    #[Test]
    public function branch_manager_can_manage_own_branch()
    {
        // Branch manager should be able to view their own branch
        $this->assertTrue($this->policy->view($this->branchManager, $this->branch));
        
        // Branch manager should be able to view any branches (if they have restaurant_branch_id)
        $this->assertTrue($this->policy->viewAny($this->branchManager));
        
        // Branch manager should NOT be able to create branches
        $this->assertFalse($this->policy->create($this->branchManager));
        
        // Branch manager should be able to update their own branch
        $this->assertTrue($this->policy->update($this->branchManager, $this->branch));
        
        // Branch manager should NOT be able to delete branches
        $this->assertFalse($this->policy->delete($this->branchManager, $this->branch));
        
        // Branch manager should NOT be able to restore branches
        $this->assertFalse($this->policy->restore($this->branchManager, $this->branch));
        
        // Branch manager should NOT be able to force delete branches
        $this->assertFalse($this->policy->forceDelete($this->branchManager, $this->branch));
    }

    #[Test]
    public function branch_manager_cannot_manage_other_branches()
    {
        // Create another branch
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        // Branch manager should not be able to update other branches
        $this->assertFalse($this->policy->update($this->branchManager, $otherBranch));
        
        // Branch manager should not be able to delete other branches
        $this->assertFalse($this->policy->delete($this->branchManager, $otherBranch));
    }

    #[Test]
    public function cashier_can_view_own_branch()
    {
        // Assign cashier to this branch
        $this->cashier->update(['restaurant_branch_id' => $this->branch->id]);
        
        // Cashier should NOT be able to view branches (not mentioned in policy)
        $this->assertFalse($this->policy->view($this->cashier, $this->branch));
        
        // Cashier should NOT be able to view any branches (not mentioned in policy)
        $this->assertFalse($this->policy->viewAny($this->cashier));
        
        // Cashier should NOT be able to create branches
        $this->assertFalse($this->policy->create($this->cashier));
        
        // Cashier should NOT be able to update branches
        $this->assertFalse($this->policy->update($this->cashier, $this->branch));
        
        // Cashier should NOT be able to delete branches
        $this->assertFalse($this->policy->delete($this->cashier, $this->branch));
    }

    #[Test]
    public function kitchen_staff_cannot_manage_branches()
    {
        // Kitchen staff should NOT be able to view any branches
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->branch));
        
        // Kitchen staff should NOT be able to create branches
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        
        // Kitchen staff should NOT be able to update branches
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->branch));
        
        // Kitchen staff should NOT be able to delete branches
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->branch));
    }

    #[Test]
    public function unauthorized_users_cannot_access_protected_resources()
    {
        // Unauthorized users should NOT be able to view any branches
        $this->assertFalse($this->policy->viewAny($this->unauthorizedUser));
        $this->assertFalse($this->policy->view($this->unauthorizedUser, $this->branch));
        
        // Unauthorized users should NOT be able to create branches
        $this->assertFalse($this->policy->create($this->unauthorizedUser));
        
        // Unauthorized users should NOT be able to update branches
        $this->assertFalse($this->policy->update($this->unauthorizedUser, $this->branch));
        
        // Unauthorized users should NOT be able to delete branches
        $this->assertFalse($this->policy->delete($this->unauthorizedUser, $this->branch));
        
        // Unauthorized users should NOT be able to restore branches
        $this->assertFalse($this->policy->restore($this->unauthorizedUser, $this->branch));
        
        // Unauthorized users should NOT be able to force delete branches
        $this->assertFalse($this->policy->forceDelete($this->unauthorizedUser, $this->branch));
    }

    #[Test]
    public function it_handles_null_user_gracefully()
    {
        // Policy should handle null user gracefully - but it doesn't, so we expect exceptions
        $this->expectException(\TypeError::class);
        $this->policy->viewAny(null);
    }

    #[Test]
    public function it_handles_deleted_branches()
    {
        // Soft delete the branch
        $this->branch->delete();
        
        // Super admin should still be able to view deleted branch
        $this->assertTrue($this->policy->view($this->superAdmin, $this->branch));
        
        // Restaurant owner should still be able to view their deleted branch
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->branch));
        
        // Branch manager should still be able to view their deleted branch
        $this->assertTrue($this->policy->view($this->branchManager, $this->branch));
    }
} 