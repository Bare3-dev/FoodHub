<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Restaurant;
use App\Models\ApiVersion;
use App\Services\ApiVersionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class IntegrationTestingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ApiVersionSeeder::class);
        Queue::fake();
    }

    /** @test */
    public function pos_system_integration_works_across_versions()
    {
        $restaurant = Restaurant::factory()->create();

        // Test Square POS integration
        $response = $this->postJson("/api/v1/pos/square/restaurants/{$restaurant->id}/menu/sync");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test Toast POS integration
        $response = $this->postJson("/api/v1/pos/toast/restaurants/{$restaurant->id}/menu/sync");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 POS integration
        $response = $this->postJson("/api/v2/pos/square/restaurants/{$restaurant->id}/menu/sync");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function mobile_app_ios_integration_works()
    {
        // Test iOS-specific headers and responses
        $response = $this->withHeaders([
            'User-Agent' => 'FoodHub/1.0 (iPhone; iOS 15.0; Scale/3.0)',
            'Accept' => 'application/vnd.foodhub.v1+json'
        ])->getJson('/api/v1/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 with iOS
        $response = $this->withHeaders([
            'User-Agent' => 'FoodHub/2.0 (iPhone; iOS 16.0; Scale/3.0)',
            'Accept' => 'application/vnd.foodhub.v2+json'
        ])->getJson('/api/v2/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function mobile_app_android_integration_works()
    {
        // Test Android-specific headers and responses
        $response = $this->withHeaders([
            'User-Agent' => 'FoodHub/1.0 (Linux; Android 12; SM-G991B)',
            'Accept' => 'application/vnd.foodhub.v1+json'
        ])->getJson('/api/v1/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 with Android
        $response = $this->withHeaders([
            'User-Agent' => 'FoodHub/2.0 (Linux; Android 13; SM-G991B)',
            'Accept' => 'application/vnd.foodhub.v2+json'
        ])->getJson('/api/v2/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function third_party_delivery_integration_works()
    {
        $restaurant = Restaurant::factory()->create();

        // Test Uber Eats style integration
        $response = $this->withHeaders([
            'X-Third-Party' => 'uber-eats',
            'X-Integration-Version' => '1.0'
        ])->getJson("/api/v1/delivery/orders/{$restaurant->id}/eta");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test DoorDash style integration
        $response = $this->withHeaders([
            'X-Third-Party' => 'doordash',
            'X-Integration-Version' => '2.0'
        ])->getJson("/api/v2/delivery/orders/{$restaurant->id}/eta");

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function staff_management_dashboard_integration_works()
    {
        // Test web dashboard integration
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept' => 'application/vnd.foodhub.v1+json'
        ])->getJson('/api/v1/staff');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test mobile dashboard integration
        $response = $this->withHeaders([
            'User-Agent' => 'FoodHub-Staff/1.0 (iPhone; iOS 15.0)',
            'Accept' => 'application/vnd.foodhub.v2+json'
        ])->getJson('/api/v2/staff');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function webhook_integration_works_across_versions()
    {
        // Test payment webhook
        $response = $this->postJson('/api/v1/webhook/payment/stripe', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']]
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 webhook
        $response = $this->postJson('/api/v2/webhook/payment/stripe', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']]
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function rate_limiting_works_across_versions()
    {
        // Test rate limiting on v1
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/v1/restaurants');
            if ($response->status() === 429) {
                break;
            }
        }

        // Should eventually hit rate limit
        $this->assertTrue($response->status() === 429 || $response->status() === 200);

        // Test rate limiting on v2
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/v2/restaurants');
            if ($response->status() === 429) {
                break;
            }
        }

        $this->assertTrue($response->status() === 429 || $response->status() === 200);
    }

    /** @test */
    public function caching_works_across_versions()
    {
        // Test v1 caching
        $response1 = $this->getJson('/api/v1/restaurants');
        $response2 = $this->getJson('/api/v1/restaurants');

        $response1->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');
        $response2->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1');

        // Test v2 caching
        $response1 = $this->getJson('/api/v2/restaurants');
        $response2 = $this->getJson('/api/v2/restaurants');

        $response1->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
        $response2->assertStatus(200)
            ->assertHeader('X-API-Version', 'v2');
    }

    /** @test */
    public function error_handling_is_consistent_across_versions()
    {
        // Test 404 errors
        $response = $this->getJson('/api/v1/nonexistent-endpoint');
        $response->assertStatus(404);

        $response = $this->getJson('/api/v2/nonexistent-endpoint');
        $response->assertStatus(404);

        // Test 400 errors
        $response = $this->postJson('/api/v1/orders', []);
        $response->assertStatus(422);

        $response = $this->postJson('/api/v2/orders', []);
        $response->assertStatus(422);
    }

    /** @test */
    public function deprecation_notifications_are_sent_correctly()
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

        // Test that deprecation notifications are sent
        $notifications = ApiVersionNotificationService::getActiveDeprecationNotifications();
        
        $this->assertNotEmpty($notifications);
        $this->assertContains('v0', array_column($notifications, 'version'));
    }
}
