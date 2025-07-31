<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\CustomerLoyaltyPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\BranchMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class OrderLoyaltyIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $superAdmin;
    protected Customer $customer;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected LoyaltyProgram $loyaltyProgram;
    protected LoyaltyTier $bronzeTier;
    protected LoyaltyTier $silverTier;
    protected CustomerLoyaltyPoint $customerLoyaltyPoints;
    protected MenuCategory $category;
    protected MenuItem $menuItem;
    protected BranchMenuItem $branchMenuItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with ACTIVE status
        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active'
        ]);
        
        // Create test customer
        $this->customer = Customer::factory()->create([
            'status' => 'active'
        ]);
        
        // Create test restaurant and branch
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        // Create loyalty program
        $this->loyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'points_per_dollar' => 1.00,
            'dollar_per_point' => 0.01,
            'minimum_spend_for_points' => 10.00,
            'is_active' => true
        ]);
        
        // Create loyalty tiers
        $this->bronzeTier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'name' => 'Bronze',
            'display_name' => 'Bronze Member',
            'min_points_required' => 0,
            'points_multiplier' => 1.00,
            'discount_percentage' => 0.00
        ]);
        
        $this->silverTier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'name' => 'Silver',
            'display_name' => 'Silver Member',
            'min_points_required' => 100,
            'points_multiplier' => 1.25,
            'discount_percentage' => 5.00
        ]);
        
        // Create customer loyalty points
        $this->customerLoyaltyPoints = CustomerLoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'loyalty_tier_id' => $this->bronzeTier->id,
            'current_points' => 50.00,
            'total_points_earned' => 100.00,
            'total_points_redeemed' => 50.00,
            'is_active' => true
        ]);
        
        // Create menu items
        $this->category = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'price' => 15.00
        ]);
        
        $this->branchMenuItem = BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'menu_item_id' => $this->menuItem->id,
            'price' => 15.00,
            'is_available' => true
        ]);
    }

    /** @test */
    public function it_calculates_points_earning_correctly()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 30.00,
            'tax_amount' => 3.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 40.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify points calculation
        $order = Order::latest()->first();
        $expectedPoints = 30.00; // $30 subtotal * 1.00 points per dollar
        
        $this->assertEquals($expectedPoints, $order->loyalty_points_earned);
        
        // Verify customer loyalty points updated
        $this->customerLoyaltyPoints->refresh();
        $this->assertEquals(80.00, $this->customerLoyaltyPoints->current_points); // 50 + 30
    }

    /** @test */
    public function it_applies_tier_multipliers_to_points_earning()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Upgrade customer to silver tier
        $this->customerLoyaltyPoints->update([
            'loyalty_tier_id' => $this->silverTier->id,
            'current_points' => 150.00
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 40.00,
            'tax_amount' => 4.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 51.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify points calculation with tier multiplier
        $order = Order::latest()->first();
        $expectedPoints = 50.00; // $40 subtotal * 1.00 points per dollar * 1.25 multiplier
        
        $this->assertEquals($expectedPoints, $order->loyalty_points_earned);
    }

    /** @test */
    public function it_handles_loyalty_points_redemption_during_checkout()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Give customer enough points
        $this->customerLoyaltyPoints->update([
            'current_points' => 200.00
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 50.00,
            'tax_amount' => 5.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 2.00, // $2 discount from points
            'total_amount' => 60.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00, // Will be calculated by service
            'loyalty_points_used' => 200.00 // 200 points used (worth $2)
        ];

        // Debug: Log the order data being sent
        \Log::info('Test order data:', $orderData);

        $response = $this->postJson('/api/orders', $orderData);

        // Debug: Log the response
        \Log::info('Test response:', [
            'status' => $response->status(),
            'content' => $response->content()
        ]);

        $response->assertStatus(201);

        // Verify points redemption
        $order = Order::latest()->first();
        
        // Debug: Log the order data
        \Log::info('Created order:', [
            'id' => $order->id,
            'loyalty_points_used' => $order->loyalty_points_used,
            'loyalty_points_earned' => $order->loyalty_points_earned
        ]);
        
        $this->assertEquals(200.00, $order->loyalty_points_used);
        
        // Verify customer loyalty points updated
        $this->customerLoyaltyPoints->refresh();
        $this->assertEquals(50.00, $this->customerLoyaltyPoints->current_points); // 200 - 200 + 50 (earned from order)
    }

    /** @test */
    public function it_applies_promotional_point_multipliers()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Set up promotional multiplier in loyalty program
        $this->loyaltyProgram->update([
            'bonus_multipliers' => [
                'happy_hour' => 2.0,
                'birthday' => 3.0,
                'first_order' => 1.5
            ]
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 25.00,
            'tax_amount' => 2.50,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 34.50,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00,
            'promo_code' => 'HAPPYHOUR' // This should trigger 2x multiplier
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify promotional multiplier applied
        $order = Order::latest()->first();
        $expectedPoints = 50.00; // $25 subtotal * 1.00 points per dollar * 2.0 multiplier
        
        $this->assertEquals($expectedPoints, $order->loyalty_points_earned);
    }

    /** @test */
    public function it_handles_points_expiration_correctly()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Set up points with expiration
        $this->customerLoyaltyPoints->update([
            'current_points' => 100.00,
            'points_expiry_date' => now()->subDays(1) // Expired yesterday
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 30.00,
            'tax_amount' => 3.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 40.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify expired points are not used
        $order = Order::latest()->first();
        $this->assertEquals(0.00, $order->loyalty_points_used);
        
        // Verify customer loyalty points updated (expired points should be deducted)
        $this->customerLoyaltyPoints->refresh();
        $this->assertEquals(30.00, $this->customerLoyaltyPoints->current_points); // 0 + 30 (expired points deducted)
    }

    /** @test */
    public function it_enforces_minimum_spend_for_points_earning()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Set minimum spend requirement
        $this->loyaltyProgram->update([
            'minimum_spend_for_points' => 25.00
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 20.00, // Below minimum spend
            'tax_amount' => 2.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 29.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify no points earned due to minimum spend requirement
        $order = Order::latest()->first();
        $this->assertEquals(0.00, $order->loyalty_points_earned);
    }

    /** @test */
    public function it_handles_concurrent_point_transactions()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Create multiple orders simultaneously
        $orders = [];
        for ($i = 0; $i < 3; $i++) {
            $orders[] = [
                'order_number' => 'ORD-' . time() . '-' . $i,
                'customer_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'restaurant_branch_id' => $this->branch->id,
                'customer_address_id' => $customerAddress->id,
                'status' => 'pending',
                'type' => 'delivery',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'subtotal' => 20.00,
                'tax_amount' => 2.00,
                'delivery_fee' => 5.00,
                'service_fee' => 2.00,
                'discount_amount' => 0.00,
                'total_amount' => 29.00,
                'currency' => 'SAR',
                'customer_name' => $this->customer->full_name,
                'customer_phone' => $this->customer->phone,
                'delivery_address' => 'Test Address',
                'loyalty_points_earned' => 0.00,
                'loyalty_points_used' => 0.00
            ];
        }

        // Submit all orders
        foreach ($orders as $orderData) {
            $response = $this->postJson('/api/orders', $orderData);
            $response->assertStatus(201);
        }

        // Verify total points earned
        $totalPointsEarned = Order::where('customer_id', $this->customer->id)
            ->sum('loyalty_points_earned');
        
        $this->assertEquals(60.00, $totalPointsEarned); // 3 orders * 20 points each
        
        // Verify customer loyalty points updated correctly
        $this->customerLoyaltyPoints->refresh();
        $this->assertEquals(110.00, $this->customerLoyaltyPoints->current_points); // 50 + 60
    }

    /** @test */
    public function it_validates_points_redemption_limits()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Try to use more points than available
        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 50.00,
            'tax_amount' => 5.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 20.00, // Try to use $20 worth of points
            'total_amount' => 42.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 2000.00 // Try to use 2000 points (only 50 available)
        ];

        $response = $this->postJson('/api/orders', $orderData);

        // Should fail due to insufficient points
        $response->assertStatus(422);
    }

    /** @test */
    public function it_tracks_loyalty_points_history()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 30.00,
            'tax_amount' => 3.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 40.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        $order = Order::latest()->first();

        // Verify loyalty points history is created
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoints->id,
            'order_id' => $order->id,
            'transaction_type' => 'earned',
            'points_amount' => 30.00,
            'source' => 'order',
            'reference_type' => 'order',
            'reference_id' => $order->id
        ]);
    }

    /** @test */
    public function it_automatically_upgrades_customer_tier_when_threshold_reached()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Set customer points close to silver tier threshold (100 points)
        $this->customerLoyaltyPoints->update([
            'current_points' => 95.00,
            'loyalty_tier_id' => $this->bronzeTier->id
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 10.00, // This will earn 10 points, bringing total to 105
            'tax_amount' => 1.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 18.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify customer was upgraded to silver tier
        $this->customerLoyaltyPoints->refresh();
        $this->assertEquals($this->silverTier->id, $this->customerLoyaltyPoints->loyalty_tier_id);
        $this->assertEquals(105.00, $this->customerLoyaltyPoints->current_points);

        // Verify tier upgrade history is created
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoints->id,
            'transaction_type' => 'tier_upgrade',
            'points_amount' => 0.00,
            'source' => 'tier_progression',
            'description' => 'Tier upgraded to Silver Member'
        ]);
    }

    /** @test */
    public function it_handles_multiple_tier_upgrades_in_single_order()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create customer address
        $customerAddress = \App\Models\CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Create a gold tier
        $goldTier = \App\Models\LoyaltyTier::factory()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'name' => 'Gold',
            'display_name' => 'Gold Member',
            'min_points_required' => 200,
            'points_multiplier' => 1.50,
            'discount_percentage' => 10.00
        ]);

        // Set customer points close to gold tier threshold
        $this->customerLoyaltyPoints->update([
            'current_points' => 195.00,
            'loyalty_tier_id' => $this->bronzeTier->id
        ]);

        $orderData = [
            'order_number' => 'ORD-' . time(),
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $customerAddress->id,
            'status' => 'pending',
            'type' => 'delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 10.00, // This will earn 10 points, bringing total to 205
            'tax_amount' => 1.00,
            'delivery_fee' => 5.00,
            'service_fee' => 2.00,
            'discount_amount' => 0.00,
            'total_amount' => 18.00,
            'currency' => 'SAR',
            'customer_name' => $this->customer->full_name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => 'Test Address',
            'loyalty_points_earned' => 0.00,
            'loyalty_points_used' => 0.00
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        // Verify customer was upgraded directly to gold tier (skipping silver)
        $this->customerLoyaltyPoints->refresh();
        $this->assertEquals($goldTier->id, $this->customerLoyaltyPoints->loyalty_tier_id);
        $this->assertEquals(205.00, $this->customerLoyaltyPoints->current_points);

        // Verify tier upgrade history is created
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoints->id,
            'transaction_type' => 'tier_upgrade',
            'points_amount' => 0.00,
            'source' => 'tier_progression',
            'description' => 'Tier upgraded to Gold Member'
        ]);
    }
} 