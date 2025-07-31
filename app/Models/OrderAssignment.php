<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'order_id',
        'assigned_at',
        'status'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
} 