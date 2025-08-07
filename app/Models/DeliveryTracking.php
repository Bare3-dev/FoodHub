<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'order_assignment_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'altitude',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'altitude' => 'float',
        'timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function orderAssignment(): BelongsTo
    {
        return $this->belongsTo(OrderAssignment::class);
    }
} 