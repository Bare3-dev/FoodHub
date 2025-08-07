<?php

namespace Tests\Feature\Api;

use App\Models\PosIntegration;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class POSIntegrationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id
        ]);
    }

    public function test_integrate_restaurant_with_square_pos()
    {
        $this->actingAs($this->user);

        $configuration = [
            'api_url' => 'https://api.square.com/v2',
            'api_key' => 'test_api_key',
            'location_id' => 'test_location_id',
            'webhook_secret' => 'test_webhook_secret'
        ];

        $response = $this->postJson("/api/pos/integrate/square", [
            'restaurant_id' => $this->restaurant->id,
            'configuration' => $configuration
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'restaurant_id',
                    'pos_type',
                    'configuration',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('pos_integrations', [
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);
    }

    public function test_get_integration_status()
    {
        $this->actingAs($this->user);

        PosIntegration::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        $response = $this->getJson("/api/pos/status/{$this->restaurant->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'restaurant_id',
                    'integrations' => [
                        '*' => [
                            'id',
                            'pos_type',
                            'is_active',
                            'last_sync_at',
                            'created_at'
                        ]
                    ]
                ]
            ]);
    }

    public function test_update_integration_configuration()
    {
        $this->actingAs($this->user);

        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        $newConfiguration = [
            'api_url' => 'https://api.square.com/v2',
            'api_key' => 'new_api_key',
            'location_id' => 'new_location_id',
            'webhook_secret' => 'new_webhook_secret'
        ];

        $response = $this->putJson("/api/pos/integrations/{$integration->id}/configuration", [
            'configuration' => $newConfiguration
        ]);

        $response->assertStatus(200);

        // Check that the integration was updated
        $this->assertDatabaseHas('pos_integrations', [
            'id' => $integration->id,
        ]);

        // Verify the configuration was updated by checking the fresh model
        $updatedIntegration = $integration->fresh();
        $this->assertEquals($newConfiguration, $updatedIntegration->configuration);
    }

    public function test_toggle_integration_status()
    {
        $this->actingAs($this->user);

        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => 'square',
            'is_active' => true
        ]);

        $response = $this->patchJson("/api/pos/integrations/{$integration->id}/toggle");

        $response->assertStatus(200);

        $this->assertFalse($integration->fresh()->is_active);
    }

    public function test_delete_integration()
    {
        $this->actingAs($this->user);

        $integration = PosIntegration::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => 'square'
        ]);

        $response = $this->deleteJson("/api/pos/integrations/{$integration->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('pos_integrations', [
            'id' => $integration->id
        ]);
    }

    public function test_unauthorized_access_returns_401()
    {
        $response = $this->postJson("/api/pos/integrate/square", [
            'restaurant_id' => $this->restaurant->id,
            'configuration' => []
        ]);

        $response->assertStatus(401);
    }
} 