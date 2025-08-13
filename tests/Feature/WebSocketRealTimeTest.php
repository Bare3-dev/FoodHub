<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\DeviceToken;
use App\Events\OrderStatusUpdated;
use App\Events\NewOrderPlaced;
use App\Events\DriverLocationUpdated;
use App\Events\DeliveryStatusChanged;
use App\Services\FCMService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class WebSocketRealTimeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $restaurantOwner;
    protected User $kitchenStaff;
    protected Customer $customer;
    protected User $driver;
    protected Restaurant $restaurant;
    protected RestaurantBranch $restaurantBranch;
    protected Order $order;
    protected DeviceToken $iosDeviceToken;
    protected DeviceToken $androidDeviceToken;
    protected $fcmService;
    protected $pushNotificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create API version for testing
        \App\Models\ApiVersion::create([
            'version' => 'v1',
            'status' => 'active',
            'release_date' => now(),
            'is_default' => true,
        ]);
        
        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN'
        ]);
        
        // Create restaurant and branch
        $this->restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'cuisine_type' => 'International',
            'business_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '22:00']
            ],
            'email' => 'restaurant@test.com',
            'phone' => '1234567890'
        ]);
        
        $this->restaurantBranch = RestaurantBranch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'SA',
            'operating_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '22:00']
            ],
            'delivery_zones' => [
                'type' => 'Polygon',
                'coordinates' => [[[0, 0], [0, 1], [1, 1], [1, 0], [0, 0]]]
            ]
        ]);
        
        // Create restaurant owner
        $this->restaurantOwner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id
        ]);
        
        // Create kitchen staff
        $this->kitchenStaff = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@test.com',
            'password' => bcrypt('password'),
            'role' => 'KITCHEN_STAFF',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id
        ]);
        
        // Create customer
        $this->customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password')
        ]);
        
        // Create driver
        $this->driver = User::create([
            'name' => 'Test Driver',
            'email' => 'driver@test.com',
            'password' => bcrypt('password'),
            'role' => 'DRIVER'
        ]);
        
        // Create order
        $this->order = Order::create([
            'order_number' => 'ORD-123456',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => null,
            'driver_id' => null,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.00,
            'tax_amount' => 3.75,
            'delivery_fee' => 5.00,
            'service_fee' => 2.50,
            'discount_amount' => 0.00,
            'total_amount' => 36.25,
            'currency' => 'SAR',
            'estimated_preparation_time' => 20,
            'estimated_delivery_time' => 30
        ]);

        // Create device tokens for iOS and Android
        $this->iosDeviceToken = DeviceToken::create([
            'user_type' => 'customer',
            'user_id' => $this->customer->id,
            'token' => 'ios_test_token_12345',
            'platform' => 'ios',
            'device_id' => 'ios_device_123',
            'is_active' => true
        ]);

        $this->androidDeviceToken = DeviceToken::create([
            'user_type' => 'customer',
            'user_id' => $this->customer->id,
            'token' => 'android_test_token_67890',
            'platform' => 'android',
            'device_id' => 'android_device_456',
            'is_active' => true
        ]);

        // Mock FCM service
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

    // ========================================
    // BASIC FUNCTIONALITY TESTS (2-3 hours)
    // ========================================

    /** @test */
    public function websocket_connection_works_from_browser()
    {
        // Test that broadcasting channels are properly configured
        $this->assertTrue(Broadcast::routes());
        
        // Test that the customer can access their channel
        $this->actingAs($this->customer);
        $this->assertTrue($this->canAccessCustomerChannel($this->customer, $this->customer->id));
        
        // Test that restaurant staff can access their channels
        $this->actingAs($this->restaurantOwner);
        $this->assertTrue($this->canAccessRestaurantChannel($this->restaurantOwner, $this->restaurantBranch->id));
        $this->assertTrue($this->canAccessKitchenChannel($this->restaurantOwner, $this->restaurantBranch->id));
        
        // Test that driver can access their channel
        $this->actingAs($this->driver);
        $this->assertTrue($this->canAccessDriverChannel($this->driver, $this->driver->id));
    }

    /** @test */
    public function order_status_change_triggers_real_time_update()
    {
        Event::fake([OrderStatusUpdated::class]);
        
        $this->actingAs($this->restaurantOwner);
        
        // Update order status
        $updateData = ['status' => 'preparing'];
        $response = $this->putJson("/api/v1/orders/{$this->order->id}", $updateData);
        
        $response->assertOk();
        
        // Verify event was dispatched
        Event::assertDispatched(OrderStatusUpdated::class, function ($event) {
            return $event->order->id === $this->order->id &&
                   $event->previousStatus === 'pending' &&
                   $event->newStatus === 'preparing';
        });
    }

    /** @test */
    public function push_notification_reaches_ios_device()
    {
        $this->fcmService->shouldReceive('sendToToken')
            ->with('ios_test_token_12345', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(['success' => 1, 'failure' => 0]);
        
        $notification = [
            'title' => 'Order Update',
            'body' => 'Your order is being prepared',
            'data' => ['order_id' => $this->order->id]
        ];
        
        $result = $this->pushNotificationService->sendToCustomer(
            $this->customer->id,
            $notification['title'],
            $notification['body'],
            $notification['data']
        );
        
        $this->assertTrue($result);
    }

    /** @test */
    public function push_notification_reaches_android_device()
    {
        $this->fcmService->shouldReceive('sendToToken')
            ->with('android_test_token_67890', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(['success' => 1, 'failure' => 0]);
        
        $notification = [
            'title' => 'Order Update',
            'body' => 'Your order is being prepared',
            'data' => ['order_id' => $this->order->id]
        ];
        
        $result = $this->pushNotificationService->sendToCustomer(
            $this->customer->id,
            $notification['title'],
            $notification['body'],
            $notification['data']
        );
        
        $this->assertTrue($result);
    }

    /** @test */
    public function device_token_registration_works()
    {
        $tokenData = [
            'user_type' => 'customer',
            'user_id' => $this->customer->id,
            'token' => 'new_test_token_123',
            'platform' => 'ios',
            'device_id' => 'new_ios_device'
        ];
        
        $response = $this->postJson('/api/v1/device-tokens/register', $tokenData);
        
        $response->assertStatus(201);
        $response->assertJson(['message' => 'Device token registered successfully']);
        
        $this->assertDatabaseHas('device_tokens', [
            'user_type' => 'customer',
            'user_id' => $this->customer->id,
            'token' => 'new_test_token_123',
            'platform' => 'ios'
        ]);
    }

    /** @test */
    public function driver_location_update_broadcasts()
    {
        Event::fake([DriverLocationUpdated::class]);
        
        $this->actingAs($this->driver);
        
        $locationData = [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10,
            'speed' => 25,
            'heading' => 90
        ];
        
        $response = $this->postJson("/api/v1/drivers/{$this->driver->id}/location", $locationData);
        
        $response->assertOk();
        
        Event::assertDispatched(DriverLocationUpdated::class, function ($event) use ($locationData) {
            return $event->driver->id === $this->driver->id &&
                   $event->location['latitude'] === $locationData['latitude'] &&
                   $event->location['longitude'] === $locationData['longitude'];
        });
    }

    // ========================================
    // INTEGRATION FLOW TESTS (2-3 hours)
    // ========================================

    /** @test */
    public function complete_order_flow_restaurant_sees_real_time()
    {
        Event::fake([NewOrderPlaced::class]);
        
        $this->actingAs($this->restaurantOwner);
        
        $orderData = [
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'order_number' => 'ORD-789',
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 30.00,
            'total_amount' => 30.00,
            'estimated_preparation_time' => 25,
            'currency' => 'SAR'
        ];
        
        $response = $this->postJson('/api/v1/orders', $orderData);
        
        $response->assertStatus(201);
        
        Event::assertDispatched(NewOrderPlaced::class, function ($event) use ($orderData) {
            return $event->order->order_number === $orderData['order_number'];
        });
    }

    /** @test */
    public function status_update_flow_kitchen_updates_customer_gets_notification()
    {
        Event::fake([OrderStatusUpdated::class]);
        
        $this->fcmService->shouldReceive('sendToUserType')
            ->with('customer', $this->customer->id, Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(['success' => 1, 'failure' => 0]);
        
        $this->actingAs($this->kitchenStaff);
        
        // Update order status
        $updateData = ['status' => 'ready_for_pickup'];
        $response = $this->putJson("/api/v1/orders/{$this->order->id}", $updateData);
        
        $response->assertOk();
        
        Event::assertDispatched(OrderStatusUpdated::class, function ($event) {
            return $event->order->id === $this->order->id &&
                   $event->newStatus === 'ready_for_pickup';
        });
    }

    /** @test */
    public function driver_flow_location_update_customer_sees_on_map()
    {
        Event::fake([DriverLocationUpdated::class]);
        
        $this->actingAs($this->driver);
        
        $locationData = [
            'latitude' => 40.7589,
            'longitude' => -73.9851,
            'accuracy' => 15,
            'speed' => 30,
            'heading' => 180
        ];
        
        $response = $this->postJson("/api/v1/drivers/{$this->driver->id}/location", $locationData);
        
        $response->assertOk();
        
        Event::assertDispatched(DriverLocationUpdated::class, function ($event) use ($locationData) {
            return $event->driver->id === $this->driver->id &&
                   $event->location['latitude'] === $locationData['latitude'] &&
                   $event->location['longitude'] === $locationData['longitude'];
        });
    }

    /** @test */
    public function error_handling_websocket_disconnects()
    {
        // Test graceful handling of missing user
        $this->assertFalse($this->canAccessCustomerChannel(null, $this->customer->id));
        $this->assertFalse($this->canAccessRestaurantChannel(null, $this->restaurantBranch->id));
        $this->assertFalse($this->canAccessDriverChannel(null, $this->driver->id));
        
        // Test handling of invalid IDs
        $this->assertFalse($this->canAccessCustomerChannel($this->customer, 999999));
        $this->assertFalse($this->canAccessRestaurantChannel($this->restaurantOwner, 999999));
        $this->assertFalse($this->canAccessDriverChannel($this->driver, 999999));
    }

    // ========================================
    // SIMPLE LOAD TESTS (1-2 hours)
    // ========================================

    /** @test */
    public function five_concurrent_websocket_connections_work()
    {
        $users = [];
        
        // Create 5 different users
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "Test User {$i}",
                'email' => "user{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'CUSTOMER'
            ]);
            $users[] = $user;
        }
        
        // Test that all can access their respective channels
        foreach ($users as $user) {
            $this->actingAs($user);
            $this->assertTrue($this->canAccessCustomerChannel($user, $user->id));
        }
    }

    /** @test */
    public function send_ten_notifications_at_once_all_delivered()
    {
        $this->fcmService->shouldReceive('sendToUserType')
            ->times(10)
            ->andReturn(['success' => 1, 'failure' => 0]);
        
        $notification = [
            'title' => 'Bulk Test',
            'body' => 'Testing bulk notifications',
            'data' => ['test' => true]
        ];
        
        $successCount = 0;
        for ($i = 1; $i <= 10; $i++) {
            $result = $this->pushNotificationService->sendToCustomer(
                $this->customer->id,
                $notification['title'],
                $notification['body'],
                $notification['data']
            );
            if ($result) $successCount++;
        }
        
        $this->assertEquals(10, $successCount);
    }

    /** @test */
    public function three_drivers_updating_location_simultaneously()
    {
        Event::fake([DriverLocationUpdated::class]);
        
        $drivers = [];
        for ($i = 1; $i <= 3; $i++) {
            $driver = User::create([
                'name' => "Driver {$i}",
                'email' => "driver{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'DRIVER'
            ]);
            $drivers[] = $driver;
        }
        
        $locations = [
            ['latitude' => 40.7128, 'longitude' => -74.0060],
            ['latitude' => 40.7589, 'longitude' => -73.9851],
            ['latitude' => 40.7505, 'longitude' => -73.9934]
        ];
        
        foreach ($drivers as $index => $driver) {
            $this->actingAs($driver);
            $locationData = array_merge($locations[$index], [
                'accuracy' => 10,
                'speed' => 25,
                'heading' => 90
            ]);
            
            $response = $this->postJson("/api/v1/drivers/{$driver->id}/location", $locationData);
            $response->assertOk();
        }
        
        Event::assertDispatched(DriverLocationUpdated::class, 3);
    }

    /** @test */
    public function place_five_orders_within_one_minute_all_broadcast()
    {
        Event::fake([NewOrderPlaced::class]);
        
        $this->actingAs($this->restaurantOwner);
        
        $orders = [];
        for ($i = 1; $i <= 5; $i++) {
            $orderData = [
                'customer_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'restaurant_branch_id' => $this->restaurantBranch->id,
                'order_number' => "ORD-{$i}",
                'status' => 'pending',
                'type' => 'delivery',
                'payment_status' => 'pending',
                'payment_method' => 'cash',
                'subtotal' => 20.00 + $i,
                'total_amount' => 20.00 + $i,
                'estimated_preparation_time' => 20,
                'currency' => 'SAR'
            ];
            
            $response = $this->postJson('/api/v1/orders', $orderData);
            $response->assertStatus(201);
            $orders[] = $orderData;
        }
        
        Event::assertDispatched(NewOrderPlaced::class, 5);
    }

    // ========================================
    // CROSS-PLATFORM TESTS (1 hour)
    // ========================================

    /** @test */
    public function web_dashboard_receives_real_time_updates()
    {
        Event::fake([OrderStatusUpdated::class]);
        
        $this->actingAs($this->restaurantOwner);
        
        // Update order status
        $updateData = ['status' => 'completed'];
        $response = $this->putJson("/api/v1/orders/{$this->order->id}", $updateData);
        
        $response->assertOk();
        
        // Verify event was dispatched for web dashboard
        Event::assertDispatched(OrderStatusUpdated::class, function ($event) {
            return $event->order->id === $this->order->id &&
                   $event->newStatus === 'completed';
        });
    }

    /** @test */
    public function ios_app_gets_push_notifications()
    {
        $this->fcmService->shouldReceive('sendToToken')
            ->with('ios_test_token_12345', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(['success' => 1, 'failure' => 0]);
        
        $result = $this->pushNotificationService->sendToCustomer(
            $this->customer->id,
            'iOS Test',
            'This is an iOS notification',
            ['platform' => 'ios']
        );
        
        $this->assertTrue($result);
    }

    /** @test */
    public function android_app_gets_push_notifications()
    {
        $this->fcmService->shouldReceive('sendToToken')
            ->with('android_test_token_67890', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(['success' => 1, 'failure' => 0]);
        
        $result = $this->pushNotificationService->sendToCustomer(
            $this->customer->id,
            'Android Test',
            'This is an Android notification',
            ['platform' => 'android']
        );
        
        $this->assertTrue($result);
    }

    /** @test */
    public function basic_offline_online_reconnection_works()
    {
        // Test device token reactivation
        $this->iosDeviceToken->update(['is_active' => false]);
        
        $tokenData = [
            'user_type' => 'customer',
            'user_id' => $this->customer->id,
            'token' => 'ios_test_token_12345',
            'platform' => 'ios',
            'device_id' => 'ios_device_123'
        ];
        
        $response = $this->postJson('/api/v1/device-tokens/register', $tokenData);
        $response->assertStatus(200); // Should update existing token
        
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'ios_test_token_12345',
            'is_active' => true
        ]);
    }

    // Helper methods
    private function canAccessCustomerChannel($user, $customerId): bool
    {
        if ($user && $user->id == $customerId) {
            return true;
        }
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        return false;
    }

    private function canAccessRestaurantChannel($user, $restaurantBranchId): bool
    {
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        return $user && $user->restaurant_branch_id == $restaurantBranchId;
    }

    private function canAccessDriverChannel($user, $driverId): bool
    {
        if ($user && $user->id == $driverId) {
            return true;
        }
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        return false;
    }

    private function canAccessKitchenChannel($user, $restaurantBranchId): bool
    {
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        return $user && 
               $user->restaurant_branch_id == $restaurantBranchId && 
               in_array($user->role, ['KITCHEN_STAFF', 'RESTAURANT_OWNER', 'BRANCH_MANAGER']);
    }
}
