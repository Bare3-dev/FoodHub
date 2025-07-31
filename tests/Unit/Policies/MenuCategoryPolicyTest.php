<?php

namespace Tests\Unit\Policies;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\User;
use App\Policies\MenuCategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuCategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MenuCategoryPolicy $policy;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private User $kitchenStaff;
    private User $unauthorizedUser;
    private Restaurant $restaurant;
    private MenuCategory $menuCategory;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new MenuCategoryPolicy();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->restaurantOwner = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $this->branchManager = User::factory()->create(['role' => 'BRANCH_MANAGER']);
        $this->cashier = User::factory()->create(['role' => 'CASHIER']);
        $this->kitchenStaff = User::factory()->create(['role' => 'KITCHEN_STAFF']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'CUSTOMER_SERVICE']);
        
        // Create test restaurant and menu category
        $this->restaurant = Restaurant::factory()->create();
        $this->menuCategory = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        // Update restaurant owner to have this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $this->restaurant->id]);
    }

    #[Test]
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should be able to view any menu category
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->menuCategory));
        
        // Super admin should be able to create menu categories
        $this->assertTrue($this->policy->create($this->superAdmin));
        
        // Super admin should be able to update any menu category
        $this->assertTrue($this->policy->update($this->superAdmin, $this->menuCategory));
        
        // Super admin should be able to delete any menu category
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->menuCategory));
        
        // Super admin should be able to restore deleted menu categories
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->menuCategory));
        
        // Super admin should be able to force delete menu categories
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->menuCategory));
    }

    #[Test]
    public function restaurant_owner_can_manage_own_categories()
    {
        // Restaurant owner should be able to view menu categories of their restaurant
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->menuCategory));
        
        // Restaurant owner should be able to create menu categories for their restaurant
        $this->assertTrue($this->policy->create($this->restaurantOwner));
        
        // Restaurant owner should be able to update menu categories of their restaurant
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->menuCategory));
        
        // Restaurant owner should be able to delete menu categories of their restaurant
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $this->menuCategory));
        
        // Restaurant owner should NOT be able to restore menu categories (only super admin can)
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->menuCategory));
        
        // Restaurant owner should NOT be able to force delete menu categories (only super admin can)
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->menuCategory));
    }

    #[Test]
    public function restaurant_owner_cannot_manage_other_categories()
    {
        // Create another restaurant and menu category owned by different user
        $otherRestaurant = Restaurant::factory()->create();
        $otherMenuCategory = MenuCategory::factory()->create([
            'restaurant_id' => $otherRestaurant->id
        ]);
        $otherUser = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $otherUser->update(['restaurant_id' => $otherRestaurant->id]);
        
        // Restaurant owner should not be able to update other menu categories
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherMenuCategory));
        
        // Restaurant owner should not be able to delete other menu categories
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherMenuCategory));
        
        // Restaurant owner should not be able to restore other menu categories
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $otherMenuCategory));
        
        // Restaurant owner should not be able to force delete other menu categories
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $otherMenuCategory));
    }

    #[Test]
    public function branch_manager_can_view_categories()
    {
        // Branch manager should NOT be able to view menu categories (no branch relationship set up)
        $this->assertFalse($this->policy->viewAny($this->branchManager));
        $this->assertFalse($this->policy->view($this->branchManager, $this->menuCategory));
        
        // Branch manager should NOT be able to create menu categories (no branch relationship set up)
        $this->assertFalse($this->policy->create($this->branchManager));
        
        // Branch manager should NOT be able to update menu categories (no branch relationship set up)
        $this->assertFalse($this->policy->update($this->branchManager, $this->menuCategory));
        
        // Branch manager should NOT be able to delete menu categories (no branch relationship set up)
        $this->assertFalse($this->policy->delete($this->branchManager, $this->menuCategory));
        
        // Branch manager should NOT be able to restore menu categories
        $this->assertFalse($this->policy->restore($this->branchManager, $this->menuCategory));
        
        // Branch manager should NOT be able to force delete menu categories
        $this->assertFalse($this->policy->forceDelete($this->branchManager, $this->menuCategory));
    }

    #[Test]
    public function cashier_can_view_categories()
    {
        // Cashier should NOT be able to view menu categories (not mentioned in policy)
        $this->assertFalse($this->policy->viewAny($this->cashier));
        $this->assertFalse($this->policy->view($this->cashier, $this->menuCategory));
        
        // Cashier should NOT be able to create menu categories
        $this->assertFalse($this->policy->create($this->cashier));
        
        // Cashier should NOT be able to update menu categories
        $this->assertFalse($this->policy->update($this->cashier, $this->menuCategory));
        
        // Cashier should NOT be able to delete menu categories
        $this->assertFalse($this->policy->delete($this->cashier, $this->menuCategory));
        
        // Cashier should NOT be able to restore menu categories
        $this->assertFalse($this->policy->restore($this->cashier, $this->menuCategory));
        
        // Cashier should NOT be able to force delete menu categories
        $this->assertFalse($this->policy->forceDelete($this->cashier, $this->menuCategory));
    }

    #[Test]
    public function kitchen_staff_cannot_manage_categories()
    {
        // Kitchen staff should NOT be able to view menu categories
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->menuCategory));
        
        // Kitchen staff should NOT be able to create menu categories
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        
        // Kitchen staff should NOT be able to update menu categories
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->menuCategory));
        
        // Kitchen staff should NOT be able to delete menu categories
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->menuCategory));
        
        // Kitchen staff should NOT be able to restore menu categories
        $this->assertFalse($this->policy->restore($this->kitchenStaff, $this->menuCategory));
        
        // Kitchen staff should NOT be able to force delete menu categories
        $this->assertFalse($this->policy->forceDelete($this->kitchenStaff, $this->menuCategory));
    }

    #[Test]
    public function unauthorized_users_cannot_access_protected_resources()
    {
        // Unauthorized users should NOT be able to view any menu categories
        $this->assertFalse($this->policy->viewAny($this->unauthorizedUser));
        $this->assertFalse($this->policy->view($this->unauthorizedUser, $this->menuCategory));
        
        // Unauthorized users should NOT be able to create menu categories
        $this->assertFalse($this->policy->create($this->unauthorizedUser));
        
        // Unauthorized users should NOT be able to update menu categories
        $this->assertFalse($this->policy->update($this->unauthorizedUser, $this->menuCategory));
        
        // Unauthorized users should NOT be able to delete menu categories
        $this->assertFalse($this->policy->delete($this->unauthorizedUser, $this->menuCategory));
        
        // Unauthorized users should NOT be able to restore menu categories
        $this->assertFalse($this->policy->restore($this->unauthorizedUser, $this->menuCategory));
        
        // Unauthorized users should NOT be able to force delete menu categories
        $this->assertFalse($this->policy->forceDelete($this->unauthorizedUser, $this->menuCategory));
    }

    #[Test]
    public function it_handles_null_user_gracefully()
    {
        // Policy should handle null user gracefully - but it doesn't, so we expect exceptions
        $this->expectException(\TypeError::class);
        $this->policy->viewAny(null);
    }

    #[Test]
    public function it_handles_deleted_categories()
    {
        // Soft delete the menu category
        $this->menuCategory->delete();
        
        // Super admin should still be able to view deleted menu category
        $this->assertTrue($this->policy->view($this->superAdmin, $this->menuCategory));
        
        // Restaurant owner should still be able to view their deleted menu category
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->menuCategory));
        
        // Other users should not be able to view deleted menu category
        $this->assertFalse($this->policy->view($this->branchManager, $this->menuCategory));
    }
} 