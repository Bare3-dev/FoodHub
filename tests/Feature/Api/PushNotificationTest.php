<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\FCMService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $fcmService;
    private $pushNotificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock FCM service to avoid actual HTTP calls during testing
        $this->fcmService = Mockery::mock(FCMService::class);
        $this->app->instance(FCMService::class, $this->fcmService);
        
        $this->pushNotificationService = app(PushNotificationService::class);
        
        // Set FCM config for testing
        Config::set('services.fcm.server_key', 'test_server_key');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_register_device_token()
    {
        $customer = Customer::factory()->create();
        
        $tokenData = [
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'token' => $this->faker->regexify('[A-Za-z0-9]{152}'),
            'platform' => 'ios',
        ];

        $response = $this->postJson('/api/v1/device-tokens/register', $tokenData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Device token registered successfully',
            ]);

        $this->assertDatabaseHas('device_tokens', [
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'token' => $tokenData['token'],
            'platform' => 'ios',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_update_existing_device_token()
    {
        $customer = Customer::factory()->create();
        $driver = Driver::factory()->create();
        
        $token = $this->faker->regexify('[A-Za-z0-9]{152}');
        
        // Create initial token for customer
        DeviceToken::factory()->create([
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'token' => $token,
            'platform' => 'ios',
        ]);

        // Update token for driver
        $updateData = [
            'user_type' => 'driver',
            'user_id' => $driver->id,
            'token' => $token,
            'platform' => 'android',
        ];

        $response = $this->postJson('/api/v1/device-tokens/register', $updateData);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('device_tokens', [
            'user_type' => 'driver',
            'user_id' => $driver->id,
            'token' => $token,
            'platform' => 'android',
        ]);
        
        // Old customer token should be updated, not duplicated
        $this->assertDatabaseCount('device_tokens', 1);
    }

    /** @test */
    public function it_can_remove_device_token()
    {
        $customer = Customer::factory()->create();
        $deviceToken = DeviceToken::factory()->create([
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'is_active' => true,
        ]);

        $removeData = [
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'token' => $deviceToken->token,
        ];

        $response = $this->postJson('/api/v1/device-tokens/remove', $removeData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Device token removed successfully',
            ]);

        $this->assertDatabaseHas('device_tokens', [
            'id' => $deviceToken->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_can_get_user_tokens()
    {
        $customer = Customer::factory()->create();
        DeviceToken::factory()->count(3)->create([
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/device-tokens/user?user_type=customer&user_id={$customer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3,
                ],
            ]);
    }

    /** @test */
    public function it_can_validate_device_token()
    {
        $token = $this->faker->regexify('[A-Za-z0-9]{152}');
        
        $this->fcmService->shouldReceive('validateToken')
            ->with($token)
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/v1/device-tokens/validate', ['token' => $token]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_valid' => true,
                ],
            ]);
    }

    /** @test */
    public function it_can_send_test_notification()
    {
        $customer = Customer::factory()->create();
        DeviceToken::factory()->create([
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'is_active' => true,
        ]);

        $this->fcmService->shouldReceive('sendToUserType')
            ->with('customer', $customer->id, Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/v1/device-tokens/test', [
            'user_type' => 'customer',
            'user_id' => $customer->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Test notification sent successfully',
            ]);
    }

    /** @test */
    public function it_validates_required_fields_for_registration()
    {
        $response = $this->postJson('/api/v1/device-tokens/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_type', 'user_id', 'token', 'platform']);
    }

    /** @test */
    public function it_validates_user_type_values()
    {
        $response = $this->postJson('/api/v1/device-tokens/register', [
            'user_type' => 'invalid_type',
            'user_id' => 1,
            'token' => $this->faker->regexify('[A-Za-z0-9]{152}'),
            'platform' => 'ios',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_type']);
    }

    /** @test */
    public function it_validates_platform_values()
    {
        $response = $this->postJson('/api/v1/device-tokens/register', [
            'user_type' => 'customer',
            'user_id' => 1,
            'token' => $this->faker->regexify('[A-Za-z0-9]{152}'),
            'platform' => 'invalid_platform',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    /** @test */
    public function it_validates_token_length()
    {
        $response = $this->postJson('/api/v1/device-tokens/register', [
            'user_type' => 'customer',
            'user_id' => 1,
            'token' => 'short',
            'platform' => 'ios',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    /** @test */
    public function it_handles_missing_device_tokens_for_test_notification()
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/device-tokens/test', [
            'user_type' => 'customer',
            'user_id' => $customer->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No active device tokens found for this user',
            ]);
    }

    /** @test */
    public function it_handles_fcm_service_failures_gracefully()
    {
        $customer = Customer::factory()->create();
        DeviceToken::factory()->create([
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'is_active' => true,
        ]);

        $this->fcmService->shouldReceive('sendToUserType')
            ->with('customer', $customer->id, Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(false);

        $response = $this->postJson('/api/v1/device-tokens/test', [
            'user_type' => 'customer',
            'user_id' => $customer->id,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to send test notification',
            ]);
    }
}
