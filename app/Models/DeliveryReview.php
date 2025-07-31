<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'order_id',
        'rating',
        'comment',
        'reviewed_at'
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'reviewed_at' => 'datetime',
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