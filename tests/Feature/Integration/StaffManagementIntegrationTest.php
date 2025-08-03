<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\StaffShift;
use App\Models\StaffAvailability;
use App\Models\PerformanceMetric;
use App\Models\CustomerFeedback;
use App\Models\EnhancedPermission;
use App\Models\StaffTransferHistory;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Order;
use App\Models\Customer;
use App\Services\StaffSchedulingService;
use App\Services\AnalyticsService;
use App\Services\MultiRestaurantService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffManagementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private StaffSchedulingService $schedulingService;
    private AnalyticsService $analyticsService;
    private MultiRestaurantService $multiRestaurantService;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private Restaurant $restaurant1;
    private Restaurant $restaurant2;
    private RestaurantBranch $branch1;
    private RestaurantBranch $branch2;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->schedulingService = new StaffSchedulingService();
        $this->analyticsService = new AnalyticsService();
        $this->multiRestaurantService = new MultiRestaurantService();
        
        // Create test data
        $this->restaurant1 = Restaurant::factory()->create(['name' => 'Restaurant 1']);
        $this->restaurant2 = Restaurant::factory()->create(['name' => 'Restaurant 2']);
        
        $this->branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'name' => 'Branch 1',
        ]);
        
        $this->branch2 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'name' => 'Branch 2',
        ]);
        
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

        $this->customer = Customer::factory()->create();
    }

    /** @test */
    public function it_integrates_staff_scheduling_with_performance_analytics(): void
    {
        // Arrange - Create availability and schedule staff
        StaffAvailability::create([
            'user_id' => $this->cashier->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Schedule staff
        $shiftData = [
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ];

        $shift = $this->schedulingService->createShift($shiftData);

        // Create orders for performance tracking
        $today = now()->startOfDay();
        $order1 = Order::factory()->create([
            'restaurant_branch_id' => $this->branch1->id,
            'restaurant_id' => $this->restaurant1->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'confirmed_at' => $today->copy()->setTime(10, 0),
            'prepared_at' => $today->copy()->setTime(10, 15),
            'created_at' => $today->copy()->setTime(9, 30),
        ]);

        $order2 = Order::factory()->create([
            'restaurant_branch_id' => $this->branch1->id,
            'restaurant_id' => $this->restaurant1->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'confirmed_at' => $today->copy()->setTime(14, 0),
            'prepared_at' => $today->copy()->setTime(14, 20),
            'created_at' => $today->copy()->setTime(13, 45),
        ]);

        // Create customer feedback
        $feedback1 = CustomerFeedback::create([
            'order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant1->id,
            'restaurant_branch_id' => $this->branch1->id,
            'user_id' => $this->cashier->id,
            'rating' => 4,
            'feedback_type' => 'service',
            'status' => 'approved',
            'created_at' => $today,
        ]);

        $feedback2 = CustomerFeedback::create([
            'order_id' => $order2->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant1->id,
            'restaurant_branch_id' => $this->branch1->id,
            'user_id' => $this->cashier->id,
            'rating' => 5,
            'feedback_type' => 'service',
            'status' => 'approved',
            'created_at' => $today,
        ]);

        // Debug: Check if feedback was created
        $this->assertDatabaseHas('customer_feedback', [
            'user_id' => $this->cashier->id,
            'status' => 'approved',
        ]);

        // Act - Calculate performance metrics
        $this->analyticsService->calculateUserPerformanceMetrics($this->cashier, $today);

        // Assert
        $this->assertDatabaseHas('staff_shifts', [
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->cashier->id,
            'metric_type' => 'order_processing_speed',
        ]);

        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->cashier->id,
            'metric_type' => 'customer_satisfaction',
        ]);
    }

    /** @test */
    public function it_integrates_staff_transfer_with_scheduling_and_analytics(): void
    {
        // Arrange - Create initial staff assignment and performance data
        StaffAvailability::create([
            'user_id' => $this->cashier->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Create shift in original branch
        $shift = $this->schedulingService->createShift([
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ]);

        // Create performance metrics
        PerformanceMetric::create([
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 15.5,
            'metric_date' => Carbon::today(),
        ]);

        // Act - Request staff transfer
        $transfer = $this->multiRestaurantService->requestStaffTransfer([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Staff relocation',
            'effective_date' => Carbon::tomorrow()->addDay(),
            'requested_by' => $this->restaurantOwner->id,
        ]);

        // Approve transfer
        $this->multiRestaurantService->approveStaffTransfer($transfer, $this->superAdmin->id, 'Approved');

        // Complete transfer
        $this->multiRestaurantService->completeStaffTransfer($transfer);

        // Assert
        $this->assertTrue($transfer->isCompleted());
        
        // Check that user's assignment was updated
        $this->cashier->refresh();
        $this->assertEquals($this->restaurant2->id, $this->cashier->restaurant_id);
        $this->assertEquals($this->branch2->id, $this->cashier->restaurant_branch_id);

        // Check that old shift still exists but new availability is needed
        $this->assertDatabaseHas('staff_shifts', [
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
        ]);

        // Performance metrics should still exist for historical data
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
        ]);
    }

    /** @test */
    public function it_integrates_permissions_with_scheduling_and_analytics(): void
    {
        // Arrange - Create permissions
        EnhancedPermission::create([
            'role' => 'BRANCH_MANAGER',
            'permission' => 'staff.schedule',
            'scope' => 'branch',
            'scope_id' => $this->branch1->id,
            'is_active' => true,
        ]);

        EnhancedPermission::create([
            'role' => 'BRANCH_MANAGER',
            'permission' => 'analytics.view',
            'scope' => 'restaurant',
            'scope_id' => $this->restaurant1->id,
            'is_active' => true,
        ]);

        // Create availability and schedule staff
        StaffAvailability::create([
            'user_id' => $this->cashier->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act - Check permissions for scheduling and analytics
        $canSchedule = $this->multiRestaurantService->hasPermission(
            $this->branchManager, 
            'staff.schedule', 
            $this->restaurant1->id, 
            $this->branch1->id
        );

        $canViewAnalytics = $this->multiRestaurantService->hasPermission(
            $this->branchManager, 
            'analytics.view', 
            $this->restaurant1->id
        );

        $cannotScheduleOtherBranch = $this->multiRestaurantService->hasPermission(
            $this->branchManager, 
            'staff.schedule', 
            $this->restaurant1->id, 
            $this->branch2->id
        );

        // Schedule staff (if permitted)
        if ($canSchedule) {
            $shift = $this->schedulingService->createShift([
                'user_id' => $this->cashier->id,
                'restaurant_branch_id' => $this->branch1->id,
                'shift_date' => Carbon::tomorrow(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'status' => 'scheduled',
            ]);
        }

        // Generate analytics (if permitted)
        if ($canViewAnalytics) {
            $this->analyticsService->calculateRestaurantAnalytics($this->restaurant1, Carbon::today());
        }

        // Assert
        $this->assertTrue($canSchedule);
        $this->assertTrue($canViewAnalytics);
        $this->assertFalse($cannotScheduleOtherBranch);

        if ($canSchedule) {
            $this->assertDatabaseHas('staff_shifts', [
                'user_id' => $this->cashier->id,
                'restaurant_branch_id' => $this->branch1->id,
            ]);
        }

        if ($canViewAnalytics) {
            $this->assertDatabaseHas('analytics_dashboard', [
                'restaurant_id' => $this->restaurant1->id,
            ]);
        }
    }

    /** @test */
    public function it_integrates_conflict_detection_with_performance_tracking(): void
    {
        // Arrange - Create conflicting shifts
        StaffAvailability::create([
            'user_id' => $this->cashier->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);

        // Create first shift
        $shift1 = $this->schedulingService->createShift([
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ]);

        // Create overlapping shift (should have conflicts)
        $shift2 = $this->schedulingService->createShift([
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '14:00',
            'end_time' => '22:00',
            'status' => 'scheduled',
        ]);

        // Create performance metrics
        PerformanceMetric::create([
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
            'metric_type' => 'attendance_rate',
            'metric_value' => 95.0,
            'metric_date' => Carbon::today(),
        ]);

        // Act - Generate performance report
        $report = $this->analyticsService->generateUserPerformanceReport(
            $this->cashier, 
            Carbon::now()->subDays(30), 
            Carbon::now()
        );

        // Assert
        $this->assertFalse($shift1->hasConflicts());
        $this->assertTrue($shift2->hasConflicts());
        $this->assertTrue($shift2->conflicts->contains('conflict_type', 'overlap'));

        $this->assertArrayHasKey('metrics_summary', $report);
        $this->assertArrayHasKey('detailed_metrics', $report);
    }

    /** @test */
    public function it_integrates_cross_restaurant_analytics_with_staff_management(): void
    {
        // Arrange - Create staff in multiple restaurants
        $cashier2 = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant2->id,
            'restaurant_branch_id' => $this->branch2->id,
            'status' => 'active',
        ]);

        // Create shifts in both restaurants
        StaffAvailability::create([
            'user_id' => $this->cashier->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        StaffAvailability::create([
            'user_id' => $cashier2->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        $shift1 = $this->schedulingService->createShift([
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ]);

        $shift2 = $this->schedulingService->createShift([
            'user_id' => $cashier2->id,
            'restaurant_branch_id' => $this->branch2->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ]);

        // Create performance metrics for both restaurants
        PerformanceMetric::create([
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 15.5,
            'metric_date' => Carbon::today(),
        ]);

        PerformanceMetric::create([
            'user_id' => $cashier2->id,
            'restaurant_id' => $this->restaurant2->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 18.2,
            'metric_date' => Carbon::today(),
        ]);

        // Act - Get cross-restaurant analytics
        $analytics = $this->multiRestaurantService->getCrossRestaurantAnalytics();

        // Assert
        $this->assertArrayHasKey($this->restaurant1->id, $analytics);
        $this->assertArrayHasKey($this->restaurant2->id, $analytics);

        $this->assertEquals($this->restaurant1->name, $analytics[$this->restaurant1->id]['restaurant_name']);
        $this->assertEquals($this->restaurant2->name, $analytics[$this->restaurant2->id]['restaurant_name']);

        // Check that shifts exist for both restaurants
        $this->assertDatabaseHas('staff_shifts', [
            'user_id' => $this->cashier->id,
            'restaurant_branch_id' => $this->branch1->id,
        ]);

        $this->assertDatabaseHas('staff_shifts', [
            'user_id' => $cashier2->id,
            'restaurant_branch_id' => $this->branch2->id,
        ]);

        // Check that performance metrics exist for both restaurants
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
        ]);

        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $cashier2->id,
            'restaurant_id' => $this->restaurant2->id,
        ]);
    }

    /** @test */
    public function it_integrates_staff_transfer_workflow_with_performance_tracking(): void
    {
        // Arrange - Create initial performance data
        PerformanceMetric::create([
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 15.5,
            'metric_date' => Carbon::today(),
        ]);

        // Create transfer request
        $transfer = $this->multiRestaurantService->requestStaffTransfer([
            'user_id' => $this->cashier->id,
            'from_restaurant_id' => $this->restaurant1->id,
            'to_restaurant_id' => $this->restaurant2->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_type' => 'restaurant_to_restaurant',
            'transfer_reason' => 'Performance improvement opportunity',
            'effective_date' => Carbon::tomorrow(),
            'requested_by' => $this->restaurantOwner->id,
        ]);

        // Act - Complete transfer workflow
        $this->multiRestaurantService->approveStaffTransfer($transfer, $this->superAdmin->id, 'Approved for performance improvement');
        $this->multiRestaurantService->completeStaffTransfer($transfer);

        // Create new performance data in new restaurant
        PerformanceMetric::create([
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant2->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 12.8, // Improved performance
            'metric_date' => Carbon::tomorrow(),
        ]);

        // Generate performance report
        $report = $this->analyticsService->generateUserPerformanceReport(
            $this->cashier, 
            Carbon::now()->subDays(30), 
            Carbon::now()
        );

        // Assert
        $this->assertTrue($transfer->isCompleted());
        
        $this->cashier->refresh();
        $this->assertEquals($this->restaurant2->id, $this->cashier->restaurant_id);
        $this->assertEquals($this->branch2->id, $this->cashier->restaurant_branch_id);

        // Check that performance data exists for both restaurants
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
        ]);

        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant2->id,
        ]);

        $this->assertArrayHasKey('metrics_summary', $report);
        $this->assertArrayHasKey('detailed_metrics', $report);
    }

    /** @test */
    public function it_integrates_permission_based_access_with_analytics(): void
    {
        // Arrange - Create permissions
        EnhancedPermission::create([
            'role' => 'RESTAURANT_OWNER',
            'permission' => 'analytics.view',
            'scope' => 'restaurant',
            'scope_id' => $this->restaurant1->id,
            'is_active' => true,
        ]);

        // Create performance data
        PerformanceMetric::create([
            'user_id' => $this->cashier->id,
            'restaurant_id' => $this->restaurant1->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 15.5,
            'metric_date' => Carbon::today(),
        ]);

        // Act - Check access permissions
        $canViewOwnRestaurant = $this->multiRestaurantService->checkCrossRestaurantAccess(
            $this->restaurantOwner, 
            $this->restaurant1
        );

        $cannotViewOtherRestaurant = $this->multiRestaurantService->checkCrossRestaurantAccess(
            $this->restaurantOwner, 
            $this->restaurant2
        );

        $accessibleRestaurants = $this->multiRestaurantService->getAccessibleRestaurants($this->restaurantOwner);

        // Generate analytics only for accessible restaurant
        if ($canViewOwnRestaurant) {
            $this->analyticsService->calculateRestaurantAnalytics($this->restaurant1, Carbon::today());
        }

        // Assert
        $this->assertTrue($canViewOwnRestaurant);
        $this->assertFalse($cannotViewOtherRestaurant);
        $this->assertCount(1, $accessibleRestaurants);
        $this->assertEquals($this->restaurant1->id, $accessibleRestaurants->first()->id);

        $this->assertDatabaseHas('analytics_dashboard', [
            'restaurant_id' => $this->restaurant1->id,
        ]);
    }
} 