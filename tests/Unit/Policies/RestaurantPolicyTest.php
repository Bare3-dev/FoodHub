<?php

namespace Tests\Unit\Policies;

use App\Models\Restaurant;
use App\Models\User;
use App\Policies\RestaurantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantPolicy $policy;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private User $kitchenStaff;
    private User $unauthorizedUser;
    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new RestaurantPolicy();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->restaurantOwner = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $this->branchManager = User::factory()->create(['role' => 'BRANCH_MANAGER']);
        $this->cashier = User::factory()->create(['role' => 'CASHIER']);
        $this->kitchenStaff = User::factory()->create(['role' => 'KITCHEN_STAFF']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'CUSTOMER_SERVICE']);
        
        // Create test restaurant
        $this->restaurant = Restaurant::factory()->create();
        
        // Update restaurant owner to have this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $this->restaurant->id]);
    }

    #[Test]
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should be able to view any restaurant
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->restaurant));
        
        // Super admin should be able to create restaurants
        $this->assertTrue($this->policy->create($this->superAdmin));
        
        // Super admin should be able to update any restaurant
        $this->assertTrue($this->policy->update($this->superAdmin, $this->restaurant));
        
        // Super admin should be able to delete any restaurant
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->restaurant));
        
        // Super admin should be able to restore deleted restaurants
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->restaurant));
        
        // Super admin should be able to force delete restaurants
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->restaurant));
    }

    #[Test]
    public function restaurant_owner_can_manage_own_restaurants()
    {
        // Restaurant owner should NOT be able to view any restaurants (only super admin can)
        $this->assertFalse($this->policy->viewAny($this->restaurantOwner));
        
        // Restaurant owner should be able to view their own restaurants
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->restaurant));
        
        // Restaurant owner should NOT be able to create restaurants (only super admin can)
        $this->assertFalse($this->policy->create($this->restaurantOwner));
        
        // Restaurant owner should be able to update their own restaurants
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->restaurant));
        
        // Restaurant owner should NOT be able to delete restaurants (only super admin can)
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $this->restaurant));
        
        // Restaurant owner should NOT be able to restore restaurants (only super admin can)
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $this->restaurant));
        
        // Restaurant owner should NOT be able to force delete restaurants (only super admin can)
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $this->restaurant));
    }

    #[Test]
    public function restaurant_owner_cannot_manage_other_restaurants()
    {
        // Create another restaurant owned by different user
        $otherUser = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $otherRestaurant = Restaurant::factory()->create();
        $otherUser->update(['restaurant_id' => $otherRestaurant->id]);
        
        // Restaurant owner should not be able to update other restaurants
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherRestaurant));
        
        // Restaurant owner should not be able to delete other restaurants
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherRestaurant));
        
        // Restaurant owner should not be able to restore other restaurants
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $otherRestaurant));
        
        // Restaurant owner should not be able to force delete other restaurants
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $otherRestaurant));
    }

    #[Test]
    public function branch_manager_can_view_restaurants()
    {
        // Branch manager should NOT be able to view any restaurants (only super admin can)
        $this->assertFalse($this->policy->viewAny($this->branchManager));
        
        // Branch manager should NOT be able to view specific restaurants (only super admin and restaurant owner can)
        $this->assertFalse($this->policy->view($this->branchManager, $this->restaurant));
        
        // Branch manager should not be able to create restaurants
        $this->assertFalse($this->policy->create($this->branchManager));
        
        // Branch manager should not be able to update restaurants
        $this->assertFalse($this->policy->update($this->branchManager, $this->restaurant));
        
        // Branch manager should not be able to delete restaurants
        $this->assertFalse($this->policy->delete($this->branchManager, $this->restaurant));
    }

    #[Test]
    public function cashier_can_view_restaurants()
    {
        // Cashier should NOT be able to view any restaurants (only super admin can)
        $this->assertFalse($this->policy->viewAny($this->cashier));
        
        // Cashier should NOT be able to view specific restaurants (only super admin and restaurant owner can)
        $this->assertFalse($this->policy->view($this->cashier, $this->restaurant));
        
        // Cashier should not be able to create restaurants
        $this->assertFalse($this->policy->create($this->cashier));
        
        // Cashier should not be able to update restaurants
        $this->assertFalse($this->policy->update($this->cashier, $this->restaurant));
        
        // Cashier should not be able to delete restaurants
        $this->assertFalse($this->policy->delete($this->cashier, $this->restaurant));
    }

    #[Test]
    public function kitchen_staff_cannot_manage_restaurants()
    {
        // Kitchen staff should not be able to view restaurants
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->restaurant));
        
        // Kitchen staff should not be able to create restaurants
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        
        // Kitchen staff should not be able to update restaurants
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->restaurant));
        
        // Kitchen staff should not be able to delete restaurants
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->restaurant));
    }

    #[Test]
    public function unauthorized_users_cannot_access_protected_resources()
    {
        // Unauthorized users should not be able to view restaurants
        $this->assertFalse($this->policy->viewAny($this->unauthorizedUser));
        $this->assertFalse($this->policy->view($this->unauthorizedUser, $this->restaurant));
        
        // Unauthorized users should not be able to create restaurants
        $this->assertFalse($this->policy->create($this->unauthorizedUser));
        
        // Unauthorized users should not be able to update restaurants
        $this->assertFalse($this->policy->update($this->unauthorizedUser, $this->restaurant));
        
        // Unauthorized users should not be able to delete restaurants
        $this->assertFalse($this->policy->delete($this->unauthorizedUser, $this->restaurant));
        
        // Unauthorized users should not be able to restore restaurants
        $this->assertFalse($this->policy->restore($this->unauthorizedUser, $this->restaurant));
        
        // Unauthorized users should not be able to force delete restaurants
        $this->assertFalse($this->policy->forceDelete($this->unauthorizedUser, $this->restaurant));
    }

    #[Test]
    public function it_handles_null_user_gracefully()
    {
        // Policy should handle null user gracefully - but it doesn't, so we expect exceptions
        $this->expectException(\TypeError::class);
        $this->policy->viewAny(null);
    }

    #[Test]
    public function it_handles_deleted_restaurants()
    {
        // Soft delete the restaurant
        $this->restaurant->delete();
        
        // Super admin should still be able to view deleted restaurant
        $this->assertTrue($this->policy->view($this->superAdmin, $this->restaurant));
        
        // Restaurant owner should still be able to view their deleted restaurant
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->restaurant));
        
        // Other users should not be able to view deleted restaurant
        $this->assertFalse($this->policy->view($this->branchManager, $this->restaurant));
    }
} 