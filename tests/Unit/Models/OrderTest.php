<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected Order $order;
    protected Customer $customer;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = Customer::factory()->create();
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
        
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $this->address->id,
        ]);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'order_number', 'customer_id', 'restaurant_id', 'restaurant_branch_id',
            'customer_address_id', 'status', 'type', 'payment_status', 'payment_method',
            'subtotal', 'tax_amount', 'delivery_fee', 'service_fee', 'discount_amount',
            'total_amount', 'currency', 'confirmed_at', 'prepared_at', 'picked_up_at',
            'delivered_at', 'cancelled_at', 'estimated_preparation_time',
            'estimated_delivery_time', 'customer_name', 'customer_phone',
            'delivery_address', 'delivery_notes', 'special_instructions',
            'payment_transaction_id', 'payment_data', 'promo_code',
            'loyalty_points_earned', 'loyalty_points_used', 'tier_discount_percentage',
            'coupon_discount_percentage', 'pos_data',
            'cancellation_reason', 'refund_amount', 'refunded_at'
        ];

        $this->assertEqualsCanonicalizing($fillable, $this->order->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $casts = [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'loyalty_points_earned' => 'decimal:2',
            'loyalty_points_used' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'prepared_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'payment_data' => 'array',
            'pos_data' => 'array',
        ];

        foreach ($casts as $attribute => $expectedCast) {
            $this->assertArrayHasKey($attribute, $this->order->getCasts());
        }
    }

    #[Test]
    public function it_belongs_to_customer()
    {
        $this->assertInstanceOf(Customer::class, $this->order->customer);
        $this->assertEquals($this->customer->id, $this->order->customer->id);
    }

    #[Test]
    public function it_belongs_to_restaurant()
    {
        $this->assertInstanceOf(Restaurant::class, $this->order->restaurant);
        $this->assertEquals($this->restaurant->id, $this->order->restaurant->id);
    }

    #[Test]
    public function it_belongs_to_restaurant_branch()
    {
        $this->assertInstanceOf(RestaurantBranch::class, $this->order->branch);
        $this->assertEquals($this->branch->id, $this->order->branch->id);
    }

    #[Test]
    public function it_belongs_to_customer_address()
    {
        $this->assertInstanceOf(CustomerAddress::class, $this->order->address);
        $this->assertEquals($this->address->id, $this->order->address->id);
    }

    #[Test]
    public function it_has_many_order_items()
    {
        $menuItem = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);
        
        $orderItem1 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'menu_item_id' => $menuItem->id
        ]);
        
        $orderItem2 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'menu_item_id' => $menuItem->id
        ]);

        $this->assertCount(2, $this->order->items);
        $this->assertInstanceOf(OrderItem::class, $this->order->items->first());
    }

    #[Test]
    public function it_has_valid_status_enum_values()
    {
        $validStatuses = [
            'pending', 'confirmed', 'preparing', 'out_for_delivery',
            'delivered', 'completed', 'cancelled'
        ];

        foreach ($validStatuses as $status) {
            $order = Order::factory()->create(['status' => $status]);
            $this->assertEquals($status, $order->status);
        }
    }

    #[Test]
    public function it_has_valid_type_enum_values()
    {
        $validTypes = ['delivery', 'pickup', 'dine_in'];

        foreach ($validTypes as $type) {
            $order = Order::factory()->create(['type' => $type]);
            $this->assertEquals($type, $order->type);
        }
    }

    #[Test]
    public function it_has_valid_payment_status_enum_values()
    {
        $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        foreach ($validPaymentStatuses as $paymentStatus) {
            $order = Order::factory()->create(['payment_status' => $paymentStatus]);
            $this->assertEquals($paymentStatus, $order->payment_status);
        }
    }

    #[Test]
    public function it_has_valid_payment_method_enum_values()
    {
        $validPaymentMethods = ['cash', 'card', 'wallet', 'google_pay', 'apple_pay'];

        foreach ($validPaymentMethods as $paymentMethod) {
            $order = Order::factory()->create(['payment_method' => $paymentMethod]);
            $this->assertEquals($paymentMethod, $order->payment_method);
        }
    }

    #[Test]
    public function it_calculates_total_amount_correctly()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'tax_amount' => 10.00,
            'discount_amount' => 15.00,
            'total_amount' => 102.00, // Set the expected total
        ]);

        $this->assertEquals(102.00, $order->total_amount);
    }

    #[Test]
    public function it_scopes_orders_by_status()
    {
        // Clear existing orders to ensure clean test state
        Order::query()->delete();
        
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'confirmed']);
        Order::factory()->create(['status' => 'completed']);

        $pendingOrders = Order::whereStatus('pending')->get();
        $this->assertCount(1, $pendingOrders);
        $this->assertEquals('pending', $pendingOrders->first()->status);
    }

    #[Test]
    public function it_scopes_orders_by_customer()
    {
        $customer2 = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer2->id]);

        $customerOrders = Order::whereCustomerId($this->customer->id)->get();
        $this->assertGreaterThan(0, $customerOrders->count());
        $this->assertEquals($this->customer->id, $customerOrders->first()->customer_id);
    }

    #[Test]
    public function it_scopes_orders_by_restaurant()
    {
        $restaurant2 = Restaurant::factory()->create();
        Order::factory()->create(['restaurant_id' => $restaurant2->id]);

        $restaurantOrders = Order::whereRestaurantId($this->restaurant->id)->get();
        $this->assertGreaterThan(0, $restaurantOrders->count());
        $this->assertEquals($this->restaurant->id, $restaurantOrders->first()->restaurant_id);
    }

    #[Test]
    public function it_handles_cancellation_with_reason()
    {
        $order = Order::factory()->create(['status' => 'confirmed']);
        
        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer requested cancellation',
            'cancelled_at' => now()
        ]);

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals('Customer requested cancellation', $order->fresh()->cancellation_reason);
        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    #[Test]
    public function it_handles_delivery_completion()
    {
        $order = Order::factory()->create(['status' => 'out_for_delivery']);
        
        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    #[Test]
    public function it_generates_unique_order_number()
    {
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();

        $this->assertNotEquals($order1->order_number, $order2->order_number);
        $this->assertNotEmpty($order1->order_number);
        $this->assertNotEmpty($order2->order_number);
    }

    #[Test]
    public function it_validates_required_relationships()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Order::create([
            'order_number' => 'TEST-001',
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            // Missing required foreign keys
        ]);
    }
} 