<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\PerformanceMetric;
use App\Models\CustomerFeedback;
use App\Models\AnalyticsDashboard;
use App\Models\Order;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Customer;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $service;
    private User $staffMember;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new AnalyticsService();
        
        // Create test data
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        
        $this->staffMember = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        $this->customer = Customer::factory()->create();
    }

    /** @test */
    public function it_calculates_user_performance_metrics(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create orders for processing speed calculation
        $order1 = Order::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'confirmed_at' => $date->copy()->setTime(10, 0),
            'prepared_at' => $date->copy()->setTime(10, 15), // 15 minutes processing
            'created_at' => $date,
        ]);

        $order2 = Order::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'confirmed_at' => $date->copy()->setTime(11, 0),
            'prepared_at' => $date->copy()->setTime(11, 20), // 20 minutes processing
            'created_at' => $date,
        ]);

        // Create customer feedback for satisfaction calculation
        CustomerFeedback::create([
            'order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 4,
            'feedback_type' => 'service',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        CustomerFeedback::create([
            'order_id' => $order2->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 5,
            'feedback_type' => 'service',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateUserPerformanceMetrics($this->staffMember, $date);

        // Assert
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 17.5, // Average of 15 and 20 minutes
        ]);

        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'customer_satisfaction',
            'metric_value' => 4.5, // Average of 4 and 5
        ]);
    }

    /** @test */
    public function it_calculates_restaurant_analytics(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create orders for revenue calculation with the correct date
        $order1 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total_amount' => 100.00,
            'created_at' => $date,
        ]);

        $order2 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total_amount' => 150.00,
            'created_at' => $date,
        ]);

        // Create customer feedback for satisfaction calculation with the correct date
        CustomerFeedback::create([
            'order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'rating' => 4,
            'feedback_type' => 'overall',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        CustomerFeedback::create([
            'order_id' => $order2->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'rating' => 5,
            'feedback_type' => 'overall',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateRestaurantAnalytics($this->restaurant, $date);

        // Assert
        $this->assertDatabaseHas('analytics_dashboard', [
            'restaurant_id' => $this->restaurant->id,
            'metric_name' => 'daily_revenue',
        ]);

        $this->assertDatabaseHas('analytics_dashboard', [
            'restaurant_id' => $this->restaurant->id,
            'metric_name' => 'customer_satisfaction',
        ]);

        $this->assertDatabaseHas('analytics_dashboard', [
            'restaurant_id' => $this->restaurant->id,
            'metric_name' => 'order_volume',
        ]);
    }

    /** @test */
    public function it_generates_dashboard_data(): void
    {
        // Arrange
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        // Create analytics data
        AnalyticsDashboard::create([
            'restaurant_id' => $this->restaurant->id,
            'metric_name' => 'daily_revenue',
            'metric_value' => ['primary_value' => 1000.00, 'trend' => 5.2],
            'date_range' => 'daily',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data_type' => 'revenue',
        ]);

        // Create performance metrics
        PerformanceMetric::create([
            'user_id' => $this->staffMember->id,
            'restaurant_id' => $this->restaurant->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 15.5,
            'metric_date' => $startDate,
        ]);

        // Create order and customer feedback
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
        ]);

        CustomerFeedback::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'rating' => 4,
            'feedback_type' => 'overall',
            'status' => 'approved',
        ]);

        // Act
        $dashboardData = $this->service->getDashboardData($this->restaurant, $startDate, $endDate);

        // Assert
        $this->assertArrayHasKey('analytics', $dashboardData);
        $this->assertArrayHasKey('performance_metrics', $dashboardData);
        $this->assertArrayHasKey('customer_feedback', $dashboardData);
        $this->assertArrayHasKey('summary', $dashboardData);
        
        $this->assertEquals(1, $dashboardData['customer_feedback']['total_feedback']);
        $this->assertEquals(4.0, $dashboardData['customer_feedback']['average_rating']);
    }

    /** @test */
    public function it_generates_user_performance_report(): void
    {
        // Arrange
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();
        
        // Create performance metrics
        PerformanceMetric::create([
            'user_id' => $this->staffMember->id,
            'restaurant_id' => $this->restaurant->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 15.5,
            'metric_date' => $startDate,
        ]);

        PerformanceMetric::create([
            'user_id' => $this->staffMember->id,
            'restaurant_id' => $this->restaurant->id,
            'metric_type' => 'customer_satisfaction',
            'metric_value' => 4.2,
            'metric_date' => $startDate,
        ]);

        // Create orders and customer feedback
        $order1 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
        ]);

        $order2 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
        ]);

        CustomerFeedback::create([
            'order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 4,
            'feedback_type' => 'service',
            'status' => 'approved',
        ]);

        CustomerFeedback::create([
            'order_id' => $order2->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 5,
            'feedback_type' => 'service',
            'status' => 'approved',
        ]);

        // Act
        $report = $this->service->generateUserPerformanceReport($this->staffMember, $startDate, $endDate);

        // Assert
        $this->assertArrayHasKey('user_info', $report);
        $this->assertArrayHasKey('metrics_summary', $report);
        $this->assertArrayHasKey('customer_feedback', $report);
        $this->assertArrayHasKey('detailed_metrics', $report);
        $this->assertArrayHasKey('feedback_details', $report);
        
        $this->assertEquals($this->staffMember->name, $report['user_info']['name']);
        $this->assertEquals($this->staffMember->role, $report['user_info']['role']);
        $this->assertEquals(2, $report['customer_feedback']['total_feedback']);
        $this->assertEquals(4.5, $report['customer_feedback']['average_rating']);
    }

    /** @test */
    public function it_handles_empty_data_for_metrics_calculation(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Don't create any orders or feedback

        // Act
        $this->service->calculateUserPerformanceMetrics($this->staffMember, $date);

        // Assert - Should not create metrics when no data exists
        $this->assertDatabaseMissing('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'order_processing_speed',
        ]);

        $this->assertDatabaseMissing('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'customer_satisfaction',
        ]);
    }

    /** @test */
    public function it_calculates_trends_correctly(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create previous day's data for trend calculation
        $previousDate = $date->copy()->subDay();
        
        // Previous day revenue
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total_amount' => 100.00,
            'created_at' => $previousDate,
        ]);

        // Current day revenue (higher)
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total_amount' => 150.00,
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateRestaurantAnalytics($this->restaurant, $date);

        // Assert
        $revenueAnalytics = AnalyticsDashboard::where('restaurant_id', $this->restaurant->id)
            ->where('metric_name', 'daily_revenue')
            ->first();

        $this->assertNotNull($revenueAnalytics);
        $this->assertEquals(50.0, $revenueAnalytics->getTrendValue()); // 50% increase
    }

    /** @test */
    public function it_handles_zero_previous_values_for_trends(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Only create current day data (no previous day data)
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total_amount' => 100.00,
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateRestaurantAnalytics($this->restaurant, $date);

        // Assert
        $revenueAnalytics = AnalyticsDashboard::where('restaurant_id', $this->restaurant->id)
            ->where('metric_name', 'daily_revenue')
            ->first();

        $this->assertNotNull($revenueAnalytics);
        $this->assertNull($revenueAnalytics->getTrendValue()); // No trend when previous value is zero
    }

    /** @test */
    public function it_calculates_productivity_metrics(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create completed orders
        Order::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'completed',
            'created_at' => $date,
        ]);

        Order::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'completed',
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateUserPerformanceMetrics($this->staffMember, $date);

        // Assert
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'productivity',
            'metric_value' => 0.25, // 2 orders / 8 hours = 0.25 orders per hour
        ]);
    }

    /** @test */
    public function it_handles_feedback_moderation_status(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create orders first
        $order1 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'created_at' => $date,
        ]);

        $order2 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'created_at' => $date,
        ]);

        // Create approved feedback
        CustomerFeedback::create([
            'order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 4,
            'feedback_type' => 'service',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        // Create pending feedback (should be ignored)
        CustomerFeedback::create([
            'order_id' => $order2->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 5,
            'feedback_type' => 'service',
            'status' => 'pending',
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateUserPerformanceMetrics($this->staffMember, $date);

        // Assert
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'customer_satisfaction',
            'metric_value' => 4.0, // Only approved feedback counted
        ]);
    }

    /** @test */
    public function it_calculates_processing_speed_with_break_times(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create order with processing times
        $order = Order::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'confirmed_at' => $date->copy()->setTime(10, 0),
            'prepared_at' => $date->copy()->setTime(10, 30), // 30 minutes total
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateUserPerformanceMetrics($this->staffMember, $date);

        // Assert
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'order_processing_speed',
            'metric_value' => 30.0, // 30 minutes processing time
        ]);
    }

    /** @test */
    public function it_handles_multiple_feedback_types(): void
    {
        // Arrange
        $date = Carbon::today();
        
        // Create orders first
        $order1 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'created_at' => $date,
        ]);

        $order2 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'created_at' => $date,
        ]);

        // Create feedback for different types
        CustomerFeedback::create([
            'order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 4,
            'feedback_type' => 'service',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        CustomerFeedback::create([
            'order_id' => $order2->id,
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'user_id' => $this->staffMember->id,
            'rating' => 5,
            'feedback_type' => 'food_quality',
            'status' => 'approved',
            'created_at' => $date,
        ]);

        // Act
        $this->service->calculateUserPerformanceMetrics($this->staffMember, $date);

        // Assert
        $this->assertDatabaseHas('performance_metrics', [
            'user_id' => $this->staffMember->id,
            'metric_type' => 'customer_satisfaction',
            'metric_value' => 4.5, // Average of all feedback types
        ]);
    }
} 