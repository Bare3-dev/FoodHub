<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StampHistory extends Model
{
    use HasFactory;

    protected $table = 'stamp_history';

    protected $fillable = [
        'stamp_card_id',
        'order_id',
        'customer_id',
        'stamps_added',
        'stamps_before',
        'stamps_after',
        'action_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'stamps_added' => 'integer',
        'stamps_before' => 'integer',
        'stamps_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Action types constants
    public const ACTION_STAMP_EARNED = 'stamp_earned';
    public const ACTION_CARD_COMPLETED = 'card_completed';
    public const ACTION_REWARD_CLAIMED = 'reward_claimed';

    public static function getActionTypes(): array
    {
        return [
            self::ACTION_STAMP_EARNED => 'Stamp Earned',
            self::ACTION_CARD_COMPLETED => 'Card Completed',
            self::ACTION_REWARD_CLAIMED => 'Reward Claimed',
        ];
    }

    public function stampCard(): BelongsTo
    {
        return $this->belongsTo(StampCard::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
} 