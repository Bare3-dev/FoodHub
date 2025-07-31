<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DriverWorkingZone extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'driver_id',
        'zone_name', 
        'zone_description',
        'coordinates',
        'radius_km',
        'is_active',
        'priority_level',
        'start_time',
        'end_time',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'radius_km' => 'decimal:2',
            'is_active' => 'boolean',
            'priority_level' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * Get the driver that owns this working zone.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Scope to get only active working zones.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Custom route model binding to handle invalid IDs gracefully
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Check if the value is a valid integer
        if (!is_numeric($value) || (int)$value <= 0) {
            abort(404, 'Driver working zone not found.');
        }

        return parent::resolveRouteBinding($value, $field);
    }
} 