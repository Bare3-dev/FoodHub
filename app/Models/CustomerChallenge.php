<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'customer_id',
        'challenge_name',
        'description',
        'target_value',
        'current_value',
        'is_completed',
        'completed_at',
        'reward_points'
    ];

    protected $casts = [
        'target_value' => 'integer',
        'current_value' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reward_points' => 'integer',
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