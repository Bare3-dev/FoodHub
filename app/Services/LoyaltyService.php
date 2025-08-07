<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyPointsHistory;
use App\Services\StampCardService;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    private StampCardService $stampCardService;

    public function __construct(StampCardService $stampCardService)
    {
        $this->stampCardService = $stampCardService;
    }

    /**
     * Calculate loyalty points earned for an order
     */
    public function calculatePointsEarned(Order $order): float
    {
        // Get the customer's loyalty program
        $customerLoyaltyPoints = CustomerLoyaltyPoint::where('customer_id', $order->customer_id)
            ->where('is_active', true)
            ->first();

        if (!$customerLoyaltyPoints) {
            return 0.00;
        }

        $loyaltyProgram = $customerLoyaltyPoints->loyaltyProgram;

        // Check if loyalty program is active
        if (!$loyaltyProgram || !$loyaltyProgram->is_active) {
            return 0.00;
        }

        // Check minimum spend requirement
        if ($order->subtotal < $loyaltyProgram->minimum_spend_for_points) {
            return 0.00;
        }

        // Calculate base points
        $basePoints = $order->subtotal * $loyaltyProgram->points_per_dollar;

        // Apply tier multiplier if customer has a tier
        $multiplier = 1.00;
        if ($customerLoyaltyPoints->loyalty_tier_id) {
            $tier = $customerLoyaltyPoints->loyaltyTier;
            if ($tier) {
                $multiplier = $tier->points_multiplier;
            }
        }

        // Apply promotional multiplier if promo code is used
        $promoMultiplier = $this->calculatePromoMultiplier($order, $loyaltyProgram);

        $totalPoints = $basePoints * $multiplier * $promoMultiplier;

        return round($totalPoints, 2);
    }

    /**
     * Calculate promotional multiplier based on promo code
     */
    private function calculatePromoMultiplier(Order $order, LoyaltyProgram $loyaltyProgram): float
    {
        if (!$order->promo_code) {
            return 1.00;
        }

        $bonusMultipliers = $loyaltyProgram->bonus_multipliers ?? [];

        // Check for specific promo code multipliers
        switch (strtoupper($order->promo_code)) {
            case 'HAPPYHOUR':
                return $bonusMultipliers['happy_hour'] ?? 2.0;
            case 'BIRTHDAY':
                return $bonusMultipliers['birthday'] ?? 3.0;
            case 'FIRSTORDER':
                return $bonusMultipliers['first_order'] ?? 1.5;
            default:
                return 1.00;
        }
    }

    /**
     * Process loyalty points for an order
     */
    public function processOrderLoyaltyPoints(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Get customer loyalty points
            $customerLoyaltyPoints = CustomerLoyaltyPoint::where('customer_id', $order->customer_id)
                ->where('is_active', true)
                ->first();

            if (!$customerLoyaltyPoints) {
                return;
            }

            // Handle points expiration first
            $this->handlePointsExpirationForCustomer($customerLoyaltyPoints);

            // Calculate points earned
            $pointsEarned = $this->calculatePointsEarned($order);
            
            // Update order with calculated points
            $order->update([
                'loyalty_points_earned' => $pointsEarned
            ]);

            // Update customer loyalty points
            $customerLoyaltyPoints->update([
                'current_points' => $customerLoyaltyPoints->current_points + $pointsEarned,
                'total_points_earned' => $customerLoyaltyPoints->total_points_earned + $pointsEarned,
                'last_points_earned_date' => now()
            ]);

            // Handle tier progression after points update
            $this->handleTierProgression($customerLoyaltyPoints);

            // Create loyalty points history record
            if ($pointsEarned > 0) {
                LoyaltyPointsHistory::create([
                    'customer_loyalty_points_id' => $customerLoyaltyPoints->id,
                    'order_id' => $order->id,
                    'transaction_type' => 'earned',
                    'points_amount' => $pointsEarned,
                    'points_balance_after' => $customerLoyaltyPoints->current_points + $pointsEarned,
                    'description' => "Points earned from order #{$order->order_number}",
                    'source' => 'order',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'base_amount' => $order->subtotal,
                    'multiplier_applied' => $this->getAppliedMultiplier($order),
                    'transaction_details' => [
                        'order_number' => $order->order_number,
                        'subtotal' => $order->subtotal,
                        'promo_code' => $order->promo_code
                    ]
                ]);
            }

            // Process stamp cards for the order
            $this->stampCardService->addStampToCard($order);
        });
    }

    /**
     * Handle automatic tier progression based on current points
     */
    private function handleTierProgression(CustomerLoyaltyPoint $customerLoyaltyPoints): void
    {
        $loyaltyProgram = $customerLoyaltyPoints->loyaltyProgram;
        if (!$loyaltyProgram) {
            return;
        }

        // Get all tiers for this program, ordered by min_points_required
        $tiers = $loyaltyProgram->loyaltyTiers()
            ->orderBy('min_points_required', 'asc')
            ->get();

        if ($tiers->isEmpty()) {
            return;
        }

        $currentPoints = $customerLoyaltyPoints->current_points;
        $currentTier = $customerLoyaltyPoints->loyaltyTier;
        $newTier = null;

        // Find the highest tier the customer qualifies for
        foreach ($tiers as $tier) {
            if ($currentPoints >= $tier->min_points_required) {
                $newTier = $tier;
            } else {
                break; // Stop at first tier customer doesn't qualify for
            }
        }

        // Update tier if it changed
        if ($newTier && (!$currentTier || $currentTier->id !== $newTier->id)) {
            $oldTierId = $currentTier ? $currentTier->id : null;
            
            $customerLoyaltyPoints->update([
                'loyalty_tier_id' => $newTier->id
            ]);

            // Create history record for tier upgrade
            LoyaltyPointsHistory::create([
                'customer_loyalty_points_id' => $customerLoyaltyPoints->id,
                'transaction_type' => 'tier_upgrade',
                'points_amount' => 0,
                'points_balance_after' => $currentPoints,
                'description' => "Tier upgraded to {$newTier->display_name}",
                'source' => 'tier_progression',
                'transaction_details' => [
                    'old_tier_id' => $oldTierId,
                    'new_tier_id' => $newTier->id,
                    'new_tier_name' => $newTier->display_name,
                    'points_required' => $newTier->min_points_required,
                    'current_points' => $currentPoints
                ]
            ]);
        }
    }

    /**
     * Handle points expiration for a specific customer
     */
    private function handlePointsExpirationForCustomer(CustomerLoyaltyPoint $customerLoyaltyPoints): void
    {
        // Check if points are expired
        if ($customerLoyaltyPoints->points_expiry_date && $customerLoyaltyPoints->points_expiry_date < now()) {
            $expiredAmount = $customerLoyaltyPoints->current_points;

            // Update customer loyalty points
            $customerLoyaltyPoints->update([
                'current_points' => 0,
                'total_points_expired' => $customerLoyaltyPoints->total_points_expired + $expiredAmount
            ]);

            // Create loyalty points history record for expiration
            if ($expiredAmount > 0) {
                LoyaltyPointsHistory::create([
                    'customer_loyalty_points_id' => $customerLoyaltyPoints->id,
                    'transaction_type' => 'expired',
                    'points_amount' => -$expiredAmount,
                    'points_balance_after' => 0,
                    'description' => "Points expired",
                    'source' => 'expiration',
                    'transaction_details' => [
                        'expiry_date' => $customerLoyaltyPoints->points_expiry_date
                    ]
                ]);
            }
        }
    }

    /**
     * Get the applied multiplier for an order
     */
    private function getAppliedMultiplier(Order $order): float
    {
        $customerLoyaltyPoints = CustomerLoyaltyPoint::where('customer_id', $order->customer_id)
            ->where('is_active', true)
            ->first();

        if (!$customerLoyaltyPoints) {
            return 1.00;
        }

        $loyaltyProgram = $customerLoyaltyPoints->loyaltyProgram;
        if (!$loyaltyProgram) {
            return 1.00;
        }

        $tierMultiplier = 1.00;
        if ($customerLoyaltyPoints->loyalty_tier_id) {
            $tier = $customerLoyaltyPoints->loyaltyTier;
            if ($tier) {
                $tierMultiplier = $tier->points_multiplier;
            }
        }

        $promoMultiplier = $this->calculatePromoMultiplier($order, $loyaltyProgram);

        return $tierMultiplier * $promoMultiplier;
    }

    /**
     * Validate points redemption for an order
     */
    public function validatePointsRedemption(Order $order, float $pointsToUse): bool
    {
        $customerLoyaltyPoints = CustomerLoyaltyPoint::where('customer_id', $order->customer_id)
            ->where('is_active', true)
            ->first();

        if (!$customerLoyaltyPoints) {
            return false;
        }

        // Check if customer has enough points
        if ($customerLoyaltyPoints->current_points < $pointsToUse) {
            return false;
        }

        // Check if points are expired
        if ($customerLoyaltyPoints->points_expiry_date && $customerLoyaltyPoints->points_expiry_date < now()) {
            return false;
        }

        return true;
    }

    /**
     * Process points redemption for an order
     */
    public function processPointsRedemption(Order $order, float $pointsToUse): void
    {
        DB::transaction(function () use ($order, $pointsToUse) {
            $customerLoyaltyPoints = CustomerLoyaltyPoint::where('customer_id', $order->customer_id)
                ->where('is_active', true)
                ->first();

            if (!$customerLoyaltyPoints) {
                return;
            }

            // Update order with points used
            $order->update([
                'loyalty_points_used' => $pointsToUse
            ]);

            // Update customer loyalty points
            $customerLoyaltyPoints->update([
                'current_points' => $customerLoyaltyPoints->current_points - $pointsToUse,
                'total_points_redeemed' => $customerLoyaltyPoints->total_points_redeemed + $pointsToUse,
                'last_points_redeemed_date' => now()
            ]);

            // Create loyalty points history record
            LoyaltyPointsHistory::create([
                'customer_loyalty_points_id' => $customerLoyaltyPoints->id,
                'order_id' => $order->id,
                'transaction_type' => 'redeemed',
                'points_amount' => -$pointsToUse,
                'points_balance_after' => $customerLoyaltyPoints->current_points - $pointsToUse,
                'description' => "Points redeemed for order #{$order->order_number}",
                'source' => 'order',
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'transaction_details' => [
                    'order_number' => $order->order_number,
                    'discount_amount' => $order->discount_amount
                ]
            ]);
        });
    }

    /**
     * Handle points expiration
     */
    public function handlePointsExpiration(): void
    {
        $expiredPoints = CustomerLoyaltyPoint::where('points_expiry_date', '<', now())
            ->where('current_points', '>', 0)
            ->get();

        foreach ($expiredPoints as $customerPoints) {
            DB::transaction(function () use ($customerPoints) {
                $expiredAmount = $customerPoints->current_points;

                // Update customer loyalty points
                $customerPoints->update([
                    'current_points' => 0,
                    'total_points_expired' => $customerPoints->total_points_expired + $expiredAmount
                ]);

                // Create loyalty points history record
                LoyaltyPointsHistory::create([
                    'customer_loyalty_points_id' => $customerPoints->id,
                    'transaction_type' => 'expired',
                    'points_amount' => -$expiredAmount,
                    'points_balance_after' => 0,
                    'description' => "Points expired",
                    'source' => 'expiration',
                    'transaction_details' => [
                        'expiry_date' => $customerPoints->points_expiry_date
                    ]
                ]);
            });
        }
    }

    /**
     * Check if a stamp card is completed
     */
    public function checkStampCardCompletion($card): bool
    {
        return $this->stampCardService->checkStampCardCompletion($card);
    }

    /**
     * Add stamps to cards for an order
     */
    public function addStampToCard(Order $order): void
    {
        $this->stampCardService->addStampToCard($order);
    }
} 