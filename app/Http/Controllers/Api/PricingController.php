<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\CalculatePricingRequest;
use App\Http\Requests\Pricing\GeneratePricingReportRequest;
use App\Http\Resources\Api\PricingResource;
use App\Http\Resources\Api\PricingReportResource;
use App\Services\PricingService;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use App\Models\Restaurant;
use App\Exceptions\BusinessLogicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Pricing Controller for FoodHub Application
 * 
 * Handles pricing calculations and reports via API endpoints
 */
final class PricingController extends Controller
{
    private PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Calculate order tax
     */
    public function calculateTax(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        
        try {
            $taxAmount = $this->pricingService->calculateOrderTax($order);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'tax_amount' => (float) $taxAmount,
                    'tax_rate' => 15.00, // Saudi VAT rate
                    'taxable_amount' => (float) ($order->subtotal + $order->delivery_fee),
                ],
                'message' => 'Tax calculated successfully',
            ]);
        } catch (BusinessLogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate delivery fee
     */
    public function calculateDeliveryFee(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'address_id' => 'required|exists:customer_addresses,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $address = CustomerAddress::findOrFail($request->address_id);
        
        // Check if coordinates are available
        if (!$address->latitude || !$address->longitude || !$order->branch->latitude || !$order->branch->longitude) {
            return response()->json([
                'success' => false,
                'message' => 'Coordinates are required for delivery fee calculation',
            ], 400);
        }
        
        try {
            $deliveryFee = $this->pricingService->calculateDeliveryFee($order, $address);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'address_id' => $address->id,
                    'delivery_fee' => (float) $deliveryFee,
                    'distance' => (float) $this->calculateDistance($address, $order->branch),
                ],
                'message' => 'Delivery fee calculated successfully',
            ]);
        } catch (BusinessLogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Apply discounts to order
     */
    public function applyDiscounts(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'coupons' => 'array',
            'coupons.*' => 'string',
        ]);

        $order = Order::findOrFail($request->order_id);
        $coupons = $request->input('coupons', []);
        
        try {
            $discountAmount = $this->pricingService->applyDiscounts($order, $coupons);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'discount_amount' => $discountAmount,
                    'subtotal' => $order->subtotal,
                    'discount_percentage' => $order->subtotal > 0 ? ($discountAmount / $order->subtotal) * 100 : 0,
                ],
                'message' => 'Discounts applied successfully',
            ]);
        } catch (BusinessLogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate item price with customizations
     */
    public function calculateItemPrice(Request $request): JsonResponse
    {
        $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'branch_id' => 'required|exists:restaurant_branches,id',
            'customizations' => 'array',
            'customizations.additions' => 'array',
            'customizations.additions.*.name' => 'string',
            'customizations.additions.*.price' => 'numeric|min:0',
            'customizations.substitutions' => 'array',
            'customizations.substitutions.*.name' => 'string',
            'customizations.substitutions.*.original_price' => 'numeric|min:0',
            'customizations.substitutions.*.new_price' => 'numeric|min:0',
            'customizations.size.name' => 'string',
            'customizations.size.price_modifier' => 'numeric',
        ]);

        $item = MenuItem::findOrFail($request->menu_item_id);
        $branch = RestaurantBranch::findOrFail($request->branch_id);
        $customizations = $request->input('customizations', []);
        
        try {
            $price = $this->pricingService->calculateItemPrice($item, $branch, $customizations);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'menu_item_id' => $item->id,
                    'branch_id' => $branch->id,
                    'base_price' => (float) $item->price,
                    'final_price' => (float) $price,
                    'customizations' => $customizations,
                    'customization_cost' => (float) ($price - $item->price),
                ],
                'message' => 'Item price calculated successfully',
            ]);
        } catch (BusinessLogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate pricing report
     */
    public function generatePricingReport(GeneratePricingReportRequest $request): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $period = Carbon::createFromFormat('Y-m', $request->period);
        
        try {
            $report = $this->pricingService->generateMonthlyPricingReport($restaurant, $period->format('Y-m'));
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Pricing report generated successfully',
            ]);
        } catch (BusinessLogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate complete order pricing
     */
    public function calculateCompletePricing(CalculatePricingRequest $request): JsonResponse
    {
        $order = Order::findOrFail($request->order_id);
        $address = CustomerAddress::findOrFail($request->address_id);
        
        try {
            // Calculate delivery fee
            $deliveryFee = $this->pricingService->calculateDeliveryFee($order, $address);
            
            // Calculate tax
            $taxAmount = $this->pricingService->calculateOrderTax($order);
            
            // Process coupons and update order discount percentage
            if (!empty($request->coupons)) {
                $couponCode = $request->coupons[0]; // Use first coupon for now
                $coupons = [
                    'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'min_order' => 50],
                    'SAVE20' => ['type' => 'fixed', 'value' => 20, 'min_order' => 100],
                    'HAPPYHOUR' => ['type' => 'percentage', 'value' => 15, 'min_order' => 30],
                ];
                
                if (isset($coupons[$couponCode]) && $order->subtotal >= $coupons[$couponCode]['min_order']) {
                    $order->coupon_discount_percentage = $coupons[$couponCode]['value'];
                    $order->promo_code = $couponCode;
                }
            }
            
            // Apply discounts
            $discountAmount = $this->pricingService->applyDiscounts($order);
            
            // Calculate total
            $totalAmount = $order->subtotal + $deliveryFee + $taxAmount - $discountAmount;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'subtotal' => (float) $order->subtotal,
                    'delivery_fee' => (float) $deliveryFee,
                    'tax_amount' => (float) $taxAmount,
                    'discount_amount' => (float) $discountAmount,
                    'total_amount' => (float) $totalAmount,
                    'breakdown' => [
                        'items_total' => (float) $order->subtotal,
                        'delivery_cost' => (float) $deliveryFee,
                        'tax_cost' => (float) $taxAmount,
                        'discount_savings' => (float) $discountAmount,
                    ],
                ],
                'message' => 'Complete pricing calculated successfully',
            ]);
        } catch (BusinessLogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate coupon code
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code' => 'required|string|max:255',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $couponCode = strtoupper($request->coupon_code);
        $subtotal = $request->subtotal;
        
        // Basic coupon validation (same logic as in PricingService)
        $coupons = [
            'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'min_order' => 50],
            'SAVE20' => ['type' => 'fixed', 'value' => 20, 'min_order' => 100],
            'HAPPYHOUR' => ['type' => 'percentage', 'value' => 15, 'min_order' => 30],
        ];

        $coupon = $coupons[$couponCode] ?? null;
        
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code',
            ], 400);
        }

        if ($subtotal < $coupon['min_order']) {
            return response()->json([
                'success' => false,
                'message' => "Minimum order amount of {$coupon['min_order']} SAR required",
            ], 400);
        }

        $discountAmount = $coupon['type'] === 'percentage' 
            ? ($subtotal * $coupon['value']) / 100 
            : min($coupon['value'], $subtotal);

        return response()->json([
            'success' => true,
            'data' => [
                'coupon_code' => $couponCode,
                'discount_amount' => $discountAmount,
                'discount_type' => $coupon['type'],
                'discount_value' => $coupon['value'],
                'minimum_order' => $coupon['min_order'],
                'valid' => true,
            ],
            'message' => 'Coupon code is valid',
        ]);
    }

    /**
     * Helper method to calculate distance (duplicate of PricingService method)
     */
    private function calculateDistance(CustomerAddress $address, RestaurantBranch $branch): float
    {
        if (!$address->latitude || !$address->longitude || !$branch->latitude || !$branch->longitude) {
            return 0.00;
        }

        $lat1 = deg2rad((float) $address->latitude);
        $lon1 = deg2rad((float) $address->longitude);
        $lat2 = deg2rad((float) $branch->latitude);
        $lon2 = deg2rad((float) $branch->longitude);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = 6371 * $c;

        return round($distance, 2);
    }
} 