<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StampCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'customer_id',
        'card_type',
        'stamps_earned',
        'stamps_required',
        'is_completed',
        'completed_at',
        'reward_description',
        'reward_value',
        'is_active'
    ];

    protected $casts = [
        'stamps_earned' => 'integer',
        'stamps_required' => 'integer',
        'is_completed' => 'boolean',
        'is_active' => 'boolean',
        'completed_at' => 'datetime',
        'reward_value' => 'decimal:2',
    ];

    // Card types constants
    public const TYPE_GENERAL = 'general';
    public const TYPE_BEVERAGES = 'beverages';
    public const TYPE_DESSERTS = 'desserts';
    public const TYPE_MAINS = 'mains';
    public const TYPE_HEALTHY = 'healthy';

    public static function getCardTypes(): array
    {
        return [
            self::TYPE_GENERAL => 'General',
            self::TYPE_BEVERAGES => 'Beverages',
            self::TYPE_DESSERTS => 'Desserts',
            self::TYPE_MAINS => 'Main Courses',
            self::TYPE_HEALTHY => 'Healthy Options',
        ];
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the stamp history for this card
     */
    public function stampHistory(): HasMany
    {
        return $this->hasMany(StampHistory::class);
    }

    /**
     * Check if the stamp card is completed
     */
    public function isCompleted(): bool
    {
        return $this->stamps_earned >= $this->stamps_required;
    }

    /**
     * Get the progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->stamps_required === 0) {
            return 0;
        }
        
        return min(100, ($this->stamps_earned / $this->stamps_required) * 100);
    }

    /**
     * Get remaining stamps needed
     */
    public function getRemainingStamps(): int
    {
        return max(0, $this->stamps_required - $this->stamps_earned);
    }

    /**
     * Scope to get active stamp cards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get incomplete stamp cards
     */
    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope to get completed stamp cards
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }
} 