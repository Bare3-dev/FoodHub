<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RestaurantConfig extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'config_key',
        'config_value',
        'is_encrypted',
        'data_type',
        'description',
        'is_sensitive',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_sensitive' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant that owns the config.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Scope a query to only include non-sensitive configs.
     */
    public function scopeNonSensitive($query)
    {
        return $query->where('is_sensitive', false);
    }

    /**
     * Scope a query to only include sensitive configs.
     */
    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * Scope a query to only include encrypted configs.
     */
    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    /**
     * Scope a query to only include non-encrypted configs.
     */
    public function scopeNonEncrypted($query)
    {
        return $query->where('is_encrypted', false);
    }
} 