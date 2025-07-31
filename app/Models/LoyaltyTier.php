<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LoyaltyTier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'loyalty_program_id',
        'name',
        'display_name',
        'description',
        'min_points_required',
        'max_points_capacity',
        'points_multiplier',
        'discount_percentage',
        'free_delivery',
        'priority_support',
        'exclusive_offers',
        'birthday_reward',
        'additional_benefits',
        'color_code',
        'icon',
        'sort_order',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'min_points_required' => 'decimal:2',
            'max_points_capacity' => 'decimal:2',
            'points_multiplier' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'free_delivery' => 'boolean',
            'priority_support' => 'boolean',
            'exclusive_offers' => 'boolean',
            'birthday_reward' => 'boolean',
            'additional_benefits' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the loyalty program that owns the tier.
     */
    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    /**
     * Get the customer loyalty points for this tier.
     */
    public function customerLoyaltyPoints(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class);
    }

    /**
     * Get the next tier in progression.
     */
    public function getNextTierAttribute(): ?self
    {
        return $this->loyaltyProgram->loyaltyTiers()
            ->where('min_points_required', '>', $this->min_points_required)
            ->orderBy('min_points_required')
            ->first();
    }

    /**
     * Get the previous tier in progression.
     */
    public function getPreviousTierAttribute(): ?self
    {
        return $this->loyaltyProgram->loyaltyTiers()
            ->where('min_points_required', '<', $this->min_points_required)
            ->orderByDesc('min_points_required')
            ->first();
    }

    /**
     * Check if this is the highest tier.
     */
    public function getIsHighestTierAttribute(): bool
    {
        return !$this->next_tier;
    }

    /**
     * Check if this is the lowest tier.
     */
    public function getIsLowestTierAttribute(): bool
    {
        return !$this->previous_tier;
    }

    /**
     * Get all benefits as an array.
     */
    public function getAllBenefitsAttribute(): array
    {
        $benefits = [
            'discount_percentage' => $this->discount_percentage,
            'free_delivery' => $this->free_delivery,
            'priority_support' => $this->priority_support,
            'exclusive_offers' => $this->exclusive_offers,
            'birthday_reward' => $this->birthday_reward,
            'points_multiplier' => $this->points_multiplier,
        ];

        if ($this->additional_benefits) {
            $benefits = array_merge($benefits, $this->additional_benefits);
        }

        return $benefits;
    }

    /**
     * Calculate discount amount for an order.
     */
    public function calculateDiscount(float $orderAmount): float
    {
        if ($this->discount_percentage <= 0) {
            return 0.00;
        }

        return ($orderAmount * $this->discount_percentage) / 100;
    }

    /**
     * Check if customer qualifies for this tier.
     */
    public function customerQualifies(float $customerPoints): bool
    {
        return $customerPoints >= $this->min_points_required;
    }

    /**
     * Get tier level (1 for lowest, 5 for highest).
     */
    public function getTierLevelAttribute(): int
    {
        $tiers = $this->loyaltyProgram->loyaltyTiers()
            ->orderBy('min_points_required')
            ->pluck('id')
            ->toArray();

        return array_search($this->id, $tiers) + 1;
    }

    /**
     * Scope a query to only include active tiers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by tier progression.
     */
    public function scopeByProgression($query)
    {
        return $query->orderBy('min_points_required');
    }

    /**
     * Scope a query to get tiers for a specific points level.
     */
    public function scopeForPointsLevel($query, float $points)
    {
        return $query->where('min_points_required', '<=', $points)
                    ->orderByDesc('min_points_required');
    }
} 