<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class ConfigurationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'status' => 'active',
        ]);
        
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        
        // Ensure branch has restaurant relationship loaded and refresh from database
        $this->branch->refresh();
        $this->branch->load('restaurant');
        
        // Associate user with restaurant
        $this->user->update(['restaurant_id' => $this->restaurant->id]);
        
        // Assign required permissions to the user
        $this->user->update([
            'permissions' => [
                'view restaurant configs',
                'create restaurant configs',
                'update restaurant configs',
                'delete restaurant configs',
                'restore restaurant configs',
                'force delete restaurant configs',
            ]
        ]);
        
        // Debug: Check what permissions the user actually has
        \Log::info('Test setup debug', [
            'user_id' => $this->user->id,
            'user_restaurant_id' => $this->user->restaurant_id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'branch_restaurant_id' => $this->branch->restaurant_id,
            'branch_restaurant_loaded' => $this->branch->relationLoaded('restaurant'),
            'branch_restaurant_object' => $this->branch->restaurant_id,
            'user_permissions' => $this->user->permissions,
            'user_has_view_permission' => $this->user->hasPermission('view restaurant configs'),
            'user_role' => $this->user->role,
        ]);
    }

    /** @test */
    public function it_can_get_restaurant_config()
    {
        // Create test config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=test_key");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => 'test_value',
                'message' => 'Restaurant configuration retrieved successfully',
            ]);
    }

    /** @test */
    public function it_can_get_all_restaurant_configs()
    {
        // Create test configs
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'custom_key',
            'config_value' => 'custom_value',
            'data_type' => 'string',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Restaurant configuration retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'custom_key',
                    'loyalty_points_per_currency',
                    'loyalty_currency_per_point',
                    'loyalty_tier_thresholds',
                    'loyalty_spin_wheel_probabilities',
                    'loyalty_stamp_card_requirements',
                    'operating_hours',
                ],
            ]);
    }

    /** @test */
    public function it_can_set_restaurant_config()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/config", [
                'key' => 'test_key',
                'value' => 'test_value',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Restaurant configuration set successfully',
            ]);

        // Verify config was created
        $this->assertDatabaseHas('restaurant_configs', [
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
        ]);
    }

    /** @test */
    public function it_validates_config_key_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/config", [
                'key' => 'invalid-key',
                'value' => 'test_value',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    /** @test */
    public function it_can_get_branch_config()
    {
        // Create branch-specific config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => "branch_{$this->branch->id}_test_key",
            'config_value' => 'branch_value',
            'data_type' => 'string',
        ]);

        // Debug: Check user state before request
        \Log::info('Branch config test debug', [
            'user_id' => $this->user->id,
            'user_restaurant_id' => $this->user->restaurant_id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'branch_restaurant_id' => $this->branch->restaurant_id,
            'branch_restaurant_loaded' => $this->branch->relationLoaded('restaurant'),
            'branch_restaurant_object' => $this->branch->restaurant_id,
            'user_permissions' => $this->user->permissions,
            'user_has_view_permission' => $this->user->hasPermission('view restaurant configs'),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurant-branches/{$this->branch->id}/config?key=test_key");

        // Debug: Log the response details
        \Log::info('Test response details', [
            'status' => $response->status(),
            'content' => $response->content(),
            'headers' => $response->headers->all(),
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => 'branch_value',
                'message' => 'Branch configuration retrieved successfully',
            ]);
    }

    /** @test */
    public function it_falls_back_to_restaurant_config_for_branch()
    {
        // Create restaurant config only
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'restaurant_value',
            'data_type' => 'string',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurant-branches/{$this->branch->id}/config?key=test_key");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => 'restaurant_value',
                'message' => 'Branch configuration retrieved successfully',
            ]);
    }

    /** @test */
    public function it_can_update_operating_hours()
    {
        $hours = [
            'monday' => ['open' => '08:00', 'close' => '21:00'],
            'tuesday' => ['open' => '08:00', 'close' => '21:00'],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurant-branches/{$this->branch->id}/operating-hours", [
                'operating_hours' => $hours,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Operating hours updated successfully',
            ]);

        // Verify operating hours were updated
        $this->branch->refresh();
        $this->assertEquals('08:00', $this->branch->operating_hours['monday']['open']);
        $this->assertEquals('21:00', $this->branch->operating_hours['monday']['close']);
    }

    /** @test */
    public function it_validates_operating_hours_format()
    {
        $invalidHours = [
            'monday' => ['open' => '25:00', 'close' => '26:00'],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurant-branches/{$this->branch->id}/operating-hours", [
                'operating_hours' => $invalidHours,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operating_hours.monday.open', 'operating_hours.monday.close']);
    }

    /** @test */
    public function it_can_configure_loyalty_program()
    {
        $settings = [
            'points_per_currency' => 2,
            'currency_per_point' => 0.02,
            'tier_thresholds' => [
                'bronze' => 0,
                'silver' => 200,
                'gold' => 1000,
                'platinum' => 2000,
            ],
            'spin_wheel_probabilities' => [
                'points_10' => 0.5,
                'points_25' => 0.3,
                'points_50' => 0.15,
                'points_100' => 0.05,
            ],
            'stamp_card_requirements' => [
                'stamps_needed' => 15,
                'reward_value' => 10.00,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/loyalty-program", $settings);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Loyalty program configured successfully',
            ]);

        // Verify configs were created
        $this->assertDatabaseHas('restaurant_configs', [
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'loyalty_points_per_currency',
            'config_value' => '2',
        ]);
    }

    /** @test */
    public function it_validates_loyalty_program_settings()
    {
        $invalidSettings = [
            'points_per_currency' => -1, // Invalid: negative value
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/loyalty-program", $invalidSettings);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['points_per_currency']);
    }

    /** @test */
    public function it_validates_spin_wheel_probabilities_sum()
    {
        $invalidSettings = [
            'spin_wheel_probabilities' => [
                'points_10' => 0.5,
                'points_25' => 0.3,
                'points_50' => 0.1,
                'points_100' => 0.3, // Sum = 1.2, should be 1.0
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/loyalty-program", $invalidSettings);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spin_wheel_probabilities']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson("/api/v1/restaurants/{$this->restaurant->id}/config");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_encrypted_configs_properly()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/config", [
                'key' => 'api_secret',
                'value' => 'secret_key_123',
            ]);

        $response->assertOk();

        // Verify config was encrypted
        $config = RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'api_secret')
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->is_encrypted);
        $this->assertTrue($config->is_sensitive);
        $this->assertNotEquals('secret_key_123', $config->config_value);
    }

    /** @test */
    public function it_caches_configuration_data()
    {
        // Create a config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        // First call
        $response1 = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=test_key");

        $response1->assertOk();

        // Update config directly in database
        RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'test_key')
            ->update(['config_value' => 'updated_value']);

        // Second call should return cached value
        $response2 = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=test_key");

        $response2->assertOk()
            ->assertJson([
                'data' => 'test_value', // Should return cached value
            ]);
    }

    /** @test */
    public function it_clears_cache_when_config_is_updated()
    {
        // Create a config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        // First call to cache
        $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=test_key");

        // Update config through API
        $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/config", [
                'key' => 'test_key',
                'value' => 'updated_value',
            ]);

        // Get config again
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=test_key");

        $response->assertOk()
            ->assertJson([
                'data' => 'updated_value',
            ]);
    }

    /** @test */
    public function it_handles_different_data_types()
    {
        // Test integer
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/config", [
                'key' => 'int_key',
                'value' => 42,
            ]);

        $response->assertOk();

        $getResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=int_key");

        $getResponse->assertOk()
            ->assertJson([
                'data' => 42,
            ]);

        // Test array
        $arrayValue = ['key' => 'value', 'number' => 123];
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/config", [
                'key' => 'array_key',
                'value' => $arrayValue,
            ]);

        $response->assertOk();

        $getResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/restaurants/{$this->restaurant->id}/config?key=array_key");

        $getResponse->assertOk()
            ->assertJson([
                'data' => $arrayValue,
            ]);
    }
} 