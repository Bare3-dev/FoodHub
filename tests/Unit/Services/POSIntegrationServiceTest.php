<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\PosIntegration;
use App\Models\PosOrderMapping;
use App\Models\Restaurant;
use App\Services\NotificationService;
use App\Services\POSIntegrationService;
use App\Services\SecurityLoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class POSIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private POSIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(POSIntegrationService::class);
    }

    public function test_create_pos_order_success()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);
        
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'pending'
        ]);

        Http::fake([
            'square.com/api/*' => Http::response(['id' => 'pos_order_123'], 200)
        ]);

        $result = $this->service->createPOSOrder($order, 'square');

        $this->assertTrue($result);
        
        $this->assertDatabaseHas('pos_order_mappings', [
            'foodhub_order_id' => $order->id,
            'pos_order_id' => 'pos_order_123',
            'pos_type' => 'square',
            'sync_status' => 'synced'
        ]);
    }

    public function test_create_pos_order_failure()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true,
            'configuration' => [
                'api_url' => 'https://api.square.com/v2/fail',
                'api_key' => 'test_key'
            ]
        ]);
        
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id
        ]);

        $result = $this->service->createPOSOrder($order, 'square');

        $this->assertFalse($result);
        
        $this->assertDatabaseHas('pos_order_mappings', [
            'foodhub_order_id' => $order->id,
            'pos_type' => 'square',
            'sync_status' => 'failed'
        ]);
    }

    public function test_update_order_status()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);
        
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'pending'
        ]);
        
        PosOrderMapping::factory()->create([
            'foodhub_order_id' => $order->id,
            'pos_order_id' => 'pos_order_123',
            'pos_type' => 'square',
            'sync_status' => 'synced'
        ]);

        $result = $this->service->updateOrderStatus($order, 'square', 'completed');

        $this->assertTrue($result);
        $this->assertEquals('completed', $order->fresh()->status);
    }

    public function test_sync_menu_items()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        Http::fake([
            'square.com/api/*' => Http::response(['success' => true], 200)
        ]);

        $result = $this->service->syncMenuItems($restaurant, 'square');

        $this->assertTrue($result);
    }

    public function test_sync_inventory_levels()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        Http::fake([
            'square.com/api/*' => Http::response(['success' => true], 200)
        ]);

        $result = $this->service->syncInventoryLevels($restaurant, 'square');

        $this->assertTrue($result);
    }

    public function test_get_active_integration()
    {
        $restaurant = Restaurant::factory()->create();
        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        $result = $this->service->getActiveIntegration($restaurant, 'square');

        $this->assertEquals($integration->id, $result->id);
    }

    public function test_get_active_integration_returns_null_when_inactive()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => false
        ]);

        $result = $this->service->getActiveIntegration($restaurant, 'square');

        $this->assertNull($result);
    }
} 