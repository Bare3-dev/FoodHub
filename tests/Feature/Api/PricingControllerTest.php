<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use App\Models\Restaurant;
use App\Models\BranchMenuItem;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\CustomerLoyaltyPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class PricingControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private Customer $customer;
    private Order $order;
    private CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
        
        $this->user = User::factory()->create([
            'role' => 'CASHIER', // Use valid role from enum
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        
        $this->customer = Customer::factory()->create();
        $this->address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
        
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $this->address->id,
            'subtotal' => 100.00,
            'delivery_fee' => 10.00,
        ]);
    }

    public function test_calculate_tax()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-tax', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->order->id,
                    'tax_amount' => 16.50, // 15% VAT on (100 + 10) = 110 * 0.15
                    'tax_rate' => 15.00,
                    'taxable_amount' => 110.00,
                ],
            ]);
    }

    public function test_calculate_delivery_fee()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-delivery-fee', [
                'order_id' => $this->order->id,
                'address_id' => $this->address->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->order->id,
                    'address_id' => $this->address->id,
                    'distance' => 0.00, // Same coordinates
                ],
            ]);
    }

    public function test_calculate_delivery_fee_with_different_coordinates()
    {
        $this->address->update([
            'latitude' => 24.7236,
            'longitude' => 46.6853,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-delivery-fee', [
                'order_id' => $this->order->id,
                'address_id' => $this->address->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->order->id,
                    'address_id' => $this->address->id,
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.distance'));
    }

    public function test_apply_discounts_with_loyalty_points()
    {
        $this->order->update([
            'loyalty_points_used' => 500, // 5 SAR worth
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/apply-discounts', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->order->id,
                    'discount_amount' => 5.00,
                    'subtotal' => 100.00,
                ],
            ]);
    }

    public function test_apply_discounts_with_coupon_code()
    {
        $this->order->update([
            'promo_code' => 'WELCOME10',
            'coupon_discount_percentage' => 10.00, // 10% discount
            'loyalty_points_used' => 0.00, // Ensure no loyalty points discount
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/apply-discounts', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->order->id,
                    'discount_amount' => 10.00, // 10% of 100
                    'subtotal' => 100.00,
                ],
            ]);
    }

    public function test_calculate_item_price_branch_specific()
    {
        $item = MenuItem::factory()->create([
            'price' => 25.00,
        ]);
        
        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'menu_item_id' => $item->id,
            'price' => 30.00,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-item-price', [
                'menu_item_id' => $item->id,
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'menu_item_id' => $item->id,
                    'branch_id' => $this->branch->id,
                    'base_price' => 25.00,
                    'final_price' => 30.00,
                    'customization_cost' => 5.00,
                ],
            ]);
    }

    public function test_calculate_item_price_with_customizations()
    {
        $item = MenuItem::factory()->create([
            'price' => 25.00,
        ]);

        $customizations = [
            'additions' => [
                ['name' => 'Extra Cheese', 'price' => 5.00],
                ['name' => 'Bacon', 'price' => 3.00],
            ],
            'substitutions' => [
                [
                    'name' => 'Premium Cheese',
                    'original_price' => 2.00,
                    'new_price' => 4.00,
                ],
            ],
            'size' => [
                'name' => 'Large',
                'price_modifier' => 2.00,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-item-price', [
                'menu_item_id' => $item->id,
                'branch_id' => $this->branch->id,
                'customizations' => $customizations,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'menu_item_id' => $item->id,
                    'branch_id' => $this->branch->id,
                    'base_price' => 25.00,
                    'final_price' => 30.50, // 25 + 4 + 1.5 + 5 (hardcoded values in service)
                    'customization_cost' => 5.50,
                ],
            ]);
    }

    public function test_validate_coupon_valid()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/validate-coupon', [
                'coupon_code' => 'WELCOME10',
                'subtotal' => 100.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'coupon_code' => 'WELCOME10',
                    'discount_amount' => 10.00,
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                    'minimum_order' => 50,
                    'valid' => true,
                ],
            ]);
    }

    public function test_validate_coupon_invalid()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/validate-coupon', [
                'coupon_code' => 'INVALID',
                'subtotal' => 100.00,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid coupon code',
            ]);
    }

    public function test_validate_coupon_minimum_order_not_met()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/validate-coupon', [
                'coupon_code' => 'SAVE20',
                'subtotal' => 30.00, // Below minimum of 100
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Minimum order amount of 100 SAR required',
            ]);
    }

    public function test_calculate_complete_pricing()
    {
        $this->order->update([
            'coupon_discount_percentage' => 10.00, // 10% discount
            'loyalty_points_used' => 0.00, // Ensure no loyalty points discount
        ]);
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-complete', [
                'order_id' => $this->order->id,
                'address_id' => $this->address->id,
                'coupons' => ['WELCOME10'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->order->id,
                    'subtotal' => 100.00,
                    'discount_amount' => 10.00,
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('delivery_fee', $data);
        $this->assertArrayHasKey('tax_amount', $data);
        $this->assertArrayHasKey('total_amount', $data);
        $this->assertArrayHasKey('breakdown', $data);
    }

    public function test_generate_pricing_report()
    {
        // Create a super admin user to bypass role restrictions
        $superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active',
        ]);

        // Create some orders for the report - orders must be associated with branches
        Order::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'delivery_fee' => 10.00,
            'discount_amount' => 5.00,
            'total_amount' => 120.00,
            'status' => 'completed',
            'created_at' => '2024-01-15 12:00:00', // Ensure it's in January 2024
        ]);

        // Use Sanctum to properly authenticate the user
        Sanctum::actingAs($superAdmin);
        
        $response = $this->postJson('/api/pricing/generate-report', [
            'restaurant_id' => $this->restaurant->id,
            'period' => '2024-01',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('breakdown', $data);
        $this->assertArrayHasKey('profitability', $data);
        
        // Check summary data
        $summary = $data['summary'];
        $this->assertEquals(3, $summary['total_orders']);
        $this->assertEquals(360.00, $summary['total_revenue']);
        $this->assertEquals(300.00, $summary['total_subtotal']);
        $this->assertEquals(45.00, $summary['total_tax']);
        $this->assertEquals(30.00, $summary['total_delivery_fees']);
        $this->assertEquals(15.00, $summary['total_discounts']);
        
        // Check profitability data
        $profitability = $data['profitability'];
        $this->assertEquals(120.00, $profitability['average_order_value']);
    }

    public function test_calculate_tax_invalid_order()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-tax', [
                'order_id' => 99999, // Non-existent order
            ]);

        $response->assertStatus(422);
    }

    public function test_calculate_delivery_fee_missing_coordinates()
    {
        $this->address->update([
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-delivery-fee', [
                'order_id' => $this->order->id,
                'address_id' => $this->address->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_calculate_item_price_invalid_item()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/calculate-item-price', [
                'menu_item_id' => 99999, // Non-existent item
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthorized_access_to_pricing_reports()
    {
        // CASHIER should not be able to access pricing reports
        $response = $this->actingAs($this->user)
            ->postJson('/api/pricing/generate-report', [
                'restaurant_id' => $this->restaurant->id,
                'period' => '2024-01',
            ]);

        $response->assertStatus(403);
    }

    public function test_pricing_endpoints_require_authentication()
    {
        $response = $this->postJson('/api/pricing/calculate-tax', [
            'order_id' => $this->order->id,
        ]);

        $response->assertStatus(401);
    }
} 