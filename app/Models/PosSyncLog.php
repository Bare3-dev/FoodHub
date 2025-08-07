<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSyncLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'pos_integration_id',
        'sync_type',
        'status',
        'details',
        'synced_at',
    ];

    protected $casts = [
        'details' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Get the POS integration that owns the sync log.
     */
    public function posIntegration(): BelongsTo
    {
        return $this->belongsTo(PosIntegration::class);
    }

    /**
     * Scope for successful syncs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed syncs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for pending syncs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for specific sync type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('sync_type', $type);
    }

    /**
     * Check if sync was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if sync failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if sync is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
} 