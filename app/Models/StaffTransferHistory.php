<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffTransferHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'staff_transfer_history';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'from_restaurant_id',
        'to_restaurant_id',
        'from_branch_id',
        'to_branch_id',
        'transfer_type',
        'transfer_reason',
        'effective_date',
        'actual_transfer_date',
        'requested_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_notes',
        'completed_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user being transferred.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the source restaurant.
     */
    public function fromRestaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'from_restaurant_id');
    }

    /**
     * Get the destination restaurant.
     */
    public function toRestaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'to_restaurant_id');
    }

    /**
     * Get the source branch.
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'from_branch_id');
    }

    /**
     * Get the destination branch.
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'to_branch_id');
    }

    /**
     * Get the user who requested the transfer.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Check if the transfer is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the transfer is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the transfer is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the transfer is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Approve the transfer.
     */
    public function approve(int $approvedBy, string $notes = null): self
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        return $this;
    }

    /**
     * Reject the transfer.
     */
    public function reject(int $rejectedBy, string $notes): self
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejection_notes' => $notes,
            'rejected_at' => now(),
        ]);

        return $this;
    }

    /**
     * Complete the transfer.
     */
    public function complete(): self
    {
        if (!$this->isApproved()) {
            throw new \Exception('Transfer must be approved before completion');
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_transfer_date' => now()->toDateString(),
        ]);

        return $this;
    }

    /**
     * Scope to get pending transfers.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved transfers.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get completed transfers.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get rejected transfers.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Get the duration of the transfer in days.
     */
    public function getTransferDuration(): ?int
    {
        if (!$this->created_at || !$this->completed_at) {
            return null;
        }

        return $this->created_at->diffInDays($this->completed_at);
    }
} 