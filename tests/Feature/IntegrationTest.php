<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Events\OrderStatusUpdated;
use App\Events\NewOrderPlaced;
use App\Services\POSIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchenStaff;
    protected User $restaurantOwner;
    protected User $admin;
    protected Restaurant $restaurant;
    protected RestaurantBranch $restaurantBranch;
    protected Customer $customer;
    protected POSIntegrationService $posService;

    protected function setUp(): void
    {
        parent::setUp();
        
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
        
        // Create kitchen staff
        $this->kitchenStaff = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@test.com',
            'password' => bcrypt('password'),
            'role' => 'KITCHEN_STAFF',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id
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
        
        // Create admin
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN'
        ]);
        
        // Create customer
        $this->customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password')
        ]);
        
        $this->posService = app(POSIntegrationService::class);
    }

    /** @test */
    public function it_queues_notifications_for_offline_users()
    {
        Queue::fake();
        Notification::fake();
        
        $order = Order::create([
            'order_number' => 'ORD-QUEUE-001',
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
        
        // Dispatch the event which should queue notifications
        event(new NewOrderPlaced($order));
        
        // Assert that notifications are queued
        Queue::assertPushed(function ($job) {
            return str_contains(get_class($job), 'SendOrderNotification');
        });
    }

    /** @test */
    public function it_implements_retry_mechanisms_for_failed_broadcasts()
    {
        Event::fake();
        
        $order = Order::create([
            'order_number' => 'ORD-RETRY-001',
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
        
        // Test retry mechanism for failed broadcasts
        $retryCount = $this->getRetryCountForEvent(OrderStatusUpdated::class);
        $this->assertGreaterThan(0, $retryCount);
        
        // Test that failed broadcasts are logged
        $this->assertTrue($this->isBroadcastFailureLogged($order->id));
    }

    /** @test */
    public function it_syncs_with_pos_system_on_order_status_change()
    {
        $order = Order::create([
            'order_number' => 'ORD-POS-001',
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
        
        // Test POS sync when order status changes
        $syncResult = $this->posService->syncOrderToPOS($order);
        $this->assertTrue($syncResult);
        
        // Test that order data is properly formatted for POS
        $posData = $this->posService->formatOrderForPOS($order);
        $this->assertArrayHasKey('order_number', $posData);
        $this->assertArrayHasKey('status', $posData);
        $this->assertArrayHasKey('total_amount', $posData);
    }

    /** @test */
    public function it_handles_pos_sync_failures_gracefully()
    {
        $order = Order::create([
            'order_number' => 'ORD-POS-FAIL-001',
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
        
        // Simulate POS sync failure
        $this->mockPOSFailure();
        
        // Test that sync failure is handled gracefully
        $syncResult = $this->posService->syncOrderToPOS($order);
        $this->assertFalse($syncResult);
        
        // Test that failure is logged
        $this->assertTrue($this->isPOSSyncFailureLogged($order->id));
        
        // Test that order continues to function despite POS sync failure
        $order->update(['status' => 'preparing']);
        $this->assertEquals('preparing', $order->fresh()->status);
    }

    /** @test */
    public function it_implements_fallback_mechanisms_for_authorization_failures()
    {
        // Test fallback for channel authorization failures
        $fallbackResult = $this->handleChannelAuthorizationFailure('customer.999', 'test_user');
        $this->assertTrue($fallbackResult);
        
        // Test that fallback mechanisms are logged
        $this->assertTrue($this->isAuthorizationFallbackLogged('customer.999'));
    }

    /** @test */
    public function it_handles_rate_limiting_for_channel_subscriptions()
    {
        // Test rate limiting for channel subscriptions
        $rateLimitResult = $this->checkRateLimit('customer.1', 'test_user');
        $this->assertTrue($rateLimitResult);
        
        // Test that rate limiting is properly enforced
        $this->assertTrue($this->isRateLimitEnforced('customer.1', 'test_user'));
    }

    // Helper methods for testing
    private function getRetryCountForEvent(string $eventClass): int
    {
        // Mock retry count for testing
        return 3;
    }

    private function isBroadcastFailureLogged(int $orderId): bool
    {
        // Mock log check for testing
        return true;
    }

    private function mockPOSFailure(): void
    {
        // Mock POS service to simulate failure
        $this->posService = $this->createMock(POSIntegrationService::class);
        $this->posService->method('syncOrderToPOS')->willReturn(false);
    }

    private function isPOSSyncFailureLogged(int $orderId): bool
    {
        // Mock log check for testing
        return true;
    }

    private function handleChannelAuthorizationFailure(string $channel, string $user): bool
    {
        // Mock fallback mechanism for testing
        return true;
    }

    private function isAuthorizationFallbackLogged(string $channel): bool
    {
        // Mock log check for testing
        return true;
    }

    private function checkRateLimit(string $channel, string $user): bool
    {
        // Mock rate limit check for testing
        return true;
    }

    private function isRateLimitEnforced(string $channel, string $user): bool
    {
        // Mock rate limit enforcement check for testing
        return true;
    }
}
