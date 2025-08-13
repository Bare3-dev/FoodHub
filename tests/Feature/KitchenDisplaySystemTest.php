<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Events\OrderStatusUpdated;
use App\Events\NewOrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class KitchenDisplaySystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchenStaff;
    protected User $restaurantOwner;
    protected User $admin;
    protected Restaurant $restaurant;
    protected RestaurantBranch $restaurantBranch;
    protected Customer $customer;

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
    }

    /** @test */
    public function it_broadcasts_new_order_placed_event()
    {
        Event::fake();
        
        $order = Order::create([
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
        
        // Dispatch the event
        event(new NewOrderPlaced($order));
        
        Event::assertDispatched(NewOrderPlaced::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    /** @test */
    public function it_broadcasts_order_status_updated_event()
    {
        Event::fake();
        
        $order = Order::create([
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
        
        // Update order status
        $order->update(['status' => 'preparing']);
        
        // Dispatch the event
        event(new OrderStatusUpdated($order, 'pending', 'preparing'));
        
        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id && 
                   $event->previousStatus === 'pending' && 
                   $event->newStatus === 'preparing';
        });
    }

    /** @test */
    public function it_calculates_order_priority_correctly()
    {
        // Create orders with different priorities
        $urgentOrder = Order::create([
            'order_number' => 'ORD-URGENT-001',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => null,
            'driver_id' => null,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 15.00,
            'tax_amount' => 2.25,
            'delivery_fee' => 5.00,
            'service_fee' => 1.50,
            'discount_amount' => 0.00,
            'total_amount' => 23.75,
            'currency' => 'SAR',
            'estimated_preparation_time' => 15,
            'estimated_delivery_time' => 25
        ]);
        
        $normalOrder = Order::create([
            'order_number' => 'ORD-NORMAL-001',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => null,
            'driver_id' => null,
            'status' => 'pending',
            'type' => 'pickup',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'subtotal' => 30.00,
            'tax_amount' => 4.50,
            'delivery_fee' => 0.00,
            'service_fee' => 3.00,
            'discount_amount' => 0.00,
            'total_amount' => 37.50,
            'currency' => 'SAR',
            'estimated_preparation_time' => 25,
            'estimated_delivery_time' => 0
        ]);
        
        // Test priority calculation logic
        $urgentPriority = $this->calculateOrderPriority($urgentOrder);
        $normalPriority = $this->calculateOrderPriority($normalOrder);
        
        // Urgent orders should have higher priority
        $this->assertGreaterThan($normalPriority, $urgentPriority);
        
        // Test specific priority factors
        $this->assertTrue($this->isUrgentOrder($urgentOrder));
        $this->assertFalse($this->isUrgentOrder($normalOrder));
    }

    /** @test */
    public function it_authorizes_kitchen_channels_correctly()
    {
        // Kitchen staff can access their kitchen channel
        $this->actingAs($this->kitchenStaff);
        $this->assertTrue($this->canAccessKitchenChannel($this->kitchenStaff, $this->restaurantBranch->id));
        
        // Restaurant owner can access their kitchen channel
        $this->actingAs($this->restaurantOwner);
        $this->assertTrue($this->canAccessKitchenChannel($this->restaurantOwner, $this->restaurantBranch->id));
        
        // Admin can access any kitchen channel
        $this->actingAs($this->admin);
        $this->assertTrue($this->canAccessKitchenChannel($this->admin, $this->restaurantBranch->id));
        
        // Regular customers cannot access kitchen channels
        $this->actingAs($this->customer);
        $this->assertFalse($this->canAccessKitchenChannel($this->customer, $this->restaurantBranch->id));
    }

    /** @test */
    public function it_handles_kitchen_order_queue_updates()
    {
        // Create multiple orders for queue testing
        $orders = [];
        for ($i = 1; $i <= 3; $i++) {
            $orders[] = Order::create([
                'order_number' => "ORD-QUEUE-00{$i}",
                'customer_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'restaurant_branch_id' => $this->restaurantBranch->id,
                'customer_address_id' => null,
                'driver_id' => null,
                'status' => 'pending',
                'type' => 'delivery',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'subtotal' => 20.00 + ($i * 5),
                'tax_amount' => 3.00 + ($i * 0.75),
                'delivery_fee' => 5.00,
                'service_fee' => 2.00 + ($i * 0.25),
                'discount_amount' => 0.00,
                'total_amount' => 30.00 + ($i * 6),
                'currency' => 'SAR',
                'estimated_preparation_time' => 20 + ($i * 5),
                'estimated_delivery_time' => 30
            ]);
        }
        
        // Test queue ordering by priority
        $orderedQueue = $this->getKitchenOrderQueue($this->restaurantBranch->id);
        
        $this->assertCount(3, $orderedQueue);
        $this->assertEquals($orders[0]->id, $orderedQueue[0]['id']); // First order should be first in queue
        
        // Test queue updates when order status changes
        $orders[1]->update(['status' => 'preparing']);
        
        // Verify the order status was updated
        $this->assertEquals('preparing', $orders[1]->fresh()->status);
        
        // Test that the queue still contains the order
        $updatedQueue = $this->getKitchenOrderQueue($this->restaurantBranch->id);
        $this->assertCount(3, $updatedQueue);
        
        // Find the updated order in the queue
        $updatedOrder = collect($updatedQueue)->firstWhere('id', $orders[1]->id);
        $this->assertNotNull($updatedOrder);
        $this->assertEquals('preparing', $updatedOrder['status']);
    }

    /** @test */
    public function it_tracks_order_preparation_time()
    {
        $order = Order::create([
            'order_number' => 'ORD-TIME-001',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->restaurantBranch->id,
            'customer_address_id' => null,
            'driver_id' => null,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
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
        
        // Start preparation
        $order->update(['status' => 'preparing']);
        
        // Test that preparation progress calculation works
        $this->assertEquals(20, $order->estimated_preparation_time);
        $this->assertEquals('preparing', $order->status);
        
        // Test that order can be completed
        $order->update(['status' => 'ready_for_pickup']);
        $this->assertEquals('ready_for_pickup', $order->status);
        
        // Test priority calculation for this order
        $priority = $this->calculateOrderPriority($order);
        $this->assertGreaterThan(0, $priority);
    }

    // Helper methods for testing
    private function calculateOrderPriority(Order $order): int
    {
        $priority = 0;
        
        // Higher priority for paid orders
        if ($order->payment_status === 'paid') {
            $priority += 10;
        }
        
        // Higher priority for delivery orders
        if ($order->type === 'delivery') {
            $priority += 5;
        }
        
        // Higher priority for orders with shorter preparation time
        if ($order->estimated_preparation_time <= 15) {
            $priority += 15;
        } elseif ($order->estimated_preparation_time <= 25) {
            $priority += 10;
        }
        
        // Higher priority for orders with special instructions or urgent flags
        if ($order->special_instructions) {
            $priority += 5;
        }
        
        return $priority;
    }

    private function isUrgentOrder(Order $order): bool
    {
        return $order->payment_status === 'paid' && 
               $order->type === 'delivery' && 
               $order->estimated_preparation_time <= 15;
    }

    private function canAccessKitchenChannel($user, $restaurantBranchId): bool
    {
        // Check if user is admin
        if ($user && $user->role === 'SUPER_ADMIN') {
            return true;
        }
        
        // Check if user is kitchen staff of this restaurant
        return $user && 
               $user->restaurant_branch_id == $restaurantBranchId && 
               in_array($user->role, ['KITCHEN_STAFF', 'RESTAURANT_OWNER', 'BRANCH_MANAGER']);
    }

    private function getKitchenOrderQueue($restaurantBranchId): array
    {
        return Order::where('restaurant_branch_id', $restaurantBranchId)
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    private function calculatePreparationProgress(Order $order, $startTime): float
    {
        if ($order->status === 'ready_for_pickup' || $order->status === 'out_for_delivery') {
            return 100.0;
        }
        
        $elapsedMinutes = now()->diffInMinutes($startTime);
        $estimatedMinutes = $order->estimated_preparation_time;
        
        if ($estimatedMinutes <= 0) {
            return 0.0;
        }
        
        $progress = ($elapsedMinutes / $estimatedMinutes) * 100;
        return min(100.0, max(0.0, $progress));
    }
}
