<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoyaltyPointsHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'loyalty_points_history';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_loyalty_points_id',
        'order_id',
        'transaction_type',
        'points_amount',
        'points_balance_after',
        'description',
        'transaction_details',
        'source',
        'bonus_multipliers_applied',
        'base_amount',
        'multiplier_applied',
        'reference_id',
        'reference_type',
        'is_reversible',
        'reversed_at',
        'reversed_by',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'points_amount' => 'decimal:2',
            'points_balance_after' => 'decimal:2',
            'transaction_details' => 'array',
            'bonus_multipliers_applied' => 'array',
            'base_amount' => 'decimal:2',
            'multiplier_applied' => 'decimal:2',
            'is_reversible' => 'boolean',
            'reversed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer loyalty points that owns the history.
     */
    public function customerLoyaltyPoints(): BelongsTo
    {
        return $this->belongsTo(CustomerLoyaltyPoint::class, 'customer_loyalty_points_id');
    }

    /**
     * Get the order associated with this transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who reversed this transaction.
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Check if this transaction is reversed.
     */
    public function getIsReversedAttribute(): bool
    {
        return !is_null($this->reversed_at);
    }

    /**
     * Get the net points amount (negative if reversed).
     */
    public function getNetPointsAmountAttribute(): float
    {
        if ($this->is_reversed) {
            return -$this->points_amount;
        }
        return $this->points_amount;
    }

    /**
     * Get transaction type display name.
     */
    public function getTransactionTypeDisplayAttribute(): string
    {
        return match ($this->transaction_type) {
            'earned' => 'Points Earned',
            'redeemed' => 'Points Redeemed',
            'expired' => 'Points Expired',
            'adjusted' => 'Points Adjusted',
            'bonus' => 'Bonus Points',
            'reversal' => 'Transaction Reversed',
            default => ucfirst($this->transaction_type),
        };
    }

    /**
     * Get source display name.
     */
    public function getSourceDisplayAttribute(): string
    {
        return match ($this->source) {
            'order' => 'Order Purchase',
            'bonus' => 'Bonus Points',
            'referral' => 'Referral Bonus',
            'birthday' => 'Birthday Bonus',
            'happy_hour' => 'Happy Hour Bonus',
            'first_order' => 'First Order Bonus',
            'tier_upgrade' => 'Tier Upgrade Bonus',
            'promotion' => 'Promotional Bonus',
            'adjustment' => 'Manual Adjustment',
            default => ucfirst(str_replace('_', ' ', $this->source)),
        };
    }

    /**
     * Scope a query to only include earned transactions.
     */
    public function scopeEarned($query)
    {
        return $query->where('transaction_type', 'earned');
    }

    /**
     * Scope a query to only include redeemed transactions.
     */
    public function scopeRedeemed($query)
    {
        return $query->where('transaction_type', 'redeemed');
    }

    /**
     * Scope a query to only include expired transactions.
     */
    public function scopeExpired($query)
    {
        return $query->where('transaction_type', 'expired');
    }

    /**
     * Scope a query to only include bonus transactions.
     */
    public function scopeBonus($query)
    {
        return $query->where('transaction_type', 'bonus');
    }

    /**
     * Scope a query to only include non-reversed transactions.
     */
    public function scopeNonReversed($query)
    {
        return $query->whereNull('reversed_at');
    }

    /**
     * Scope a query to only include reversed transactions.
     */
    public function scopeReversed($query)
    {
        return $query->whereNotNull('reversed_at');
    }

    /**
     * Scope a query to get transactions for a specific source.
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope a query to get transactions within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get total points earned for a customer.
     */
    public static function getTotalEarned(int $customerLoyaltyPointsId): float
    {
        return self::where('customer_loyalty_points_id', $customerLoyaltyPointsId)
            ->earned()
            ->nonReversed()
            ->sum('points_amount');
    }

    /**
     * Get total points redeemed for a customer.
     */
    public static function getTotalRedeemed(int $customerLoyaltyPointsId): float
    {
        return self::where('customer_loyalty_points_id', $customerLoyaltyPointsId)
            ->redeemed()
            ->nonReversed()
            ->sum('points_amount');
    }

    /**
     * Get total points expired for a customer.
     */
    public static function getTotalExpired(int $customerLoyaltyPointsId): float
    {
        return self::where('customer_loyalty_points_id', $customerLoyaltyPointsId)
            ->expired()
            ->nonReversed()
            ->sum('points_amount');
    }
} 