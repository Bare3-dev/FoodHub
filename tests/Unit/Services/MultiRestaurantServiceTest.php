<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\EnhancedPermission;
use App\Models\StaffTransferHistory;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Services\MultiRestaurantService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MultiRestaurantServiceTest extends TestCase
{
    use RefreshDatabase;

    private MultiRestaurantService $service;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private Restaurant $restaurant1;
    private Restaurant $restaurant2;
    private RestaurantBranch $branch1;
    private RestaurantBranch $branch2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new MultiRestaurantService();
        
        // Create test restaurants
        $this->restaurant1 = Restaurant::factory()->create(['name' => 'Restaurant 1']);
        $this->restaurant2 = Restaurant::factory()->create(['name' => 'Restaurant 2']);
        
        // Create test branches
        $this->branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'name' => 'Branch 1',
        ]);
        
        $this->branch2 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'name' => 'Branch 2',
        ]);
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active',
        ]);
        
        $this->restaurantOwner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant1->id,
            'status' => 'active',
        ]);
        
        $this->branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'restaurant_id' => $this->restaurant1->id,
            'restaurant_branch_id' => $this->branch1->id,
            'status' => 'active',
        ]);
        
        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant1->id,
            'restaurant_branch_id' => $this->branch1->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_checks_permissions_correctly(): void
    {
        // Arrange - Create permissions
        EnhancedPermission::create([
            'role' => 'CASHIER',
            'permission' => 'orders.view',
            'scope' => 'branch',
            'scope_id' => $this->branch1->id,
            'is_active' => true,
        ]);

        EnhancedPermission::create([
            'role' => 'BRANCH_MANAGER',
            'permission' => 'orders.manage',
            'scope' => 'restaurant',
            'scope_id' => $this->restaurant1->id,
            'is_active' => true,
        ]);

        // Act & Assert
        // Cashier can view orders in their branch
        $this->assertTrue($this->service->hasPermission($this->cashier, 'orders.view', $this->restaurant1->id, $this->branch1->id));
        
        // Cashier cannot view orders in different branch
        $this->assertFalse($this->service->hasPermission($this->cashier, 'orders.view', $this->restaurant1->id, $this->branch2->id));
        
        // Branch manager can manage orders in their restaurant
        $this->assertTrue($this->service->hasPermission($this->branchManager, 'orders.manage', $this->restaurant1->id));
        
        // Super admin has all permissions
        $this->assertTrue($this->service->hasPermission($this->superAdmin, 'any.permission'));
    }

    /** @test */
    public function it_handles_global_permissions(): void
    {
        // Arrange - Create global permission
        EnhancedPermission::create([
            'role' => 'CASHIER',
            'permission' => 'profile.view',
            'scope' => 'global',
            'is_active' => true,
        ]);

        // Act & Assert
        $this->assertTrue($this->service->hasPermission($this->cashier, 'profile.view'));
        $this->assertTrue($this->service->hasPermission($this->cashier, 'profile.view', $this->restaurant1->id));
        $this->assertTrue($this->service->hasPermission($this->cashier, 'profile.view', $this->restaurant1->id, $this->branch1->id));
    }

    /** @test */
    public function it_requests_staff_transfer(): void
    {
        // Arrange
        $transferData = [
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
        ];

        // Act
        $transfer = $this->service->requestStaffTransfer($transferData);

        // Assert
        $this->assertInstanceOf(StaffTransferHistory::class, $transfer);
        $this->assertEquals($this->cashier->id, $transfer->user_id);
        $this->assertEquals($this->restaurant1->id, $transfer->from_restaurant_id);
        $this->assertEquals($this->restaurant2->id, $transfer->to_restaurant_id);
        $this->assertEquals('pending', $transfer->status);
    }

    /** @test */
    public function it_validates_transfer_request(): void
    {
        // Arrange - Try to transfer user to same location
        $transferData = [
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant1->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch1->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Invalid transfer',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User is already at the specified destination');
        
        $this->service->requestStaffTransfer($transferData);
    }

    /** @test */
    public function it_approves_staff_transfer(): void
    {
        // Arrange
        $transfer = StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending',
        ]);

        // Act
        $approvedTransfer = $this->service->approveStaffTransfer($transfer, $this->superAdmin->id, 'Approved');

        // Assert
        $this->assertTrue($approvedTransfer->isApproved());
        $this->assertEquals($this->superAdmin->id, $approvedTransfer->approved_by);
        $this->assertNotNull($approvedTransfer->approved_at);
    }

    /** @test */
    public function it_rejects_staff_transfer(): void
    {
        // Arrange
        $transfer = StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending',
        ]);

        // Act
        $rejectedTransfer = $this->service->rejectStaffTransfer($transfer, $this->superAdmin->id, 'Not needed');

        // Assert
        $this->assertTrue($rejectedTransfer->isRejected());
        $this->assertEquals($this->superAdmin->id, $rejectedTransfer->rejected_by);
        $this->assertNotNull($rejectedTransfer->rejected_at);
    }

    /** @test */
    public function it_completes_staff_transfer(): void
    {
        // Arrange
        $transfer = StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'approved',
        ]);

        // Act
        $completedTransfer = $this->service->completeStaffTransfer($transfer);

        // Assert
        $this->assertTrue($completedTransfer->isCompleted());
        $this->assertNotNull($completedTransfer->actual_transfer_date);
        
        // Check that user's assignment was updated
        $this->cashier->refresh();
        $this->assertEquals($this->restaurant2->id, $this->cashier->restaurant_id);
        $this->assertEquals($this->branch2->id, $this->cashier->restaurant_branch_id);
    }

    /** @test */
    public function it_prevents_completing_unapproved_transfer(): void
    {
        // Arrange
        $transfer = StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending', // Not approved
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transfer must be approved before completion');
        
        $this->service->completeStaffTransfer($transfer);
    }

    /** @test */
    public function it_checks_transfer_approval_permissions(): void
    {
        // Arrange
        $transfer = StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending',
        ]);

        // Act & Assert
        // Super admin can approve any transfer
        $approvedTransfer = $this->service->approveStaffTransfer($transfer, $this->superAdmin->id);
        $this->assertTrue($approvedTransfer->isApproved());
        
        // Restaurant owner can approve transfers within their restaurant
        $transfer->update(['status' => 'pending']);
        $approvedTransfer = $this->service->approveStaffTransfer($transfer, $this->restaurantOwner->id);
        $this->assertTrue($approvedTransfer->isApproved());
        
        // Branch manager cannot approve cross-restaurant transfers
        $transfer->update(['status' => 'pending']);
        $this->expectException(\Exception::class);
        $this->service->approveStaffTransfer($transfer, $this->branchManager->id);
    }

    /** @test */
    public function it_gets_pending_transfers_for_user(): void
    {
        // Arrange
        StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending',
        ]);

        // Act
        $pendingTransfers = $this->service->getPendingTransfers($this->superAdmin);

        // Assert
        $this->assertCount(1, $pendingTransfers);
        $this->assertEquals($this->cashier->id, $pendingTransfers->first()->user_id);
    }

    /** @test */
    public function it_filters_pending_transfers_by_user_role(): void
    {
        // Arrange
        StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending',
        ]);

        // Act
        $pendingTransfers = $this->service->getPendingTransfers($this->branchManager);

        // Assert - Branch manager should not see cross-restaurant transfers
        $this->assertCount(0, $pendingTransfers);
    }

    /** @test */
    public function it_gets_transfer_statistics(): void
    {
        // Arrange
        StaffTransferHistory::create([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'pending',
        ]);

        StaffTransferHistory::create([
            'user_id' => $this->branchManager->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant1->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'branch_to_branch',
            'transfer_reason' => 'Branch transfer',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
            'status' => 'completed',
        ]);

        // Act
        $statistics = $this->service->getTransferStatistics($this->restaurant1);

        // Assert
        $this->assertEquals(2, $statistics['total_transfers']);
        $this->assertEquals(1, $statistics['pending_transfers']);
        $this->assertEquals(1, $statistics['completed_transfers']);
        $this->assertEquals(0, $statistics['rejected_transfers']);
    }

    /** @test */
    public function it_checks_cross_restaurant_access(): void
    {
        // Act & Assert
        // Super admin can access all restaurants
        $this->assertTrue($this->service->checkCrossRestaurantAccess($this->superAdmin, $this->restaurant1));
        $this->assertTrue($this->service->checkCrossRestaurantAccess($this->superAdmin, $this->restaurant2));
        
        // Restaurant owner can only access their own restaurant
        $this->assertTrue($this->service->checkCrossRestaurantAccess($this->restaurantOwner, $this->restaurant1));
        $this->assertFalse($this->service->checkCrossRestaurantAccess($this->restaurantOwner, $this->restaurant2));
        
        // Branch manager can only access their restaurant
        $this->assertTrue($this->service->checkCrossRestaurantAccess($this->branchManager, $this->restaurant1));
        $this->assertFalse($this->service->checkCrossRestaurantAccess($this->branchManager, $this->restaurant2));
        
        // Cashier can only access their restaurant
        $this->assertTrue($this->service->checkCrossRestaurantAccess($this->cashier, $this->restaurant1));
        $this->assertFalse($this->service->checkCrossRestaurantAccess($this->cashier, $this->restaurant2));
    }

    /** @test */
    public function it_gets_accessible_restaurants_for_user(): void
    {
        // Act
        $superAdminRestaurants = $this->service->getAccessibleRestaurants($this->superAdmin);
        $ownerRestaurants = $this->service->getAccessibleRestaurants($this->restaurantOwner);
        $managerRestaurants = $this->service->getAccessibleRestaurants($this->branchManager);

        // Assert
        $this->assertCount(2, $superAdminRestaurants); // All restaurants
        $this->assertCount(1, $ownerRestaurants); // Only their restaurant
        $this->assertCount(1, $managerRestaurants); // Only their restaurant
    }

    /** @test */
    public function it_gets_accessible_branches_for_user(): void
    {
        // Act
        $superAdminBranches = $this->service->getAccessibleBranches($this->superAdmin);
        $ownerBranches = $this->service->getAccessibleBranches($this->restaurantOwner);
        $managerBranches = $this->service->getAccessibleBranches($this->branchManager);

        // Assert
        $this->assertCount(2, $superAdminBranches); // All branches
        $this->assertCount(1, $ownerBranches); // Only their restaurant's branches
        $this->assertCount(1, $managerBranches); // Only their branch
    }

    /** @test */
    public function it_creates_permissions(): void
    {
        // Arrange
        $permissionData = [
            'role' => 'CASHIER',
            'permission' => 'orders.view',
            'scope' => 'branch',
            'scope_id' => $this->branch1->id,
            'is_active' => true,
            'description' => 'View orders in branch',
        ];

        // Act
        $permission = $this->service->createPermission($permissionData);

        // Assert
        $this->assertInstanceOf(EnhancedPermission::class, $permission);
        $this->assertEquals('CASHIER', $permission->role);
        $this->assertEquals('orders.view', $permission->permission);
        $this->assertEquals('branch', $permission->scope);
        $this->assertEquals($this->branch1->id, $permission->scope_id);
    }

    /** @test */
    public function it_updates_permissions(): void
    {
        // Arrange
        $permission = EnhancedPermission::create([
            'role' => 'CASHIER',
            'permission' => 'orders.view',
            'scope' => 'branch',
            'scope_id' => $this->branch1->id,
            'is_active' => true,
        ]);

        $updateData = [
            'is_active' => false,
            'description' => 'Updated description',
        ];

        // Act
        $updatedPermission = $this->service->updatePermission($permission, $updateData);

        // Assert
        $this->assertFalse($updatedPermission->is_active);
        $this->assertEquals('Updated description', $updatedPermission->description);
    }

    /** @test */
    public function it_deletes_permissions(): void
    {
        // Arrange
        $permission = EnhancedPermission::create([
            'role' => 'CASHIER',
            'permission' => 'orders.view',
            'scope' => 'branch',
            'scope_id' => $this->branch1->id,
            'is_active' => true,
        ]);

        // Act
        $result = $this->service->deletePermission($permission);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('enhanced_permissions', ['id' => $permission->id]);
    }

    /** @test */
    public function it_gets_user_permissions(): void
    {
        // Arrange
        EnhancedPermission::create([
            'role' => 'CASHIER',
            'permission' => 'orders.view',
            'scope' => 'branch',
            'scope_id' => $this->branch1->id,
            'is_active' => true,
        ]);

        EnhancedPermission::create([
            'role' => 'CASHIER',
            'permission' => 'orders.create',
            'scope' => 'global',
            'is_active' => true,
        ]);

        // Act
        $permissions = $this->service->getUserPermissions($this->cashier);

        // Assert
        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->contains('permission', 'orders.view'));
        $this->assertTrue($permissions->contains('permission', 'orders.create'));
    }

    /** @test */
    public function it_gets_cross_restaurant_analytics(): void
    {
        // Act
        $analytics = $this->service->getCrossRestaurantAnalytics();

        // Assert
        $this->assertArrayHasKey($this->restaurant1->id, $analytics);
        $this->assertArrayHasKey($this->restaurant2->id, $analytics);
        
        $this->assertEquals($this->restaurant1->name, $analytics[$this->restaurant1->id]['restaurant_name']);
        $this->assertEquals($this->restaurant2->name, $analytics[$this->restaurant2->id]['restaurant_name']);
    }
} 