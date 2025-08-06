<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class SpinResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'spin_wheel_id',
        'spin_wheel_prize_id',
        'spin_type',
        'prize_value',
        'prize_type',
        'prize_name',
        'prize_description',
        'prize_details',
        'is_redeemed',
        'redeemed_at',
        'redeemed_by_order_id',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'prize_value' => 'decimal:2',
            'is_redeemed' => 'boolean',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
            'prize_details' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns the spin result.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the spin wheel that owns the result.
     */
    public function spinWheel(): BelongsTo
    {
        return $this->belongsTo(SpinWheel::class);
    }

    /**
     * Get the prize that was won.
     */
    public function prize(): BelongsTo
    {
        return $this->belongsTo(SpinWheelPrize::class, 'spin_wheel_prize_id');
    }

    /**
     * Get the order where this prize was redeemed.
     */
    public function redeemedByOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'redeemed_by_order_id');
    }

    /**
     * Check if the prize is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return Carbon::now()->gt($this->expires_at);
    }

    /**
     * Check if the prize can be redeemed.
     */
    public function getCanBeRedeemedAttribute(): bool
    {
        return !$this->is_redeemed && !$this->is_expired;
    }

    /**
     * Get the prize display value.
     */
    public function getDisplayValueAttribute(): string
    {
        switch ($this->prize_type) {
            case 'discount':
                return $this->prize_value . '%';
            case 'bonus_points':
                return $this->prize_value . ' points';
            case 'free_delivery':
                return 'Free Delivery';
            case 'cashback':
                return '$' . number_format($this->prize_value, 2);
            case 'free_item':
                return 'Free Item';
            default:
                return (string) $this->prize_value;
        }
    }

    /**
     * Redeem the prize.
     */
    public function redeem(?int $orderId = null): bool
    {
        if (!$this->can_be_redeemed) {
            return false;
        }

        $this->is_redeemed = true;
        $this->redeemed_at = Carbon::now();
        $this->redeemed_by_order_id = $orderId;
        $this->save();

        return true;
    }

    /**
     * Apply the prize to a customer.
     */
    public function applyPrize(): bool
    {
        if (!$this->can_be_redeemed) {
            return false;
        }

        $customer = $this->customer;
        
        // Ensure prize_details is properly cast as array
        if (is_string($this->prize_details)) {
            $this->prize_details = json_decode($this->prize_details, true) ?? [];
        }
        
        switch ($this->prize_type) {
            case 'bonus_points':
                return $this->applyBonusPoints($customer);
            case 'discount':
                return $this->applyDiscount($customer);
            case 'free_delivery':
                return $this->applyFreeDelivery($customer);
            case 'cashback':
                return $this->applyCashback($customer);
            case 'free_item':
                return $this->applyFreeItem($customer);
            default:
                return false;
        }
    }

    /**
     * Apply bonus points to customer.
     */
    private function applyBonusPoints(Customer $customer): bool
    {
        $loyaltyPoints = $customer->loyaltyPoints()->first();
        
        if (!$loyaltyPoints) {
            return false;
        }

        $loyaltyPoints->increment('current_points', $this->prize_value);
        $loyaltyPoints->increment('total_points_earned', $this->prize_value);
        
        return true;
    }

    /**
     * Apply discount to customer.
     */
    private function applyDiscount(Customer $customer): bool
    {
        // Store discount in customer preferences or create a discount voucher
        $preferences = $customer->preferences ?? [];
        
        // Ensure preferences is an array
        if (!is_array($preferences)) {
            $preferences = [];
        }
        
        $discounts = $preferences['discounts'] ?? [];
        
        // Ensure discounts is an array
        if (!is_array($discounts)) {
            $discounts = [];
        }
        
        $discounts[] = [
            'type' => 'spin_wheel_discount',
            'value' => $this->prize_value,
            'expires_at' => $this->expires_at?->toISOString(),
            'spin_result_id' => $this->id,
        ];
        
        $preferences['discounts'] = $discounts;
        $customer->update(['preferences' => $preferences]);
        
        return true;
    }

    /**
     * Apply free delivery to customer.
     */
    private function applyFreeDelivery(Customer $customer): bool
    {
        // Store free delivery in customer preferences
        $preferences = $customer->preferences ?? [];
        
        // Ensure preferences is an array
        if (!is_array($preferences)) {
            $preferences = [];
        }
        
        $freeDeliveries = $preferences['free_deliveries'] ?? [];
        
        // Ensure freeDeliveries is an array
        if (!is_array($freeDeliveries)) {
            $freeDeliveries = [];
        }
        
        $freeDeliveries[] = [
            'expires_at' => $this->expires_at?->toISOString(),
            'spin_result_id' => $this->id,
        ];
        
        $preferences['free_deliveries'] = $freeDeliveries;
        $customer->update(['preferences' => $preferences]);
        
        return true;
    }

    /**
     * Apply cashback to customer.
     */
    private function applyCashback(Customer $customer): bool
    {
        // Add cashback to customer balance or create a refund
        $preferences = $customer->preferences ?? [];
        
        // Ensure preferences is an array
        if (!is_array($preferences)) {
            $preferences = [];
        }
        
        $cashbacks = $preferences['cashbacks'] ?? [];
        
        // Ensure cashbacks is an array
        if (!is_array($cashbacks)) {
            $cashbacks = [];
        }
        
        $cashbacks[] = [
            'amount' => $this->prize_value,
            'expires_at' => $this->expires_at?->toISOString(),
            'spin_result_id' => $this->id,
        ];
        
        $preferences['cashbacks'] = $cashbacks;
        $customer->update(['preferences' => $preferences]);
        
        return true;
    }

    /**
     * Apply free item to customer.
     */
    private function applyFreeItem(Customer $customer): bool
    {
        // Store free item in customer preferences
        $preferences = $customer->preferences ?? [];
        
        // Ensure preferences is an array
        if (!is_array($preferences)) {
            $preferences = [];
        }
        
        $freeItems = $preferences['free_items'] ?? [];
        
        // Ensure freeItems is an array
        if (!is_array($freeItems)) {
            $freeItems = [];
        }
        
        $freeItems[] = [
            'expires_at' => $this->expires_at?->toISOString(),
            'spin_result_id' => $this->id,
        ];
        
        $preferences['free_items'] = $freeItems;
        $customer->update(['preferences' => $preferences]);
        
        return true;
    }

    /**
     * Scope a query to only include unredeemed results.
     */
    public function scopeUnredeemed($query)
    {
        return $query->where('is_redeemed', false);
    }

    /**
     * Scope a query to only include non-expired results.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    /**
     * Scope a query to only include redeemable results.
     */
    public function scopeRedeemable($query)
    {
        return $query->unredeemed()->notExpired();
    }
} 