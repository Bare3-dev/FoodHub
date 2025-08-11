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
            'is_active' => true,
            'configuration' => [
                'api_url' => 'https://api.square.com',
                'access_token' => 'test_token',
                'merchant_id' => 'test_merchant'
            ]
        ]);
        
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'pending'
        ]);

        Http::fake([
            'api.square.com/*' => Http::response(['order' => ['id' => 'pos_order_123']], 200)
        ]);

        $result = $this->service->createPOSOrder($order, 'square');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('pos_order_123', $result['pos_order_id']);
        
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
                'api_url' => 'https://api.square.com',
                'access_token' => 'test_key',
                'merchant_id' => 'test_merchant'
            ]
        ]);
        
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id
        ]);

        Http::fake([
            'api.square.com/*' => Http::response(['error' => 'API Error'], 400)
        ]);

        $this->expectException(\Exception::class);
        $this->service->createPOSOrder($order, 'square');
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

        $result = $this->service->updateOrderStatus('pos_order_123', 'square', 'COMPLETED');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('completed', $order->fresh()->status);
    }

    public function test_sync_menu_items()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true,
            'configuration' => [
                'api_url' => 'https://api.square.com',
                'access_token' => 'test_token'
            ]
        ]);

        Http::fake([
            'api.square.com/*' => Http::response(['objects' => []], 200)
        ]);

        $result = $this->service->syncMenuItems($restaurant, 'square');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('synced_items', $result);
        $this->assertArrayHasKey('updated_items', $result);
    }

    public function test_sync_inventory_levels()
    {
        $restaurant = Restaurant::factory()->create();
        PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true,
            'configuration' => [
                'api_url' => 'https://api.square.com',
                'access_token' => 'test_token'
            ]
        ]);

        Http::fake([
            'api.square.com/*' => Http::response(['objects' => []], 200)
        ]);

        $result = $this->service->syncInventoryLevels($restaurant, 'square');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('updated_items', $result);
    }

    public function test_get_active_integration()
    {
        $restaurant = Restaurant::factory()->create();
        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        // Use reflection to access private method for testing
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getActiveIntegration');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $restaurant->id, 'square');

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

        // Use reflection to access private method for testing
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getActiveIntegration');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $restaurant->id, 'square');

        $this->assertNull($result);
    }
} 