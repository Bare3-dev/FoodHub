<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosIntegration extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'restaurant_id',
        'pos_type',
        'configuration',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the restaurant that owns the POS integration.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the sync logs for this POS integration.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(PosSyncLog::class);
    }

    /**
     * Get the order mappings for this POS integration.
     */
    public function orderMappings(): HasMany
    {
        return $this->hasMany(PosOrderMapping::class, 'pos_type', 'pos_type');
    }

    /**
     * Check if the integration is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get configuration value by key.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->configuration, $key, $default);
    }

    /**
     * Set configuration value.
     */
    public function setConfig(string $key, mixed $value): void
    {
        $config = $this->configuration ?? [];
        data_set($config, $key, $value);
        $this->configuration = $config;
    }

    /**
     * Update last sync timestamp.
     */
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }
} 