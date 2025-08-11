<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class CustomerChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'challenge_id',
        'assigned_at',
        'started_at',
        'completed_at',
        'expires_at',
        'status',
        'progress_current',
        'progress_target',
        'progress_percentage',
        'progress_details',
        'reward_claimed',
        'reward_claimed_at',
        'reward_details',
        'metadata',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'progress_current' => 'decimal:2',
        'progress_target' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
        'progress_details' => 'array',
        'reward_claimed' => 'boolean',
        'reward_claimed_at' => 'datetime',
        'reward_details' => 'array',
        'metadata' => 'array',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ChallengeProgressLog::class);
    }

    /**
     * Check if the challenge is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Update progress and recalculate percentage
     */
    public function updateProgress(float $newProgress): void
    {
        $this->progress_current = min($newProgress, $this->progress_target);
        $this->progress_percentage = $this->calculateProgressPercentage();
        
        if ($this->status === 'assigned') {
            $this->status = 'active';
            $this->started_at = now();
        }
        
        if ($this->progress_current >= $this->progress_target) {
            $this->status = 'completed';
            $this->completed_at = now();
        }
        
        $this->save();
    }

    /**
     * Calculate progress percentage
     */
    public function calculateProgressPercentage(): float
    {
        if ($this->progress_target <= 0) {
            return 0.0;
        }
        
        return round(($this->progress_current / $this->progress_target) * 100, 2);
    }

    /**
     * Get days remaining until expiry
     */
    public function getDaysRemaining(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }
        
        return (int) max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Check if challenge is close to completion (80% or more)
     */
    public function isCloseToCompletion(): bool
    {
        return $this->progress_percentage >= 80.0;
    }

    /**
     * Check if challenge expires soon (within 24 hours)
     */
    public function expiresSoon(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        
        // Check if expires within 24 hours from now
        $now = now(); // Changed from Carbon::now() back to now()
        $hoursUntilExpiry = $now->diffInHours($this->expires_at, false);
        
        // If the expiry time is in the past, return false
        if ($hoursUntilExpiry < 0) {
            return false;
        }
        
        return $hoursUntilExpiry <= 24;
    }

    /**
     * Get milestone level based on progress
     */
    public function getMilestoneLevel(): ?string
    {
        $percentage = $this->progress_percentage;
        
        if ($percentage >= 100) {
            return 'completed';
        } elseif ($percentage >= 75) {
            return '75%';
        } elseif ($percentage >= 50) {
            return '50%';
        } elseif ($percentage >= 25) {
            return '25%';
        }
        
        return null;
    }

    /**
     * Check if a specific milestone was reached
     */
    public function checkMilestoneReached(float $previousProgress): ?string
    {
        $currentMilestone = $this->getMilestoneLevel();
        
        if ($currentMilestone === null) {
            return null;
        }
        
        // Calculate what milestone the previous progress would have been
        $previousMilestone = null;
        if ($previousProgress >= 100) {
            $previousMilestone = 'completed';
        } elseif ($previousProgress >= 75) {
            $previousMilestone = '75%';
        } elseif ($previousProgress >= 50) {
            $previousMilestone = '50%';
        } elseif ($previousProgress >= 25) {
            $previousMilestone = '25%';
        }
        
        // Return the milestone if it's new (different from previous)
        return $currentMilestone !== $previousMilestone ? $currentMilestone : null;
    }

    /**
     * Mark challenge as expired
     */
    public function markAsExpired(): void
    {
        $this->status = 'expired';
        $this->save();
    }

    /**
     * Claim reward for completed challenge
     */
    public function claimReward(array $rewardDetails): void
    {
        $this->reward_claimed = true;
        $this->reward_claimed_at = now();
        $this->reward_details = $rewardDetails;
        $this->status = 'rewarded';
        $this->save();
    }

    /**
     * Scope for active challenges
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }
} 