<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

final class CustomerLoyaltyPoint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'loyalty_program_id',
        'loyalty_tier_id',
        'current_points',
        'total_points_earned',
        'total_points_redeemed',
        'total_points_expired',
        'last_points_earned_date',
        'last_points_redeemed_date',
        'points_expiry_date',
        'is_active',
        'bonus_multipliers_used',
        'redemption_history',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'current_points' => 'decimal:2',
            'total_points_earned' => 'decimal:2',
            'total_points_redeemed' => 'decimal:2',
            'total_points_expired' => 'decimal:2',
            'last_points_earned_date' => 'date',
            'last_points_redeemed_date' => 'date',
            'points_expiry_date' => 'date',
            'is_active' => 'boolean',
            'bonus_multipliers_used' => 'array',
            'redemption_history' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns the loyalty points.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the loyalty program that owns the points.
     */
    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    /**
     * Get the current loyalty tier.
     */
    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class);
    }

    /**
     * Get the points history for this customer.
     */
    public function pointsHistory(): HasMany
    {
        return $this->hasMany(LoyaltyPointsHistory::class, 'customer_loyalty_points_id');
    }

    /**
     * Get the next tier progress.
     */
    public function getNextTierProgressAttribute(): array
    {
        $currentTier = $this->loyaltyTier;
        $nextTier = $this->loyaltyProgram->loyaltyTiers()
            ->where('min_points_required', '>', $this->current_points)
            ->orderBy('min_points_required')
            ->first();

        if (!$nextTier) {
            return [
                'next_tier' => null,
                'points_needed' => 0,
                'progress_percentage' => 100,
            ];
        }

        $pointsNeeded = $nextTier->min_points_required - $this->current_points;
        $progressPercentage = min(100, ($this->current_points / $nextTier->min_points_required) * 100);

        return [
            'next_tier' => $nextTier,
            'points_needed' => $pointsNeeded,
            'progress_percentage' => round($progressPercentage, 2),
        ];
    }

    /**
     * Check if points are expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->points_expiry_date && $this->points_expiry_date->isPast();
    }

    /**
     * Get available points (excluding expired).
     */
    public function getAvailablePointsAttribute(): float
    {
        if ($this->is_expired) {
            return 0.00;
        }
        return (float) $this->current_points;
    }

    /**
     * Calculate points to expire.
     */
    public function getPointsToExpireAttribute(): float
    {
        if (!$this->points_expiry_date || $this->points_expiry_date->isFuture()) {
            return 0.00;
        }

        // Points expire after 1 year
        $expiryDate = $this->points_expiry_date->addYear();
        if ($expiryDate->isPast()) {
            return (float) $this->current_points;
        }

        return 0.00;
    }

    /**
     * Scope a query to only include active loyalty points.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include non-expired points.
     */
    public function scopeNonExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('points_expiry_date')
              ->orWhere('points_expiry_date', '>', now());
        });
    }

    /**
     * Scope a query to only include points that will expire soon.
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('points_expiry_date', '<=', now()->addDays($days))
                    ->where('points_expiry_date', '>', now());
    }
} 