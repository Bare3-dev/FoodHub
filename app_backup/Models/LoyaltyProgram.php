<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LoyaltyProgram extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'name',
        'description',
        'type',
        'is_active',
        'start_date',
        'end_date',
        'rules',
        'points_per_dollar',
        'dollar_per_point',
        'minimum_spend_for_points',
        'bonus_multipliers',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'rules' => 'array',
            'points_per_dollar' => 'decimal:2',
            'dollar_per_point' => 'decimal:2',
            'bonus_multipliers' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant that owns the loyalty program.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the customer loyalty points for the program.
     */
    public function customerLoyaltyPoints(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class);
    }

    /**
     * Get the loyalty tiers for the program.
     */
    public function loyaltyTiers(): HasMany
    {
        return $this->hasMany(LoyaltyTier::class);
    }

    /**
     * Get the stamp cards for the program.
     */
    public function stampCards(): HasMany
    {
        return $this->hasMany(StampCard::class);
    }

    /**
     * Get the customer challenges for the program.
     */
    public function customerChallenges(): HasMany
    {
        return $this->hasMany(CustomerChallenge::class);
    }

    /**
     * Scope a query to only include active loyalty programs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
