<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\LoyaltyProgram;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\LoyaltyTier;
use App\Policies\LoyaltyProgramPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoyaltyProgramPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
    protected User $kitchenStaff;
    protected LoyaltyProgram $loyaltyProgram;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected LoyaltyTier $loyaltyTier;
    protected LoyaltyProgramPolicy $policy;

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
        
        // Associate branch manager and cashier with the restaurant
        $this->branchManager->update(['restaurant_id' => $this->restaurant->id]);
        $this->cashier->update(['restaurant_id' => $this->restaurant->id]);
        
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        // Create loyalty tier
        $this->loyaltyTier = LoyaltyTier::factory()->create([
            'name' => 'Bronze',
            'min_points_required' => 0,
            'discount_percentage' => 5.0
        ]);
        
        // Create test loyalty program
        $this->loyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Loyalty Program',
            'description' => 'Test loyalty program description',
            'points_per_dollar' => 1.0,
            'redemption_rate' => 0.01,
            'is_active' => true
        ]);
        
        $this->policy = new LoyaltyProgramPolicy();
    }

    /** @test */
    public function super_admin_can_perform_all_actions()
    {
        // Super admin should have full access to all loyalty programs
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->loyaltyProgram));
        $this->assertTrue($this->policy->create($this->superAdmin));
        $this->assertTrue($this->policy->update($this->superAdmin, $this->loyaltyProgram));
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->loyaltyProgram));
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->loyaltyProgram));
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->loyaltyProgram));
    }

    /** @test */
    public function restaurant_owner_can_manage_own_restaurant_loyalty_programs()
    {
        // Restaurant owner should have full access to their restaurant's loyalty programs
        $this->assertTrue($this->policy->viewAny($this->restaurantOwner));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $this->loyaltyProgram));
        $this->assertTrue($this->policy->create($this->restaurantOwner));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $this->loyaltyProgram));
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $this->loyaltyProgram));
        $this->assertTrue($this->policy->restore($this->restaurantOwner, $this->loyaltyProgram));
        $this->assertTrue($this->policy->forceDelete($this->restaurantOwner, $this->loyaltyProgram));
    }

    /** @test */
    public function restaurant_owner_cannot_manage_other_restaurant_loyalty_programs()
    {
        // Create another restaurant and loyalty program
        $otherRestaurant = Restaurant::factory()->create();
        $otherLoyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $otherRestaurant->id
        ]);
        
        // Restaurant owner should not have access to other restaurant's loyalty programs
        $this->assertFalse($this->policy->update($this->restaurantOwner, $otherLoyaltyProgram));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, $otherLoyaltyProgram));
        $this->assertFalse($this->policy->restore($this->restaurantOwner, $otherLoyaltyProgram));
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, $otherLoyaltyProgram));
    }

    /** @test */
    public function branch_manager_can_view_and_update_loyalty_programs()
    {
        // Branch manager should be able to view and update loyalty programs
        $this->assertTrue($this->policy->viewAny($this->branchManager));
        $this->assertTrue($this->policy->view($this->branchManager, $this->loyaltyProgram));
        $this->assertTrue($this->policy->update($this->branchManager, $this->loyaltyProgram));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->branchManager));
        $this->assertFalse($this->policy->delete($this->branchManager, $this->loyaltyProgram));
        $this->assertFalse($this->policy->restore($this->branchManager, $this->loyaltyProgram));
        $this->assertFalse($this->policy->forceDelete($this->branchManager, $this->loyaltyProgram));
    }

    /** @test */
    public function cashier_can_view_loyalty_programs()
    {
        // Cashier should only be able to view loyalty programs
        $this->assertTrue($this->policy->viewAny($this->cashier));
        $this->assertTrue($this->policy->view($this->cashier, $this->loyaltyProgram));
        
        // But should not be able to modify them
        $this->assertFalse($this->policy->create($this->cashier));
        $this->assertFalse($this->policy->update($this->cashier, $this->loyaltyProgram));
        $this->assertFalse($this->policy->delete($this->cashier, $this->loyaltyProgram));
        $this->assertFalse($this->policy->restore($this->cashier, $this->loyaltyProgram));
        $this->assertFalse($this->policy->forceDelete($this->cashier, $this->loyaltyProgram));
    }

    /** @test */
    public function customer_service_can_view_and_update_loyalty_programs()
    {
        // Customer service should be able to view and update loyalty programs
        $this->assertTrue($this->policy->viewAny($this->customerService));
        $this->assertTrue($this->policy->view($this->customerService, $this->loyaltyProgram));
        $this->assertTrue($this->policy->update($this->customerService, $this->loyaltyProgram));
        
        // But should not be able to create, delete, or restore
        $this->assertFalse($this->policy->create($this->customerService));
        $this->assertFalse($this->policy->delete($this->customerService, $this->loyaltyProgram));
        $this->assertFalse($this->policy->restore($this->customerService, $this->loyaltyProgram));
        $this->assertFalse($this->policy->forceDelete($this->customerService, $this->loyaltyProgram));
    }

    /** @test */
    public function kitchen_staff_cannot_manage_loyalty_programs()
    {
        // Kitchen staff should not have access to loyalty program management
        $this->assertFalse($this->policy->viewAny($this->kitchenStaff));
        $this->assertFalse($this->policy->view($this->kitchenStaff, $this->loyaltyProgram));
        $this->assertFalse($this->policy->create($this->kitchenStaff));
        $this->assertFalse($this->policy->update($this->kitchenStaff, $this->loyaltyProgram));
        $this->assertFalse($this->policy->delete($this->kitchenStaff, $this->loyaltyProgram));
        $this->assertFalse($this->policy->restore($this->kitchenStaff, $this->loyaltyProgram));
        $this->assertFalse($this->policy->forceDelete($this->kitchenStaff, $this->loyaltyProgram));
    }

    /** @test */
    public function inactive_users_cannot_access_loyalty_programs()
    {
        $inactiveUser = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'status' => 'inactive'
        ]);
        
        $this->assertFalse($this->policy->viewAny($inactiveUser));
        $this->assertFalse($this->policy->view($inactiveUser, $this->loyaltyProgram));
        $this->assertFalse($this->policy->create($inactiveUser));
        $this->assertFalse($this->policy->update($inactiveUser, $this->loyaltyProgram));
        $this->assertFalse($this->policy->delete($inactiveUser, $this->loyaltyProgram));
    }

    /** @test */
    public function unauthorized_users_cannot_access_loyalty_programs()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);
        
        $this->assertFalse($this->policy->viewAny($unauthorizedUser));
        $this->assertFalse($this->policy->view($unauthorizedUser, $this->loyaltyProgram));
        $this->assertFalse($this->policy->create($unauthorizedUser));
        $this->assertFalse($this->policy->update($unauthorizedUser, $this->loyaltyProgram));
        $this->assertFalse($this->policy->delete($unauthorizedUser, $this->loyaltyProgram));
    }

    /** @test */
    public function it_handles_null_user_gracefully()
    {
        $this->assertFalse($this->policy->viewAny(null));
        $this->assertFalse($this->policy->view(null, $this->loyaltyProgram));
        $this->assertFalse($this->policy->create(null));
        $this->assertFalse($this->policy->update(null, $this->loyaltyProgram));
        $this->assertFalse($this->policy->delete(null, $this->loyaltyProgram));
    }

    /** @test */
    public function it_handles_null_loyalty_program_gracefully()
    {
        $this->assertFalse($this->policy->view($this->restaurantOwner, null));
        $this->assertFalse($this->policy->update($this->restaurantOwner, null));
        $this->assertFalse($this->policy->delete($this->restaurantOwner, null));
        $this->assertFalse($this->policy->restore($this->restaurantOwner, null));
        $this->assertFalse($this->policy->forceDelete($this->restaurantOwner, null));
    }

    /** @test */
    public function it_handles_loyalty_program_with_different_statuses()
    {
        $activeProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true
        ]);
        
        $inactiveProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false
        ]);
        
        // Restaurant owner should be able to manage loyalty programs with all statuses
        $this->assertTrue($this->policy->view($this->restaurantOwner, $activeProgram));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $activeProgram));
        $this->assertTrue($this->policy->view($this->restaurantOwner, $inactiveProgram));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $inactiveProgram));
    }

    /** @test */
    public function it_handles_loyalty_program_with_deleted_restaurant()
    {
        // Create a loyalty program associated with a restaurant
        $restaurant = Restaurant::factory()->create();
        
        // Associate restaurant owner with this restaurant
        $this->restaurantOwner->update(['restaurant_id' => $restaurant->id]);
        
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $restaurant->id
        ]);
        
        // Delete the restaurant
        $restaurant->delete();
        
        // Restaurant owner should still be able to view the loyalty program
        $this->assertTrue($this->policy->view($this->restaurantOwner, $loyaltyProgram));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $loyaltyProgram));
    }

    /** @test */
    public function it_handles_loyalty_program_with_tiers()
    {
        // Create loyalty program with tiers
        $loyaltyProgramWithTiers = LoyaltyProgram::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Program with Tiers'
        ]);
        
        // Restaurant owner should be able to manage loyalty programs with tiers
        $this->assertTrue($this->policy->view($this->restaurantOwner, $loyaltyProgramWithTiers));
        $this->assertTrue($this->policy->update($this->restaurantOwner, $loyaltyProgramWithTiers));
        $this->assertTrue($this->policy->delete($this->restaurantOwner, $loyaltyProgramWithTiers));
    }
} 