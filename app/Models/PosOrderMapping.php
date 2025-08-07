<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderMapping extends Model
{
    use HasFactory;

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'foodhub_order_id',
        'pos_order_id',
        'pos_type',
        'sync_status',
    ];

    /**
     * Get the FoodHub order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'foodhub_order_id');
    }

    /**
     * Scope for synced orders.
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * Scope for failed orders.
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'failed');
    }

    /**
     * Scope for pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * Scope for specific POS type.
     */
    public function scopeOfPosType($query, string $posType)
    {
        return $query->where('pos_type', $posType);
    }

    /**
     * Check if order is synced.
     */
    public function isSynced(): bool
    {
        return $this->sync_status === 'synced';
    }

    /**
     * Check if order sync failed.
     */
    public function isFailed(): bool
    {
        return $this->sync_status === 'failed';
    }

    /**
     * Check if order sync is pending.
     */
    public function isPending(): bool
    {
        return $this->sync_status === 'pending';
    }

    /**
     * Mark as synced.
     */
    public function markAsSynced(): void
    {
        $this->update(['sync_status' => 'synced']);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(): void
    {
        $this->update(['sync_status' => 'failed']);
    }

    /**
     * Mark as pending.
     */
    public function markAsPending(): void
    {
        $this->update(['sync_status' => 'pending']);
    }
} 