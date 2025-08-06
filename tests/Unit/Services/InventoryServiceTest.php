<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\BranchMenuItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SecurityLoggingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventoryService;
    private SecurityLoggingService $securityLoggingService;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->securityLoggingService = Mockery::mock(SecurityLoggingService::class);
        $this->notificationService = new NotificationService();
        
        $this->inventoryService = new InventoryService(
            $this->securityLoggingService,
            $this->notificationService
        );
    }

    public function test_update_item_stock_updates_quantity_and_status(): void
    {
        // Create test data
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem = MenuItem::factory()->create();
        $branchMenuItem = BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem->id,
            'stock_quantity' => 10,
            'min_stock_threshold' => 5,
            'track_inventory' => true,
            'stock_status' => 'available',
        ]);

        // Update stock
        $this->inventoryService->updateItemStock($menuItem, $branch, 15);

        // Assert stock was updated
        $branchMenuItem->refresh();
        $this->assertEquals(15, $branchMenuItem->stock_quantity);
        $this->assertEquals('in_stock', $branchMenuItem->stock_status);
    }

    public function test_check_item_availability_with_6_layer_validation(): void
    {
        // Create test data
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'operating_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '22:00'],
                'saturday' => ['open' => '09:00', 'close' => '22:00'],
                'sunday' => ['open' => '09:00', 'close' => '22:00'],
            ]
        ]);
        $menuItem = MenuItem::factory()->create();
        $branchMenuItem = BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem->id,
            'stock_quantity' => 10,
            'min_stock_threshold' => 5,
            'track_inventory' => true,
            'is_available' => true,
            'stock_status' => 'available',
            'time_schedules' => [
                ['day' => 'monday', 'start_time' => '09:00', 'end_time' => '22:00'],
                ['day' => 'tuesday', 'start_time' => '09:00', 'end_time' => '22:00'],
                ['day' => 'wednesday', 'start_time' => '09:00', 'end_time' => '22:00'],
                ['day' => 'thursday', 'start_time' => '09:00', 'end_time' => '22:00'],
                ['day' => 'friday', 'start_time' => '09:00', 'end_time' => '22:00'],
                ['day' => 'saturday', 'start_time' => '09:00', 'end_time' => '22:00'],
                ['day' => 'sunday', 'start_time' => '09:00', 'end_time' => '22:00'],
            ],
            'kitchen_capacity' => 100,
            'max_daily_orders' => 50,
        ]);

        // Test availability check
        $isAvailable = $this->inventoryService->checkItemAvailability($menuItem, $branch);
        $this->assertTrue($isAvailable);
    }

    public function test_get_low_stock_items_with_classification(): void
    {
        // Create test data
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem1 = MenuItem::factory()->create();
        $menuItem2 = MenuItem::factory()->create();
        
        // Create items with different stock levels
        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem1->id,
            'stock_quantity' => 1, // Critical
            'min_stock_threshold' => 5,
            'track_inventory' => true,
        ]);

        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem2->id,
            'stock_quantity' => 3, // High
            'min_stock_threshold' => 5,
            'track_inventory' => true,
        ]);

        // Get low stock items
        $lowStockItems = $this->inventoryService->getLowStockItems($restaurant, 5);

        // Assert structure
        $this->assertArrayHasKey('critical', $lowStockItems);
        $this->assertArrayHasKey('high', $lowStockItems);
        $this->assertArrayHasKey('medium', $lowStockItems);
    }

    public function test_generate_inventory_report(): void
    {
        // Create test data
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem = MenuItem::factory()->create();
        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem->id,
            'stock_quantity' => 10,
            'min_stock_threshold' => 5,
            'track_inventory' => true,
        ]);

        // Generate report
        $report = $this->inventoryService->generateInventoryReport($restaurant, now());

        // Assert report structure
        $this->assertArrayHasKey('restaurant_id', $report);
        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('stock_movements', $report);
        $this->assertArrayHasKey('turnover_rates', $report);
        $this->assertArrayHasKey('low_stock_alerts', $report);
        $this->assertArrayHasKey('out_of_stock_items', $report);
        $this->assertArrayHasKey('fast_movers', $report);
        $this->assertArrayHasKey('slow_movers', $report);
        $this->assertArrayHasKey('seasonal_trends', $report);
        $this->assertArrayHasKey('branch_comparison', $report);
        $this->assertArrayHasKey('optimization_insights', $report);
        $this->assertArrayHasKey('summary', $report);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 