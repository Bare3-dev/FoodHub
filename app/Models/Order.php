<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_number',
        'customer_id',
        'restaurant_id',
        'restaurant_branch_id',
        'customer_address_id',
        'status',
        'type',
        'payment_status',
        'payment_method',
        'subtotal',
        'tax_amount',
        'delivery_fee',
        'service_fee',
        'discount_amount',
        'total_amount',
        'currency',
        'confirmed_at',
        'prepared_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
        'estimated_preparation_time',
        'estimated_delivery_time',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'delivery_notes',
        'special_instructions',
        'payment_transaction_id',
        'payment_data',
        'promo_code',
        'loyalty_points_earned',
        'loyalty_points_used',
        'pos_data',
        'cancellation_reason',
        'refund_amount',
        'refunded_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'loyalty_points_earned' => 'decimal:2',
            'loyalty_points_used' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'prepared_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'payment_data' => 'array',
            'pos_data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and add event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Create status history record when order is created
        static::created(function ($order) {
            $order->createStatusHistoryRecord($order->status, auth()->id());
        });

        // Create status history record when order status is updated
        static::updated(function ($order) {
            if ($order->wasChanged('status')) {
                $order->createStatusHistoryRecord($order->status, auth()->id());
            }
        });
    }

    /**
     * Create a status history record for the order.
     */
    public function createStatusHistoryRecord(string $status, ?int $changedBy = null): void
    {
        \DB::table('order_status_history')->insert([
            'order_id' => $this->id,
            'status' => $status,
            'changed_by' => $changedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get the customer that owns the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the restaurant that owns the order.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the restaurant branch that owns the order.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get the customer address for the order.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    /**
     * Get the order items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Check if the order is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the order is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if the order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the order is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Scope a query to only include orders with a specific status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include orders with a specific type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include paid orders.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope a query to only include delivery orders.
     */
    public function scopeDelivery($query)
    {
        return $query->where('type', 'delivery');
    }

    /**
     * Scope a query to only include pickup orders.
     */
    public function scopePickup($query)
    {
        return $query->where('type', 'pickup');
    }
}
