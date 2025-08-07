<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeProgressLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_challenge_id',
        'customer_id',
        'challenge_id',
        'order_id',
        'action_type',
        'progress_before',
        'progress_after',
        'progress_increment',
        'description',
        'event_data',
        'milestone_reached',
        'milestone_type',
    ];

    protected $casts = [
        'progress_before' => 'decimal:2',
        'progress_after' => 'decimal:2',
        'progress_increment' => 'decimal:2',
        'event_data' => 'array',
        'milestone_reached' => 'boolean',
    ];

    /**
     * Get the customer challenge
     */
    public function customerChallenge(): BelongsTo
    {
        return $this->belongsTo(CustomerChallenge::class);
    }

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the challenge
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * Get the related order (if applicable)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for milestone events
     */
    public function scopeMilestones($query)
    {
        return $query->where('milestone_reached', true);
    }

    /**
     * Scope by action type
     */
    public function scopeByActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }
}