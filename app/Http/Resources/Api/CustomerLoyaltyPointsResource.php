<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerLoyaltyPointsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'loyalty_program_id' => $this->loyalty_program_id,
            'loyalty_tier_id' => $this->loyalty_tier_id,
            'current_points' => (float) $this->current_points,
            'total_points_earned' => (float) $this->total_points_earned,
            'total_points_redeemed' => (float) $this->total_points_redeemed,
            'total_points_expired' => (float) $this->total_points_expired,
            'last_points_earned_date' => $this->last_points_earned_date?->toDateString(),
            'last_points_redeemed_date' => $this->last_points_redeemed_date?->toDateString(),
            'points_expiry_date' => $this->points_expiry_date?->toDateString(),
            'is_active' => $this->is_active,
            'bonus_multipliers_used' => $this->bonus_multipliers_used,
            'redemption_history' => $this->redemption_history,
            'available_points' => (float) $this->available_points,
            'points_to_expire' => (float) $this->points_to_expire,
            'is_expired' => $this->is_expired,
            'next_tier_progress' => $this->next_tier_progress,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ];
            }),
            
            'loyalty_program' => $this->whenLoaded('loyaltyProgram', function () {
                return [
                    'id' => $this->loyaltyProgram->id,
                    'name' => $this->loyaltyProgram->name,
                    'description' => $this->loyaltyProgram->description,
                    'is_active' => $this->loyaltyProgram->is_active,
                ];
            }),
            
            'loyalty_tier' => $this->whenLoaded('loyaltyTier', function () {
                return [
                    'id' => $this->loyaltyTier->id,
                    'name' => $this->loyaltyTier->name,
                    'display_name' => $this->loyaltyTier->display_name,
                    'description' => $this->loyaltyTier->description,
                    'min_points_required' => (float) $this->loyaltyTier->min_points_required,
                    'points_multiplier' => (float) $this->loyaltyTier->points_multiplier,
                    'discount_percentage' => (float) $this->loyaltyTier->discount_percentage,
                    'free_delivery' => $this->loyaltyTier->free_delivery,
                    'priority_support' => $this->loyaltyTier->priority_support,
                    'exclusive_offers' => $this->loyaltyTier->exclusive_offers,
                    'birthday_reward' => $this->loyaltyTier->birthday_reward,
                    'color_code' => $this->loyaltyTier->color_code,
                    'icon' => $this->loyaltyTier->icon,
                ];
            }),
            
            'points_history' => $this->whenLoaded('pointsHistory', function () {
                return $this->pointsHistory->map(function ($history) {
                    return [
                        'id' => $history->id,
                        'transaction_type' => $history->transaction_type,
                        'transaction_type_display' => $history->transaction_type_display,
                        'points_amount' => (float) $history->points_amount,
                        'points_balance_after' => (float) $history->points_balance_after,
                        'description' => $history->description,
                        'source' => $history->source,
                        'source_display' => $history->source_display,
                        'multiplier_applied' => (float) $history->multiplier_applied,
                        'is_reversed' => $history->is_reversed,
                        'created_at' => $history->created_at?->toISOString(),
                    ];
                });
            }),
        ];
    }
} 