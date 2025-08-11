<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\PosIntegration;
use App\Models\PosOrderMapping;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SquarePOSControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Restaurant $restaurant;
    private PosIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active'
        ]);
        $this->integration = PosIntegration::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => 'square',
            'is_active' => true,
            'configuration' => [
                'api_url' => 'https://api.square.com',
                'access_token' => 'test_token',
                'merchant_id' => 'test_merchant'
            ]
        ]);
    }

    public function test_sync_order_success()
    {
        $this->actingAs($this->user);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending'
        ]);

        // Mock the HTTP request to Square API
        Http::fake([
            '*' => Http::response(['order' => ['id' => 'pos_order_123']], 200)
        ]);

        $response = $this->postJson("/api/pos/square/orders/{$order->id}/sync");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order synced successfully'
            ]);

        $this->assertDatabaseHas('pos_order_mappings', [
            'foodhub_order_id' => $order->id,
            'pos_order_id' => 'pos_order_123',
            'pos_type' => 'square',
            'sync_status' => 'synced'
        ]);
    }

    public function test_sync_menu_success()
    {
        $this->actingAs($this->user);

        Http::fake([
            'https://api.square.com/*' => Http::response(['objects' => [
                [
                    'id' => 'item_1',
                    'name' => 'Test Item',
                    'price' => 10.99,
                    'description' => 'Test description',
                    'is_available' => true,
                    'category' => 'Test Category'
                ]
            ]], 200)
        ]);

        $response = $this->postJson("/api/pos/square/restaurants/{$this->restaurant->id}/menu/sync");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Menu synced successfully'
            ]);
    }

    public function test_sync_inventory_success()
    {
        $this->actingAs($this->user);

        Http::fake([
            'https://api.square.com/*' => Http::response(['objects' => [
                [
                    'id' => 'item_1',
                    'name' => 'Test Item',
                    'inventory_count' => 50,
                    'is_available' => true
                ]
            ]], 200)
        ]);

        $response = $this->postJson("/api/pos/square/restaurants/{$this->restaurant->id}/inventory/sync");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory synced successfully'
            ]);
    }

    public function test_handle_pos_webhook_success()
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending'
        ]);

        // Create the order mapping first
        PosOrderMapping::factory()->create([
            'foodhub_order_id' => $order->id,
            'pos_order_id' => 'pos_order_123',
            'pos_type' => 'square',
            'sync_status' => 'synced'
        ]);

        $webhookData = [
            'type' => 'order.updated',
            'data' => [
                'id' => 'pos_order_123',
                'status' => 'COMPLETED'
            ]
        ];

        // Mock the webhook signature validation and any external calls
        Http::fake([
            '*' => Http::response(['success' => true], 200)
        ]);

        $response = $this->withHeaders([
            'X-Square-Signature' => 'test_signature'
        ])->postJson("/api/pos/webhook/square", $webhookData);

        $response->assertStatus(200);

        // Verify the order status was updated
        $this->assertEquals('completed', $order->fresh()->status);
    }

    public function test_validate_pos_connection_success()
    {
        $this->actingAs($this->user);

        // Update the existing integration configuration
        $this->integration->update([
            'configuration' => [
                'api_url' => 'https://api.square.com/v2',
                'api_key' => 'test_key'
            ]
        ]);

        // Mock the HTTP request to Square API
        Http::fake([
            'https://api.square.com/v2/test' => Http::response(['success' => true], 200)
        ]);

        $response = $this->getJson("/api/pos/square/restaurants/{$this->restaurant->id}/connection/test");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Square POS connection validated successfully'
            ]);
    }

    public function test_get_integration_status()
    {
        $this->actingAs($this->user);

        $response = $this->getJson("/api/pos/square/restaurants/{$this->restaurant->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pos_type',
                    'integrated',
                    'is_active',
                    'last_sync_at',
                    'created_at'
                ]
            ]);
    }

    public function test_unauthorized_access_returns_401()
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);

        $response = $this->postJson("/api/pos/square/orders/{$order->id}/sync");

        $response->assertStatus(401);
    }
} 