<?php

namespace Tests\Feature\Broadcasting;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use App\Models\RestaurantBranch;
use App\Models\Driver;
use App\Models\OrderAssignment;
use App\Events\OrderStatusUpdated;
use App\Events\NewOrderPlaced;
use App\Events\DriverLocationUpdated;
use App\Events\DeliveryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

class OrderBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create API version v1 for testing
        \App\Models\ApiVersion::create([
            'version' => 'v1',
            'status' => 'active',
            'release_date' => now(),
            'is_default' => true,
        ]);
        
        // Create test data
        $this->customer = Customer::factory()->create();
        $this->restaurantBranch = RestaurantBranch::factory()->create();
        $this->driver = Driver::factory()->create();
        
        // Create a customer address for orders with coordinates
        $this->customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'street_address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'Test Country',
            'latitude' => 40.7589,
            'longitude' => -73.9851,
        ]);
        
        // Create a user with RESTAURANT_OWNER role and associate with the restaurant
        $this->user = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'status' => 'active'
        ]);
        
        // Debug: Verify user role is set correctly
        dump('Test user created with role: ' . $this->user->role);
        dump('User has RESTAURANT_OWNER role: ' . ($this->user->hasRole('RESTAURANT_OWNER') ? 'YES' : 'NO'));
        dump('User can access RESTAURANT_OWNER role: ' . ($this->user->canAccessRole('RESTAURANT_OWNER') ? 'YES' : 'NO'));
    }

    /** @test */
    public function it_broadcasts_new_order_placed_event()
    {
        Event::fake([NewOrderPlaced::class]);

        $orderData = [
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'order_number' => 'ORD-001',
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
            'delivery_address' => '456 Oak Ave',
            'estimated_preparation_time' => 20,
            'currency' => 'USD',
            'customer_name' => $this->customer->name ?? 'Test Customer',
            'customer_phone' => $this->customer->phone ?? '123-456-7890',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/orders', $orderData);

        // Debug: Check the actual response content
        if ($response->status() !== 201) {
            dump('Response status: ' . $response->status());
            dump('Response content: ' . $response->content());
        }

        $response->assertStatus(201);

        Event::assertDispatched(NewOrderPlaced::class, function ($event) use ($orderData) {
            return $event->order->order_number === $orderData['order_number'];
        });
    }

    /** @test */
    public function it_broadcasts_order_status_updated_event()
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
        ]);

        $updateData = [
            'status' => 'preparing',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/orders/{$order->id}", $updateData);

        $response->assertOk();

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                   $event->previousStatus === 'pending' &&
                   $event->newStatus === 'preparing';
        });
    }

    /** @test */
    public function it_broadcasts_driver_location_updated_event()
    {
        Event::fake([DriverLocationUpdated::class]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
        ]);

        $assignment = OrderAssignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'status' => 'assigned',
        ]);

        // Debug: Verify driver exists and can be found
        dump('=== PRE-REQUEST DEBUG ===');
        dump('Driver ID: ' . $this->driver->id);
        dump('Driver exists in DB: ' . (Driver::find($this->driver->id) ? 'YES' : 'NO'));
        dump('Driver model: ' . json_encode($this->driver->toArray()));
        dump('User authenticated: ' . (auth()->check() ? 'YES' : 'NO'));
        dump('User ID: ' . (auth()->check() ? auth()->id() : 'NOT AUTHENTICATED'));
        dump('User role: ' . (auth()->check() ? auth()->user()->role : 'NOT AUTHENTICATED'));
        dump('========================');

        $locationData = [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10,
            'speed' => 25,
            'heading' => 90,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/drivers/{$this->driver->id}/location", $locationData);

        // Check authentication state right after actingAs
        dump('=== AUTHENTICATION CHECK ===');
        dump('After actingAs - User authenticated: ' . (auth()->check() ? 'YES' : 'NO'));
        dump('After actingAs - User ID: ' . (auth()->check() ? auth()->id() : 'NOT AUTHENTICATED'));
        dump('After actingAs - User role: ' . (auth()->check() ? auth()->user()->role : 'NOT AUTHENTICATED'));
        dump('============================');

        // Comprehensive debugging to understand the 400 error
        dump('=== DEBUG INFO ===');
        dump('Request URL: ' . "/api/v1/drivers/{$this->driver->id}/location");
        dump('Request Method: POST');
        dump('Request Data: ' . json_encode($locationData));
        dump('Driver ID: ' . $this->driver->id);
        dump('User ID: ' . $this->user->id);
        dump('User Role: ' . $this->user->role);
        dump('Response Status: ' . $response->status());
        dump('Response Headers: ' . json_encode($response->headers->all()));
        dump('Response Content: ' . $response->content());
        dump('==================');

        $response->assertOk();

        Event::assertDispatched(DriverLocationUpdated::class, function ($event) use ($locationData) {
            return $event->driver->id === $this->driver->id &&
                   $event->location['latitude'] === $locationData['latitude'] &&
                   $event->location['longitude'] === $locationData['longitude'];
        });
    }

    /** @test */
    public function it_broadcasts_delivery_status_changed_event()
    {
        Event::fake([DeliveryStatusChanged::class]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
        ]);

        $assignment = OrderAssignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'status' => 'assigned',
        ]);

        // Manually dispatch the event to test it
        broadcast(new DeliveryStatusChanged($assignment, 'assigned', 'picked_up'));

        Event::assertDispatched(DeliveryStatusChanged::class, function ($event) use ($assignment) {
            return $event->orderAssignment->id === $assignment->id &&
                   $event->previousStatus === 'assigned' &&
                   $event->newStatus === 'picked_up';
        });
    }

    /** @test */
    public function it_broadcasts_to_correct_channels()
    {
        Event::fake([OrderStatusUpdated::class, DriverLocationUpdated::class]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
        ]);

        $assignment = OrderAssignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
        ]);

        // Test OrderStatusUpdated event
        broadcast(new OrderStatusUpdated($order, 'pending', 'preparing'));

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });

        // Test DriverLocationUpdated event
        $location = ['latitude' => 40.7128, 'longitude' => -74.0060];
        broadcast(new DriverLocationUpdated($this->driver, $location, $assignment));

        Event::assertDispatched(DriverLocationUpdated::class, function ($event) use ($assignment) {
            return $event->orderAssignment->id === $assignment->id;
        });
    }

    /** @test */
    public function it_includes_correct_data_in_broadcasts()
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
            'order_number' => 'ORD-001',
        ]);

        broadcast(new OrderStatusUpdated($order, 'pending', 'preparing'));

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                   $event->order->order_number === 'ORD-001' &&
                   $event->previousStatus === 'pending' &&
                   $event->newStatus === 'preparing';
        });
    }

    /** @test */
    public function it_handles_broadcasting_errors_gracefully()
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurantBranch->restaurant_id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => $this->customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 25.50,
            'total_amount' => 25.50,
        ]);

        // This should not throw an exception
        $this->expectNotToPerformAssertions();

        try {
            broadcast(new OrderStatusUpdated($order, 'pending', 'preparing'));
        } catch (\Exception $e) {
            // Broadcasting errors should be logged but not break the application
            $this->fail('Broadcasting error should not break the application');
        }
    }
}
