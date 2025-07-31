<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StampCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'customer_id',
        'stamps_earned',
        'stamps_required',
        'is_completed',
        'completed_at'
    ];

    protected $casts = [
        'stamps_earned' => 'integer',
        'stamps_required' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
} 