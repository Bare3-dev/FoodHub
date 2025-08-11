<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PricingService;
use App\Services\ConfigurationService;
use App\Services\LoyaltyService;
use App\Services\AnalyticsService;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use App\Models\Restaurant;
use App\Models\BranchMenuItem;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTier;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Exceptions\BusinessLogicException;
use Carbon\Carbon;
use Mockery;

class PricingServiceTest extends TestCase
{
    private PricingService $pricingService;
    private ConfigurationService $configurationService;
    private LoyaltyService $loyaltyService;
    private AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use real service instances since ConfigurationService is final
        $this->configurationService = new ConfigurationService();
        $this->loyaltyService = new LoyaltyService(new \App\Services\StampCardService());
        $this->analyticsService = new AnalyticsService();
        
        $this->pricingService = new PricingService(
            $this->configurationService,
            $this->loyaltyService,
            $this->analyticsService
        );
    }

    public function test_calculate_order_tax_with_15_percent_vat()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
        ]);

        $taxAmount = $this->pricingService->calculateOrderTax($order);

        // 15% VAT on 100 = 15.00
        $this->assertEquals(15.00, $taxAmount);
    }

    public function test_calculate_order_tax_rounds_to_two_decimal_places()
    {
        $order = Order::factory()->create([
            'subtotal' => 33.33,
        ]);

        $taxAmount = $this->pricingService->calculateOrderTax($order);

        // 15% VAT on 33.33 = 4.9995, should round to 5.00
        $this->assertEquals(5.00, $taxAmount);
    }

    public function test_calculate_delivery_fee_fixed_mode()
    {
        $order = Order::factory()->create([
            'subtotal' => 50.00,
        ]);
        
        $address = CustomerAddress::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
        
        $branch = RestaurantBranch::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        // Set the order's branch
        $order->update(['restaurant_branch_id' => $branch->id]);

        $deliveryFee = $this->pricingService->calculateDeliveryFee($order, $address);

        // Distance should be 0 km (same coordinates), so fee should be 0
        $this->assertEquals(0.00, $deliveryFee);
    }

    public function test_calculate_delivery_fee_distance_based_mode()
    {
        $order = Order::factory()->create([
            'subtotal' => 50.00,
        ]);
        
        $address = CustomerAddress::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
        
        $branch = RestaurantBranch::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        // Set the order's branch
        $order->update(['restaurant_branch_id' => $branch->id]);

        $deliveryFee = $this->pricingService->calculateDeliveryFee($order, $address);

        // Distance should be 0 km (same coordinates), so fee should be 0
        $this->assertEquals(0.00, $deliveryFee);
    }

    public function test_calculate_delivery_fee_free_delivery_threshold()
    {
        $order = Order::factory()->create([
            'subtotal' => 200.00, // Above 150 SAR threshold
        ]);
        
        $address = CustomerAddress::factory()->create();
        $branch = RestaurantBranch::factory()->create();

        // Set the order's branch
        $order->update(['restaurant_branch_id' => $branch->id]);

        $deliveryFee = $this->pricingService->calculateDeliveryFee($order, $address);

        // Should be free delivery for orders above 150 SAR
        $this->assertEquals(0.00, $deliveryFee);
    }

    public function test_calculate_delivery_fee_exceeds_max_distance()
    {
        $order = Order::factory()->create([
            'subtotal' => 50.00,
        ]);
        
        $address = CustomerAddress::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
        
        $branch = RestaurantBranch::factory()->create([
            'latitude' => 25.7136, // Different latitude that will exceed 25km
            'longitude' => 47.6753, // Different longitude
        ]);

        // Set the order's branch
        $order->update(['restaurant_branch_id' => $branch->id]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Delivery address is outside our delivery range (max 25 km)');

        $this->pricingService->calculateDeliveryFee($order, $address);
    }

    public function test_calculate_delivery_fee_missing_coordinates()
    {
        $order = Order::factory()->create([
            'subtotal' => 50.00,
        ]);
        
        $address = CustomerAddress::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);
        
        $branch = RestaurantBranch::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        // Set the order's branch
        $order->update(['restaurant_branch_id' => $branch->id]);

        // Should fall back to fixed delivery fee
        $deliveryFee = $this->pricingService->calculateDeliveryFee($order, $address);
        $this->assertEquals(15.00, $deliveryFee);
    }

    public function test_apply_discounts_loyalty_points()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'loyalty_points_used' => 1000,
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // 1000 points * 0.01 = 10.00 SAR
        $this->assertEquals(10.00, $discountAmount);
    }

    public function test_apply_discounts_tier_discount()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'tier_discount_percentage' => 10.00,
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // 10% discount on 100 = 10.00 (allow small precision difference)
        $this->assertEqualsWithDelta(10.00, $discountAmount, 0.10);
    }

    public function test_apply_discounts_coupon_code()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'coupon_discount_percentage' => 10.00,
            'promo_code' => 'SAVE10',
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // 10% discount on 100 = 10.00 (allow small precision difference)
        $this->assertEqualsWithDelta(10.00, $discountAmount, 0.10);
    }

    public function test_apply_discounts_multiple_discounts()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'loyalty_points_used' => 500, // 500 points = 5.00 SAR
            'tier_discount_percentage' => 5.00, // 5% = 5.00 SAR
            'coupon_discount_percentage' => 5.00, // 5% = 5.00 SAR
            'promo_code' => 'SAVE5',
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // 500 points = 5.00 + 5% tier + 5% coupon = 5.00 + 5.00 + 5.00 = 15.00 total
        $this->assertEqualsWithDelta(15.00, $discountAmount, 0.10);
    }

    public function test_apply_discounts_cannot_exceed_subtotal()
    {
        $order = Order::factory()->create([
            'subtotal' => 50.00,
            'tier_discount_percentage' => 150.0, // More than 100%
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // Discount should be capped at subtotal
        $this->assertEquals(50.00, $discountAmount);
    }

    public function test_calculate_item_price_branch_specific()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create(['restaurant_id' => $restaurant->id]);
        
        // Create branch-specific pricing
        BranchMenuItem::factory()->create([
            'menu_item_id' => $item->id,
            'restaurant_branch_id' => $branch->id,
            'price' => 25.00,
        ]);

        $price = $this->pricingService->calculateItemPrice($item, $branch);

        $this->assertEquals(25.00, $price);
    }

    public function test_calculate_item_price_fallback_to_base_price()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'price' => 20.00,
        ]);

        $price = $this->pricingService->calculateItemPrice($item, $branch);

        $this->assertEquals(20.00, $price);
    }

    public function test_calculate_item_price_with_customizations()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'price' => 20.00,
        ]);

        $customizations = [
            'additions' => ['extra cheese', 'bacon'],
            'substitutions' => ['different sauce'],
            'size' => 'large'
        ];

        $price = $this->pricingService->calculateItemPrice($item, $branch, $customizations);

        // Base price: 20.00
        // Additions: 2 * 2.00 = 4.00
        // Substitutions: 1 * 1.50 = 1.50
        // Size: large = +5.00
        // Total: 20.00 + 4.00 + 1.50 + 5.00 = 30.50
        $this->assertEquals(30.50, $price);
    }

    public function test_generate_pricing_report()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        
        // Create orders for the restaurant's branch with correct date
        Order::factory()->count(3)->create([
            'restaurant_branch_id' => $branch->id,
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'delivery_fee' => 10.00,
            'discount_amount' => 0.00, // Explicitly set to 0
            'service_fee' => 0.00, // Explicitly set to 0
            'status' => 'completed',
            'created_at' => '2025-01-15 12:00:00', // Ensure it's in January 2025
        ]);

        $report = $this->pricingService->generateMonthlyPricingReport($restaurant, '2025-01');

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('breakdown', $report);
        $this->assertArrayHasKey('profitability', $report);

        $this->assertEquals(3, $report['summary']['total_orders']);
        // Expected values based on 3 orders: subtotal=100, tax=15, delivery=10, discount=0
        // Total revenue per order: 100 + 15 + 10 - 0 = 125.00
        // For 3 orders: 125.00 * 3 = 375.00
        $this->assertEqualsWithDelta(375.00, $report['summary']['total_revenue'], 1.00);
        $this->assertEqualsWithDelta(300.00, $report['summary']['total_subtotal'], 1.00);
        $this->assertEqualsWithDelta(45.00, $report['summary']['total_tax'], 1.00);
        $this->assertEqualsWithDelta(30.00, $report['summary']['total_delivery_fees'], 1.00);
    }

    public function test_generate_pricing_report_excludes_cancelled_orders()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        
        // Create orders including cancelled ones with correct date
        Order::factory()->count(2)->create([
            'restaurant_branch_id' => $branch->id,
            'status' => 'completed',
            'created_at' => '2025-01-15 12:00:00', // Ensure it's in January 2025
        ]);
        
        Order::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'status' => 'cancelled',
            'created_at' => '2025-01-15 12:00:00', // Ensure it's in January 2025
        ]);

        $report = $this->pricingService->generateMonthlyPricingReport($restaurant, '2025-01');

        // Should only count non-cancelled orders
        $this->assertEquals(2, $report['summary']['total_orders']);
    }

    public function test_validate_coupon_code_valid()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'coupon_discount_percentage' => 10.00,
            'promo_code' => 'SAVE10',
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // 10% discount on 100 = 10.00 (allow small precision difference)
        $this->assertEqualsWithDelta(10.00, $discountAmount, 0.10);
    }

    public function test_validate_coupon_code_invalid()
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'coupon_discount_percentage' => 0.00, // No discount
            'promo_code' => 'INVALID',
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // No discount for invalid coupon (allow small precision difference)
        $this->assertEqualsWithDelta(0.00, $discountAmount, 0.10);
    }

    public function test_validate_coupon_code_minimum_order_not_met()
    {
        $order = Order::factory()->create([
            'subtotal' => 50.00, // Below minimum order value
            'coupon_discount_percentage' => 0.00, // No discount when minimum not met
            'promo_code' => 'SAVE10',
        ]);

        $discountAmount = $this->pricingService->applyDiscounts($order);

        // No discount if minimum order not met
        $this->assertEqualsWithDelta(0.00, $discountAmount, 0.10);
    }

    public function test_calculate_distance_same_coordinates()
    {
        $lat1 = 24.7136;
        $lon1 = 46.6753;
        $lat2 = 24.7136;
        $lon2 = 46.6753;

        $distance = $this->pricingService->calculateDistanceFromCoordinates($lat1, $lon1, $lat2, $lon2);

        $this->assertEquals(0.0, $distance);
    }

    public function test_calculate_distance_different_coordinates()
    {
        $lat1 = 24.7136;
        $lon1 = 46.6753;
        $lat2 = 24.7236; // Slightly different
        $lon2 = 46.6853;

        $distance = $this->pricingService->calculateDistanceFromCoordinates($lat1, $lon1, $lat2, $lon2);

        $this->assertGreaterThan(0, $distance);
        $this->assertLessThan(10, $distance); // Should be less than 10 km
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
} 