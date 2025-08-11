<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use App\Models\Restaurant;
use App\Models\BranchMenuItem;
use App\Models\LoyaltyTier;
use App\Models\CustomerLoyaltyPoint;
use App\Models\SpinWheelPrize;
use App\Exceptions\BusinessLogicException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Pricing Service for FoodHub Application
 * 
 * Handles all pricing calculations including:
 * - Tax calculation (15% VAT for Saudi Arabia)
 * - Delivery fee calculation (fixed or distance-based)
 * - Discount application (loyalty points, tier discounts, coupons)
 * - Item price calculation with customizations
 * - Pricing reports and analytics
 */
class PricingService
{
    /**
     * VAT rate for Saudi Arabia
     */
    private const VAT_RATE = 0.15; // 15%

    /**
     * Points per SAR for loyalty calculations
     */
    private const POINTS_PER_SAR = 100.0;

    /**
     * Default delivery settings
     */
    private const DEFAULT_DELIVERY_SETTINGS = [
        'delivery_fee_type' => 'fixed',
        'fixed_delivery_fee' => 15.00,
        'per_km_rate' => 2.50,
        'max_delivery_distance' => 25.0,
        'free_delivery_threshold' => 100.00,
    ];

    /**
     * Available coupon codes
     */
    private const COUPON_CODES = [
        'WELCOME10' => [
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order' => 50.00,
        ],
        'SAVE20' => [
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'minimum_order' => 100.00,
        ],
        'FREEDELIVERY' => [
            'discount_type' => 'delivery_fee',
            'discount_value' => 100,
            'minimum_order' => 50.00,
        ],
    ];

    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly LoyaltyService $loyaltyService,
        private readonly AnalyticsService $analyticsService,
    ) {}

    /**
     * Calculate order tax (15% VAT for Saudi Arabia)
     */
    public function calculateOrderTax(Order $order): float
    {
        $subtotal = (float) $order->subtotal;
        $deliveryFee = (float) $order->delivery_fee;
        $taxRate = 0.15; // 15% VAT for Saudi Arabia
        
        return round(($subtotal + $deliveryFee) * $taxRate, 2);
    }

    /**
     * Calculate delivery fee based on distance or fixed rate
     */
    public function calculateDeliveryFee(Order $order, CustomerAddress $address): float
    {
        $subtotal = (float) $order->subtotal;
        
        // Free delivery for orders above 150 SAR
        if ($subtotal >= 150.00) {
            return 0.00;
        }
        
        // Check if coordinates are available for distance-based calculation
        if ($address->latitude && $address->longitude && $order->restaurant_branch_id) {
            $branch = RestaurantBranch::find($order->restaurant_branch_id);
            
            if ($branch && $branch->latitude && $branch->longitude) {
                $distance = $this->calculateDistance(
                    (float) $address->latitude,
                    (float) $address->longitude,
                    (float) $branch->latitude,
                    (float) $branch->longitude
                );
                
                // Max delivery distance is 25km
                if ($distance > 25.0) {
                    throw new BusinessLogicException('Delivery address is outside our delivery range (max 25 km)');
                }
                
                // Distance-based pricing: 2.50 SAR per km
                $distanceFee = $distance * 2.50;
                return round($distanceFee, 2);
            }
        }
        
        // Fixed delivery fee for orders below 150 SAR when coordinates aren't available
        return 15.00;
    }

    /**
     * Apply discounts to an order
     */
    public function applyDiscounts(Order $order): float
    {
        $totalDiscount = 0.0;
        $subtotal = (float) $order->subtotal;

        // Loyalty points discount
        if ($order->loyalty_points_used > 0) {
            $loyaltyDiscount = $this->calculateLoyaltyPointsDiscount($order);
            $totalDiscount += $loyaltyDiscount;
        }

        // Tier discount
        if ($order->tier_discount_percentage > 0) {
            $tierDiscount = ($subtotal * (float) $order->tier_discount_percentage) / 100;
            $totalDiscount += $tierDiscount;
        }

        // Coupon discount
        if ($order->promo_code && $order->coupon_discount_percentage > 0) {
            $couponDiscount = ($subtotal * (float) $order->coupon_discount_percentage) / 100;
            $totalDiscount += $couponDiscount;
        }

        // Ensure discount doesn't exceed subtotal
        return min($totalDiscount, $subtotal);
    }

    /**
     * Calculate item price with customizations
     */
    public function calculateItemPrice(MenuItem $item, RestaurantBranch $branch, array $customizations = []): float
    {
        // Get branch-specific price if available
        $branchMenuItem = BranchMenuItem::where('menu_item_id', $item->id)
            ->where('restaurant_branch_id', $branch->id)
            ->first();

        $basePrice = $branchMenuItem ? (float) $branchMenuItem->price : (float) $item->price;
        $totalPrice = $basePrice;

        // Add customization costs
        if (!empty($customizations)) {
            $customizationCost = $this->calculateCustomizationCost($customizations);
            $totalPrice += $customizationCost;
        }

        return round($totalPrice, 2);
    }

    /**
     * Generate monthly pricing report for a restaurant
     */
    public function generateMonthlyPricingReport(Restaurant $restaurant, string $period): array
    {
        $startDate = Carbon::parse($period . '-01');
        $endDate = $startDate->copy()->endOfMonth();
        
        // Get all orders for the restaurant's branches in the specified period
        $orders = Order::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->whereBetween('created_at', [$startDate, $endDate])
        ->where('status', '!=', 'cancelled')
        ->get();
        
        if ($orders->isEmpty()) {
            return [
                'summary' => [
                    'total_orders' => 0,
                    'total_revenue' => 0.00,
                    'total_subtotal' => 0.00,
                    'total_tax' => 0.00,
                    'total_delivery_fees' => 0.00,
                    'total_discounts' => 0.00,
                ],
                'breakdown' => [],
                'profitability' => [
                    'profit_margin' => 0.00,
                    'average_order_value' => 0.00,
                ]
            ];
        }
        
        $totalRevenue = 0.00;
        $totalSubtotal = 0.00;
        $totalTax = 0.00;
        $totalDeliveryFees = 0.00;
        $totalDiscounts = 0.00;
        
        foreach ($orders as $order) {
            $subtotal = (float) $order->subtotal;
            $tax = (float) $order->tax_amount;
            $deliveryFee = (float) $order->delivery_fee;
            $discount = (float) $order->discount_amount;
            
            $totalSubtotal += $subtotal;
            $totalTax += $tax;
            $totalDeliveryFees += $deliveryFee;
            $totalDiscounts += $discount;
            $totalRevenue += $subtotal + $tax + $deliveryFee - $discount;
        }
        
        $averageOrderValue = $totalRevenue / $orders->count();
        $profitMargin = $totalRevenue > 0 ? (($totalRevenue - $totalSubtotal) / $totalRevenue) * 100 : 0;
        
        return [
            'summary' => [
                'total_orders' => $orders->count(),
                'total_revenue' => round($totalRevenue, 2),
                'total_subtotal' => round($totalSubtotal, 2),
                'total_tax' => round($totalTax, 2),
                'total_delivery_fees' => round($totalDeliveryFees, 2),
                'total_discounts' => round($totalDiscounts, 2),
            ],
            'breakdown' => [
                'by_status' => $orders->groupBy('status')->map->count(),
                'by_branch' => $orders->groupBy('restaurant_branch_id')->map->count(),
            ],
            'profitability' => [
                'profit_margin' => round($profitMargin, 2),
                'average_order_value' => round($averageOrderValue, 2),
            ]
        ];
    }

    /**
     * Validate coupon code
     */
    public function validateCouponCode(string $couponCode, float $orderSubtotal): array
    {
        // Basic coupon validation logic
        $validCoupons = [
            'SAVE10' => ['discount' => 10.00, 'min_order' => 50.00],
            'SAVE20' => ['discount' => 20.00, 'min_order' => 100.00],
            'FREEDEL' => ['discount' => 15.00, 'min_order' => 75.00],
        ];

        if (!isset($validCoupons[$couponCode])) {
            return ['valid' => false, 'message' => 'Invalid coupon code'];
        }

        $coupon = $validCoupons[$couponCode];
        
        if ($orderSubtotal < $coupon['min_order']) {
            return [
                'valid' => false, 
                'message' => "Minimum order amount of {$coupon['min_order']} SAR required"
            ];
        }

        return [
            'valid' => true,
            'discount' => $coupon['discount'],
            'message' => 'Coupon applied successfully'
        ];
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Haversine formula for distance calculation
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $dlat = $lat2Rad - $lat1Rad;
        $dlon = $lon2Rad - $lon1Rad;

        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1Rad) * cos($lat2Rad) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Earth's radius in kilometers
        $radius = 6371;

        return round($radius * $c, 2);
    }

    /**
     * Calculate distance between two coordinate pairs (for testing)
     */
    public function calculateDistanceFromCoordinates(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Haversine formula for distance calculation
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $dlat = $lat2Rad - $lat1Rad;
        $dlon = $lon2Rad - $lon1Rad;

        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1Rad) * cos($lat2Rad) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Earth's radius in kilometers
        $radius = 6371;

        return round($radius * $c, 2);
    }

    /**
     * Calculate customization cost
     */
    private function calculateCustomizationCost(array $customizations): float
    {
        $totalCost = 0.0;

        // Additions cost (e.g., extra toppings)
        if (isset($customizations['additions']) && is_array($customizations['additions'])) {
            $totalCost += count($customizations['additions']) * 2.00; // 2 SAR per addition
        }

        // Substitutions cost (e.g., different cheese)
        if (isset($customizations['substitutions']) && is_array($customizations['substitutions'])) {
            $totalCost += count($customizations['substitutions']) * 1.50; // 1.50 SAR per substitution
        }

        // Size changes cost
        if (isset($customizations['size'])) {
            switch ($customizations['size']) {
                case 'large':
                    $totalCost += 5.00; // 5 SAR extra for large
                    break;
                case 'medium':
                    $totalCost += 2.50; // 2.50 SAR extra for medium
                    break;
                case 'small':
                    $totalCost -= 2.00; // 2 SAR discount for small
                    break;
            }
        }

        return max(0, $totalCost); // Ensure cost doesn't go negative
    }

    /**
     * Calculate loyalty points discount
     */
    private function calculateLoyaltyPointsDiscount(Order $order): float
    {
        $pointsUsed = (float) $order->loyalty_points_used;
        $pointsToCurrencyRate = 0.01; // 1 point = 0.01 SAR
        
        return round($pointsUsed * $pointsToCurrencyRate, 2);
    }

    /**
     * Calculate tier discount
     */
    private function calculateTierDiscount(int $customerId, float $subtotal): float
    {
        $customerPoints = CustomerLoyaltyPoint::where('customer_id', $customerId)
            ->where('is_active', true)
            ->with('loyaltyTier')
            ->first();

        if (!$customerPoints || !$customerPoints->loyaltyTier) {
            return 0.0;
        }

        $discountPercentage = (float) $customerPoints->loyaltyTier->discount_percentage;
        return round(($subtotal * $discountPercentage) / 100, 2);
    }

    /**
     * Calculate coupon discount
     */
    private function calculateCouponDiscount(string $promoCode, float $subtotal): float
    {
        if (!isset(self::COUPON_CODES[$promoCode])) {
            return 0.0;
        }

        $coupon = self::COUPON_CODES[$promoCode];
        
        if ($subtotal < $coupon['minimum_order']) {
            return 0.0;
        }

        $discount = $coupon['discount_type'] === 'percentage' 
            ? ($subtotal * $coupon['discount_value'] / 100)
            : $coupon['discount_value'];

        return round($discount, 2);
    }

    /**
     * Get top items from orders
     */
    private function getTopItems($orders): array
    {
        // This would require order items relationship
        // For now, return empty array
        return [];
    }
} 