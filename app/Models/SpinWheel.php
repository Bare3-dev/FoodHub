<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

final class SpinWheel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'daily_free_spins_base',
        'max_daily_spins',
        'spin_cost_points',
        'tier_spin_multipliers',
        'tier_probability_boost',
        'starts_at',
        'ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_free_spins_base' => 'integer',
            'max_daily_spins' => 'integer',
            'spin_cost_points' => 'decimal:2',
            'tier_spin_multipliers' => 'array',
            'tier_probability_boost' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the prizes for this spin wheel.
     */
    public function prizes(): HasMany
    {
        return $this->hasMany(SpinWheelPrize::class);
    }

    /**
     * Get the active prizes for this spin wheel.
     */
    public function activePrizes(): HasMany
    {
        return $this->hasMany(SpinWheelPrize::class)->where('is_active', true);
    }

    /**
     * Get the customer spins for this wheel.
     */
    public function customerSpins(): HasMany
    {
        return $this->hasMany(CustomerSpin::class);
    }

    /**
     * Get the spin results for this wheel.
     */
    public function spinResults(): HasMany
    {
        return $this->hasMany(SpinResult::class);
    }

    /**
     * Check if the spin wheel is currently active.
     */
    public function getIsCurrentlyActiveAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();
        
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Get daily free spins for a specific tier.
     */
    public function getDailyFreeSpinsForTier(int $tierLevel): int
    {
        $multipliers = $this->tier_spin_multipliers ?? [];
        $multiplier = $multipliers[$tierLevel] ?? 1.0;
        
        return (int) ($this->daily_free_spins_base * $multiplier);
    }

    /**
     * Get probability boost for a specific tier.
     */
    public function getProbabilityBoostForTier(int $tierLevel): float
    {
        $boosts = $this->tier_probability_boost ?? [];
        return $boosts[$tierLevel] ?? 1.0;
    }

    /**
     * Scope a query to only include active spin wheels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include currently active spin wheels.
     */
    public function scopeCurrentlyActive($query)
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('starts_at')
                          ->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>=', $now);
                    });
    }
} 