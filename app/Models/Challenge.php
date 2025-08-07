<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'challenge_type',
        'requirements',
        'reward_type',
        'reward_value',
        'reward_metadata',
        'start_date',
        'end_date',
        'duration_days',
        'target_segments',
        'is_active',
        'is_repeatable',
        'max_participants',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'requirements' => 'array',
        'reward_metadata' => 'array',
        'target_segments' => 'array',
        'metadata' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_repeatable' => 'boolean',
        'reward_value' => 'decimal:2',
    ];

    /**
     * Get customer challenges for this challenge
     */
    public function customerChallenges(): HasMany
    {
        return $this->hasMany(CustomerChallenge::class);
    }

    /**
     * Get active customer challenges
     */
    public function activeCustomerChallenges(): HasMany
    {
        return $this->customerChallenges()->whereIn('status', ['assigned', 'active']);
    }

    /**
     * Get progress logs for this challenge
     */
    public function progressLogs(): HasMany
    {
        return $this->hasMany(ChallengeProgressLog::class);
    }

    /**
     * Get engagement logs for this challenge
     */
    public function engagementLogs(): HasMany
    {
        return $this->hasMany(ChallengeEngagementLog::class);
    }

    /**
     * Check if challenge is currently active
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active && 
               $this->start_date <= now() && 
               $this->end_date >= now();
    }

    /**
     * Check if challenge has expired
     */
    public function hasExpired(): bool
    {
        return $this->end_date < now();
    }

    /**
     * Check if challenge is full (reached max participants)
     */
    public function isFull(): bool
    {
        if (!$this->max_participants) {
            return false;
        }

        return $this->activeCustomerChallenges()->count() >= $this->max_participants;
    }

    /**
     * Get completion rate for this challenge
     */
    public function getCompletionRate(): float
    {
        $total = $this->customerChallenges()->count();
        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->customerChallenges()->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get average completion time in days
     */
    public function getAverageCompletionTime(): ?float
    {
        $completedChallenges = $this->customerChallenges()
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->get();

        if ($completedChallenges->isEmpty()) {
            return null;
        }

        $totalDays = $completedChallenges->sum(function ($customerChallenge) {
            return $customerChallenge->started_at->diffInDays($customerChallenge->completed_at);
        });

        return round($totalDays / $completedChallenges->count(), 2);
    }

    /**
     * Scope for active challenges
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now());
    }

    /**
     * Scope for challenges by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('challenge_type', $type);
    }

    /**
     * Scope for challenges available to customer segment
     */
    public function scopeForCustomerSegment($query, array $customerData)
    {
        return $query->where(function ($q) use ($customerData) {
            $q->whereNull('target_segments')
              ->orWhere(function ($subQuery) use ($customerData) {
                  // Add logic to match customer data against target_segments
                  $subQuery->whereJsonContains('target_segments', $customerData);
              });
        });
    }
}