<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SpinWheelPrize extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'spin_wheel_id',
        'name',
        'description',
        'type',
        'value',
        'value_type',
        'probability',
        'max_redemptions',
        'current_redemptions',
        'is_active',
        'tier_restrictions',
        'conditions',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'probability' => 'decimal:4',
            'max_redemptions' => 'integer',
            'current_redemptions' => 'integer',
            'is_active' => 'boolean',
            'tier_restrictions' => 'array',
            'conditions' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the spin wheel that owns the prize.
     */
    public function spinWheel(): BelongsTo
    {
        return $this->belongsTo(SpinWheel::class);
    }

    /**
     * Get the spin results for this prize.
     */
    public function spinResults(): HasMany
    {
        return $this->hasMany(SpinResult::class);
    }

    /**
     * Check if the prize is available for redemption.
     */
    public function getIsAvailableAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->max_redemptions && $this->current_redemptions >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    /**
     * Check if a customer tier can win this prize.
     */
    public function isAvailableForTier(int $tierLevel): bool
    {
        if (empty($this->tier_restrictions)) {
            return true;
        }

        return in_array($tierLevel, $this->tier_restrictions);
    }

    /**
     * Get adjusted probability for a specific tier.
     */
    public function getAdjustedProbabilityForTier(int $tierLevel): float
    {
        $baseProbability = (float) $this->probability;
        $spinWheel = $this->spinWheel;
        $boost = $spinWheel->getProbabilityBoostForTier($tierLevel);
        
        return min(1.0, $baseProbability * $boost);
    }

    /**
     * Increment the redemption count.
     */
    public function incrementRedemption(): void
    {
        if ($this->max_redemptions) {
            $this->increment('current_redemptions');
        }
    }

    /**
     * Get the prize display value.
     */
    public function getDisplayValueAttribute(): string
    {
        switch ($this->type) {
            case 'discount':
                return $this->value . '%';
            case 'bonus_points':
                return $this->value . ' points';
            case 'free_delivery':
                return 'Free Delivery';
            case 'cashback':
                return '$' . number_format($this->value, 2);
            case 'free_item':
                return 'Free Item';
            default:
                return (string) $this->value;
        }
    }

    /**
     * Scope a query to only include active prizes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include available prizes.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('max_redemptions')
                          ->orWhereRaw('current_redemptions < max_redemptions');
                    });
    }
} 