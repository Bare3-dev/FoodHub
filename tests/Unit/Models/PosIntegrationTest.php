<?php

namespace Tests\Unit\Models;

use App\Models\PosIntegration;
use App\Models\Restaurant;
use App\Models\PosSyncLog;
use App\Models\PosOrderMapping;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_integration_can_be_created(): void
    {
        $restaurant = Restaurant::factory()->create();
        
        $integration = PosIntegration::create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'configuration' => [
                'api_url' => 'https://api.square.com/v2',
                'api_key' => 'test_key',
                'location_id' => 'test_location'
            ],
            'is_active' => true
        ]);

        $this->assertDatabaseHas('pos_integrations', [
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);
    }

    public function test_pos_integration_belongs_to_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id
        ]);

        $this->assertInstanceOf(Restaurant::class, $integration->restaurant);
        $this->assertEquals($restaurant->id, $integration->restaurant->id);
    }

    public function test_pos_integration_has_sync_logs(): void
    {
        $integration = PosIntegration::factory()->create();
        $syncLog = PosSyncLog::factory()->create([
            'pos_integration_id' => $integration->id
        ]);

        $this->assertTrue($integration->syncLogs->contains($syncLog));
    }

    public function test_pos_integration_has_order_mappings(): void
    {
        $restaurant = Restaurant::factory()->create();
        $order = Order::factory()->create(['restaurant_id' => $restaurant->id]);
        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $restaurant->id,
            'pos_type' => 'square'
        ]);
        
        $orderMapping = PosOrderMapping::factory()->create([
            'foodhub_order_id' => $order->id,
            'pos_order_id' => 'pos_order_123',
            'pos_type' => 'square'
        ]);

        $this->assertTrue($integration->orderMappings->contains($orderMapping));
    }

    public function test_is_active_method(): void
    {
        $activeIntegration = PosIntegration::factory()->create(['is_active' => true]);
        $inactiveIntegration = PosIntegration::factory()->create(['is_active' => false]);

        $this->assertTrue($activeIntegration->isActive());
        $this->assertFalse($inactiveIntegration->isActive());
    }

    public function test_get_config_method(): void
    {
        $integration = PosIntegration::factory()->create([
            'configuration' => [
                'api_url' => 'https://api.square.com/v2',
                'api_key' => 'test_key',
                'location_id' => 'test_location'
            ]
        ]);

        $this->assertEquals('https://api.square.com/v2', $integration->getConfig('api_url'));
        $this->assertEquals('test_key', $integration->getConfig('api_key'));
        $this->assertNull($integration->getConfig('non_existent'));
        $this->assertEquals('default', $integration->getConfig('non_existent', 'default'));
    }

    public function test_set_config_method(): void
    {
        $integration = PosIntegration::factory()->create([
            'configuration' => [
                'api_url' => 'https://api.square.com/v2'
            ]
        ]);

        $integration->setConfig('api_key', 'new_key');
        $integration->setConfig('nested.key', 'value');

        $this->assertEquals('new_key', $integration->getConfig('api_key'));
        $this->assertEquals('value', $integration->getConfig('nested.key'));
    }

    public function test_update_last_sync_method(): void
    {
        $integration = PosIntegration::factory()->create(['last_sync_at' => null]);
        
        $this->assertNull($integration->last_sync_at);
        
        $integration->updateLastSync();
        
        $this->assertNotNull($integration->fresh()->last_sync_at);
    }
} 