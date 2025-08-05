<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

final class CustomerSpin extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'spin_wheel_id',
        'free_spins_remaining',
        'paid_spins_remaining',
        'total_spins_used',
        'daily_spins_used',
        'last_spin_date',
        'last_spin_at',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'free_spins_remaining' => 'integer',
            'paid_spins_remaining' => 'integer',
            'total_spins_used' => 'integer',
            'daily_spins_used' => 'integer',
            'last_spin_date' => 'date',
            'last_spin_at' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns the spins.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the spin wheel that owns the spins.
     */
    public function spinWheel(): BelongsTo
    {
        return $this->belongsTo(SpinWheel::class);
    }

    /**
     * Get the spin results for this customer.
     */
    public function spinResults(): HasMany
    {
        return $this->hasMany(SpinResult::class, 'customer_id', 'customer_id');
    }

    /**
     * Check if customer can spin today.
     */
    public function getCanSpinTodayAttribute(): bool
    {
        $today = Carbon::today();
        
        // Reset daily spins if it's a new day
        if ($this->last_spin_date && $this->last_spin_date->lt($today)) {
            $this->resetDailySpins();
        }

        return $this->daily_spins_used < $this->spinWheel->max_daily_spins;
    }

    /**
     * Check if customer has available spins.
     */
    public function getHasAvailableSpinsAttribute(): bool
    {
        return $this->free_spins_remaining > 0 || $this->paid_spins_remaining > 0;
    }

    /**
     * Get total available spins.
     */
    public function getTotalAvailableSpinsAttribute(): int
    {
        return $this->free_spins_remaining + $this->paid_spins_remaining;
    }

    /**
     * Reset daily spins for a new day.
     */
    public function resetDailySpins(): void
    {
        $today = Carbon::today();
        
        if (!$this->last_spin_date || $this->last_spin_date->lt($today)) {
            $this->daily_spins_used = 0;
            $this->last_spin_date = $today;
            $this->save();
        }
    }

    /**
     * Add free spins for the day.
     */
    public function addDailyFreeSpins(): void
    {
        $customer = $this->customer;
        $loyaltyPoints = $customer->loyaltyPoints()->first();
        
        if ($loyaltyPoints && $loyaltyPoints->loyaltyTier) {
            $tierLevel = $loyaltyPoints->loyaltyTier->tier_level;
            $dailySpins = $this->spinWheel->getDailyFreeSpinsForTier($tierLevel);
            
            $this->free_spins_remaining += $dailySpins;
            $this->save();
        }
    }

    /**
     * Use a free spin.
     */
    public function useFreeSpin(): bool
    {
        if ($this->free_spins_remaining > 0) {
            $this->free_spins_remaining--;
            $this->total_spins_used++;
            $this->daily_spins_used++;
            $this->last_spin_at = Carbon::now();
            $this->last_spin_date = Carbon::today();
            $this->save();
            return true;
        }
        
        return false;
    }

    /**
     * Use a paid spin.
     */
    public function usePaidSpin(): bool
    {
        if ($this->paid_spins_remaining > 0) {
            $this->paid_spins_remaining--;
            $this->total_spins_used++;
            $this->daily_spins_used++;
            $this->last_spin_at = Carbon::now();
            $this->last_spin_date = Carbon::today();
            $this->save();
            return true;
        }
        
        return false;
    }

    /**
     * Buy spins with loyalty points.
     */
    public function buySpins(int $quantity): bool
    {
        $customer = $this->customer;
        $loyaltyPoints = $customer->loyaltyPoints()->first();
        
        if (!$loyaltyPoints) {
            return false;
        }

        $totalCost = $this->spinWheel->spin_cost_points * $quantity;
        
        if ($loyaltyPoints->current_points >= $totalCost) {
            // Deduct points
            $loyaltyPoints->decrement('current_points', $totalCost);
            
            // Add paid spins
            $this->paid_spins_remaining += $quantity;
            $this->save();
            
            return true;
        }
        
        return false;
    }

    /**
     * Scope a query to only include active customer spins.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
} 