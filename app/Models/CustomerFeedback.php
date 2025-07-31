<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class CustomerFeedback extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'restaurant_id',
        'restaurant_branch_id',
        'user_id',
        'rating',
        'feedback_type',
        'feedback_text',
        'feedback_details',
        'is_anonymous',
        'is_verified_purchase',
        'status',
        'reviewed_at',
        'reviewed_by',
        'moderation_notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'feedback_details' => 'array',
            'is_anonymous' => 'boolean',
            'is_verified_purchase' => 'boolean',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the order for this feedback.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the customer for this feedback.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the restaurant for this feedback.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the restaurant branch for this feedback.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get the staff member being rated.
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who reviewed this feedback.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get feedback type description.
     */
    public function getFeedbackTypeDescription(): string
    {
        $descriptions = [
            'food_quality' => 'Food Quality',
            'service' => 'Service',
            'delivery' => 'Delivery',
            'overall' => 'Overall Experience',
            'cleanliness' => 'Cleanliness',
            'value_for_money' => 'Value for Money',
            'menu_variety' => 'Menu Variety',
            'special_requests' => 'Special Requests',
        ];

        return $descriptions[$this->feedback_type] ?? 'Unknown';
    }

    /**
     * Check if feedback is positive (4-5 stars).
     */
    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    /**
     * Check if feedback is negative (1-2 stars).
     */
    public function isNegative(): bool
    {
        return $this->rating <= 2;
    }

    /**
     * Check if feedback is neutral (3 stars).
     */
    public function isNeutral(): bool
    {
        return $this->rating === 3;
    }

    /**
     * Get rating description.
     */
    public function getRatingDescription(): string
    {
        $descriptions = [
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Good',
            5 => 'Excellent',
        ];

        return $descriptions[$this->rating] ?? 'Unknown';
    }

    /**
     * Check if feedback is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if feedback is pending review.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if feedback is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if feedback is flagged for review.
     */
    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }

    /**
     * Approve this feedback.
     */
    public function approve(int $reviewedBy, string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewedBy,
            'moderation_notes' => $notes,
        ]);
    }

    /**
     * Reject this feedback.
     */
    public function reject(int $reviewedBy, string $notes): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewedBy,
            'moderation_notes' => $notes,
        ]);
    }

    /**
     * Flag this feedback for review.
     */
    public function flag(string $notes = null): void
    {
        $this->update([
            'status' => 'flagged',
            'moderation_notes' => $notes,
        ]);
    }

    /**
     * Scope to get approved feedback.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get pending feedback.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get flagged feedback.
     */
    public function scopeFlagged($query)
    {
        return $query->where('status', 'flagged');
    }

    /**
     * Scope to get feedback by rating.
     */
    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope to get positive feedback (4-5 stars).
     */
    public function scopePositive($query)
    {
        return $query->where('rating', '>=', 4);
    }

    /**
     * Scope to get negative feedback (1-2 stars).
     */
    public function scopeNegative($query)
    {
        return $query->where('rating', '<=', 2);
    }

    /**
     * Scope to get feedback by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('feedback_type', $type);
    }

    /**
     * Scope to get feedback for a specific restaurant.
     */
    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope to get feedback for a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('restaurant_branch_id', $branchId);
    }

    /**
     * Scope to get feedback for a specific staff member.
     */
    public function scopeForStaff($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get verified purchase feedback.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    /**
     * Scope to get anonymous feedback.
     */
    public function scopeAnonymous($query)
    {
        return $query->where('is_anonymous', true);
    }
} 