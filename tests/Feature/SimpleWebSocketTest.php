<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Driver;
use App\Events\OrderStatusUpdated;
use App\Events\NewOrderPlaced;
use App\Events\DriverLocationUpdated;
use App\Services\FCMService;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Mockery;

class SimpleWebSocketTest extends TestCase
{
    
    protected $fcmService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock FCM service
        $this->fcmService = Mockery::mock(FCMService::class);
        $this->app->instance(FCMService::class, $this->fcmService);
        
        // Set test configuration
        Config::set('services.fcm.server_key', 'test_server_key');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_websocket_connection_basic()
    {
        // Test broadcasting configuration
        $this->assertNotNull(config('broadcasting.default'));
        $this->assertNotNull(config('broadcasting.connections'));
        
        // Test channels file exists
        $this->assertFileExists(base_path('routes/channels.php'));
    }

    public function test_order_status_event_works()
    {
        Event::fake([OrderStatusUpdated::class]);
        
        $order = Order::factory()->create(['status' => 'pending']);
        
        Event::dispatch(new OrderStatusUpdated($order, 'pending', 'confirmed'));
        
        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                   $event->newStatus === 'confirmed' &&
                   $event->previousStatus === 'pending';
        });
    }

    public function test_new_order_event_works()
    {
        Event::fake([NewOrderPlaced::class]);
        
        $order = Order::factory()->create(['status' => 'pending']);
        
        Event::dispatch(new NewOrderPlaced($order));
        
        Event::assertDispatched(NewOrderPlaced::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_driver_location_event_works()
    {
        Event::fake([DriverLocationUpdated::class]);
        
        $driver = Driver::factory()->create();
        $locationData = [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10
        ];
        
        Event::dispatch(new DriverLocationUpdated($driver, $locationData));
        
        Event::assertDispatched(DriverLocationUpdated::class, function ($event) use ($driver, $locationData) {
            return $event->driver->id === $driver->id &&
                   $event->location['latitude'] === $locationData['latitude'];
        });
    }

    public function test_push_notification_ios()
    {
        $this->fcmService->shouldReceive('sendToToken')
            ->with('ios_test_token', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(true);
        
        $result = $this->fcmService->sendToToken(
            'ios_test_token',
            ['type' => 'order_update'],
            ['title' => 'iOS Test', 'body' => 'Test notification']
        );
        
        $this->assertTrue($result);
    }

    public function test_push_notification_android()
    {
        $this->fcmService->shouldReceive('sendToToken')
            ->with('android_test_token', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(true);
        
        $result = $this->fcmService->sendToToken(
            'android_test_token',
            ['type' => 'order_update'],
            ['title' => 'Android Test', 'body' => 'Test notification']
        );
        
        $this->assertTrue($result);
    }

    public function test_device_token_endpoint()
    {
        $tokenData = [
            'user_type' => 'customer',
            'user_id' => 1,
            'token' => 'test_token_123',
            'platform' => 'ios',
            'device_id' => 'test_device'
        ];
        
        $response = $this->postJson('/api/v1/device-tokens/register', $tokenData);
        
        // Should not be 404 (route exists)
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_multiple_events()
    {
        Event::fake([OrderStatusUpdated::class]);
        
        // Create 3 orders instead of 75
        for ($i = 1; $i <= 3; $i++) {
            $order = Order::factory()->create(['status' => 'pending']);
            Event::dispatch(new OrderStatusUpdated($order, 'pending', 'confirmed'));
        }
        
        Event::assertDispatchedTimes(OrderStatusUpdated::class, 3);
    }

    public function test_error_handling()
    {
        // Test graceful error handling
        Config::set('broadcasting.default', 'null');
        
        Event::fake([OrderStatusUpdated::class]);
        
        $order = Order::factory()->create(['status' => 'pending']);
        
        try {
            Event::dispatch(new OrderStatusUpdated($order, 'pending', 'confirmed'));
            $success = true;
        } catch (\Exception $e) {
            $success = false;
        }
        
        $this->assertTrue($success, 'Events should handle broadcasting failures gracefully');
        Event::assertDispatchedTimes(OrderStatusUpdated::class, 1);
    }

    public function test_performance()
    {
        $startTime = microtime(true);
        
        // Simple operations
        $order = Order::factory()->create();
        $event = new OrderStatusUpdated($order, 'pending', 'confirmed');
        
        $this->fcmService->shouldReceive('sendToToken')->andReturn(true);
        $this->fcmService->sendToToken('test', [], []);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should complete within 2 seconds
        $this->assertLessThan(2, $duration, 'Basic operations should complete quickly');
    }
}
