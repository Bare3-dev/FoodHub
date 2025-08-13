<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\Customer;
use App\Models\ApiVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class CompatibilityTestingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ApiVersionSeeder::class);
    }

    /** @test */
    public function order_processing_flow_works_across_versions()
    {
        // Test v1 order processing
        $response = $this->postJson('/api/v1/orders', [
            'restaurant_id' => Restaurant::factory()->create()->id,
            'items' => [
                ['menu_item_id' => 1, 'quantity' => 2, 'price' => 10.00]
            ],
            'customer_id' => Customer::factory()->create()->id,
            'total_amount' => 20.00
        ]);

        $response->assertStatus(201)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 order processing (when implemented)
        $response = $this->postJson('/api/v2/orders', [
            'restaurant_id' => Restaurant::factory()->create()->id,
            'items' => [
                ['menu_item_id' => 1, 'quantity' => 2, 'price' => 10.00]
            ],
            'customer_id' => Customer::factory()->create()->id,
            'total_amount' => 20.00
        ]);

        // v2 should work or provide appropriate migration guidance
        $response->assertStatus(201)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function staff_authentication_flow_works_across_versions()
    {
        $user = User::factory()->create(['role' => 'CASHIER']);

        // Test v1 authentication
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1')
            ->assertJsonStructure(['token']);

        // Test v2 authentication
        $response = $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function customer_loyalty_points_flow_works_across_versions()
    {
        $customer = Customer::factory()->create();
        $restaurant = Restaurant::factory()->create();

        // Test v1 loyalty points
        $response = $this->postJson('/api/v1/customer-loyalty-points/earn-points', [
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'points' => 100,
            'reason' => 'purchase'
        ]);

        $response->assertStatus(201)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 loyalty points
        $response = $this->postJson('/api/v2/customer-loyalty-points/earn-points', [
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'points' => 100,
            'reason' => 'purchase'
        ]);

        $response->assertStatus(201)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function restaurant_pos_integration_works_across_versions()
    {
        $restaurant = Restaurant::factory()->create();

        // Test v1 POS integration
        $response = $this->getJson("/api/v1/pos/status/{$restaurant->id}");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 POS integration
        $response = $this->getJson("/api/v2/pos/status/{$restaurant->id}");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function mobile_app_endpoints_work_across_versions()
    {
        // Test mobile app specific endpoints
        $response = $this->getJson('/api/v1/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1')
            ->assertJsonStructure(['data']);

        $response = $this->getJson('/api/v2/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function third_party_delivery_integration_works_across_versions()
    {
        $restaurant = Restaurant::factory()->create();

        // Test v1 delivery endpoints
        $response = $this->getJson("/api/v1/delivery/orders/{$restaurant->id}/eta");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 delivery endpoints
        $response = $this->getJson("/api/v2/delivery/orders/{$restaurant->id}/eta");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function staff_management_dashboard_works_across_versions()
    {
        $user = User::factory()->create(['role' => 'SUPER_ADMIN']);
        Sanctum::actingAs($user);

        // Test v1 staff management
        $response = $this->getJson('/api/v1/staff');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 staff management
        $response = $this->getJson('/api/v2/staff');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function backward_compatibility_is_maintained_for_critical_endpoints()
    {
        // Test that legacy endpoints still work
        $response = $this->getJson('/api/restaurants');

        $response->assertStatus(200);

        // Test that versioned endpoints work
        $response = $this->getJson('/api/v1/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');
    }

    /** @test */
    public function deprecation_warnings_are_provided_for_legacy_endpoints()
    {
        // Create a deprecated version
        $deprecatedVersion = ApiVersion::create([
            'version' => 'v0',
            'status' => ApiVersion::STATUS_DEPRECATED,
            'release_date' => now()->subYear(),
            'sunset_date' => now()->addMonths(6),
            'migration_guide_url' => 'https://example.com/migration',
            'breaking_changes' => [],
            'is_default' => false
        ]);

        // Test that deprecation warnings are included
        $response = $this->getJson('/api/v0/restaurants');

        $response->assertHeader('Deprecation', 'true')
            ->assertHeader('Sunset');
    }
}
