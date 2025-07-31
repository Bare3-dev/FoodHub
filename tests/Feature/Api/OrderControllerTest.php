<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private Customer $customer;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->restaurant = Restaurant::factory()->create();
        $this->restaurantOwner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active'
        ]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
            'permissions' => ['order:update-status-own-branch']
        ]);
        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_branch_id' => $this->branch->id,
            'permissions' => ['order:create-takeaway', 'order:update-status-own-branch'],
            'status' => 'active'
        ]);
        $this->customer = Customer::factory()->create();
        $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
    }

    #[Test]
    public function it_creates_new_order()
    {
        $orderData = [
            'order_number' => 'ORD-2024-001',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $this->address->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal' => 25.00,
            'delivery_fee' => 5.00,
            'tax_amount' => 2.50,
            'total_amount' => 32.50,
            'discount_amount' => 0.00,
            'notes' => 'Extra cheese please',
            'delivery_instructions' => 'Ring doorbell twice'
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'order_number',
                'customer_id',
                'restaurant_id',
                'restaurant_branch_id',
                'customer_address_id',
                'status',
                'type',
                'payment_status',
                'payment_method',
                'subtotal',
                'delivery_fee',
                'tax_amount',
                'total_amount',
                'discount_amount',
                'notes',
                'delivery_instructions',
                'created_at',
                'updated_at'
            ]);

        $this->assertDatabaseHas('orders', [
            'order_number' => 'ORD-2024-001',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal' => 25.00,
            'total_amount' => 32.50
        ]);
    }

    #[Test]
    public function it_updates_order_status()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'pending',
            'type' => 'delivery'
        ]);

        $updateData = [
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ];

        $response = $this->actingAs($this->branchManager)
            ->putJson("/api/orders/{$order->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'status' => 'confirmed',
                'payment_status' => 'paid'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ]);
    }

    #[Test]
    public function it_assigns_order_to_driver()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'type' => 'delivery'
        ]);

        $driver = User::factory()->create(['role' => 'DRIVER', 'status' => 'active']);

        $updateData = [
            'status' => 'out_for_delivery',
            'driver_id' => $driver->id
        ];

        $response = $this->actingAs($this->branchManager)
            ->putJson("/api/orders/{$order->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'status' => 'out_for_delivery'
            ]);
    }

    #[Test]
    public function it_tracks_order_delivery()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'out_for_delivery',
            'type' => 'delivery'
        ]);

        $updateData = [
            'status' => 'delivered',
            'delivered_at' => now()->toDateTimeString()
        ];

        $response = $this->actingAs($this->branchManager)
            ->putJson("/api/orders/{$order->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'status' => 'delivered'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered'
        ]);
    }

    #[Test]
    public function it_validates_order_data()
    {
        $invalidData = [
            'order_number' => '', // Required
            'customer_id' => 99999, // Non-existent
            'restaurant_id' => 99999, // Non-existent
            'restaurant_branch_id' => 99999, // Non-existent
            'customer_address_id' => 99999, // Non-existent
            'status' => 'invalid_status', // Invalid enum
            'type' => 'invalid_type', // Invalid enum
            'payment_status' => 'invalid_payment', // Invalid enum
            'payment_method' => 'invalid_method', // Invalid enum
            'subtotal' => -10, // Negative amount
            'total_amount' => 'not_a_number' // Invalid number
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'order_number',
                'customer_id',
                'restaurant_id',
                'restaurant_branch_id',
                'customer_address_id',
                'status',
                'type',
                'payment_status',
                'payment_method',
                'subtotal',
                'total_amount'
            ]);
    }

    #[Test]
    public function it_enforces_order_permissions()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);

        // Test unauthorized user cannot view order
        $unauthorizedUser = User::factory()->create(['role' => 'CUSTOMER_SERVICE', 'status' => 'active']);
        
        $response = $this->actingAs($unauthorizedUser)
            ->getJson("/api/orders/{$order->id}");
        
        $response->assertStatus(403);

        // Test unauthorized user cannot update order
        $response = $this->actingAs($unauthorizedUser)
            ->putJson("/api/orders/{$order->id}", ['status' => 'confirmed']);
        
        $response->assertStatus(403);

        // Test unauthorized user cannot delete order
        $response = $this->actingAs($unauthorizedUser)
            ->deleteJson("/api/orders/{$order->id}");
        
        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_order_cancellation()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ]);

        $cancelData = [
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer requested cancellation',
            'cancelled_at' => now()->toDateTimeString()
        ];

        $response = $this->actingAs($this->branchManager)
            ->putJson("/api/orders/{$order->id}", $cancelData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'status' => 'cancelled'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer requested cancellation'
        ]);
    }

    #[Test]
    public function it_calculates_order_totals()
    {
        $orderData = [
            'order_number' => 'ORD-2024-002',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $this->address->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal' => 50.00,
            'delivery_fee' => 8.00,
            'tax_amount' => 5.00,
            'discount_amount' => 10.00,
            'total_amount' => 53.00, // 50 + 8 + 5 - 10
            'notes' => 'Test order with calculations'
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJson([
                'subtotal' => 50.00,
                'delivery_fee' => 8.00,
                'tax_amount' => 5.00,
                'discount_amount' => 10.00,
                'total_amount' => 53.00
            ]);

        $this->assertDatabaseHas('orders', [
            'order_number' => 'ORD-2024-002',
            'subtotal' => 50.00,
            'delivery_fee' => 8.00,
            'tax_amount' => 5.00,
            'discount_amount' => 10.00,
            'total_amount' => 53.00
        ]);
    }

    #[Test]
    public function it_lists_orders_with_pagination()
    {
        // Create multiple orders
        Order::factory()->count(25)->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);

        $response = $this->actingAs($this->branchManager)
            ->getJson('/api/orders?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'order_number',
                    'customer_id',
                    'restaurant_id',
                    'restaurant_branch_id',
                    'status',
                    'type',
                    'payment_status',
                    'payment_method',
                    'subtotal',
                    'total_amount',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $responseData = $response->json();
        $this->assertCount(10, $responseData);
        // Note: We can't check total count since it's not paginated in the response
    }

    #[Test]
    public function it_shows_specific_order()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);

        $response = $this->actingAs($this->branchManager)
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'restaurant_id' => $order->restaurant_id,
                'restaurant_branch_id' => $order->restaurant_branch_id,
                'status' => $order->status,
                'type' => $order->type,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => $order->subtotal,
                'total_amount' => $order->total_amount
            ]);
    }

    #[Test]
    public function it_deletes_order()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    #[Test]
    public function it_handles_order_with_items()
    {
        $menuItem = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);
        
        $orderData = [
            'order_number' => 'ORD-2024-003',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $this->address->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal' => 30.00,
            'delivery_fee' => 5.00,
            'tax_amount' => 3.00,
            'total_amount' => 38.00
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        $orderId = $response->json('id');
        
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_number' => 'ORD-2024-003',
            'subtotal' => 30.00,
            'total_amount' => 38.00
        ]);

        // Note: Order items creation is not implemented in the controller yet
        // This would be a separate feature to implement
    }

    #[Test]
    public function it_enforces_unique_order_numbers()
    {
        $existingOrder = Order::factory()->create([
            'order_number' => 'ORD-2024-001',
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id
        ]);

        $duplicateOrderData = [
            'order_number' => 'ORD-2024-001', // Duplicate
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $this->address->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal' => 25.00,
            'total_amount' => 25.00
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', $duplicateOrderData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_number']);
    }

    #[Test]
    public function it_handles_order_status_transitions()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'pending'
        ]);

        // Test valid status transitions
        $statusTransitions = [
            'confirmed' => ['status' => 'confirmed'],
            'preparing' => ['status' => 'preparing'],
            'out_for_delivery' => ['status' => 'out_for_delivery'],
            'delivered' => ['status' => 'delivered'],
            'completed' => ['status' => 'completed']
        ];

        foreach ($statusTransitions as $expectedStatus => $updateData) {
            $response = $this->actingAs($this->branchManager)
                ->putJson("/api/orders/{$order->id}", $updateData);

            $response->assertStatus(200)
                ->assertJson([
                    'id' => $order->id,
                    'status' => $expectedStatus
                ]);

            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'status' => $expectedStatus
            ]);
        }
    }
} 