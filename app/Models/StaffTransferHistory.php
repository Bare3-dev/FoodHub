<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class StaffTransferHistory extends Model
{
    use HasFactory;

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
        'status',
        'transfer_reason',
        'additional_notes',
        'effective_date',
        'actual_transfer_date',
        'requested_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'transfer_details',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'actual_transfer_date' => 'date',
            'approved_at' => 'datetime',
            'transfer_details' => 'array',
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
     * Get the user who approved the transfer.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get transfer type description.
     */
    public function getTransferTypeDescription(): string
    {
        $descriptions = [
            'restaurant_to_restaurant' => 'Between different restaurants',
            'branch_to_branch' => 'Between branches of same restaurant',
            'restaurant_to_branch' => 'From restaurant level to specific branch',
            'branch_to_restaurant' => 'From branch to restaurant level',
            'temporary_assignment' => 'Temporary assignment',
            'permanent_transfer' => 'Permanent transfer',
        ];

        return $descriptions[$this->transfer_type] ?? 'Unknown transfer type';
    }

    /**
     * Get status description.
     */
    public function getStatusDescription(): string
    {
        $descriptions = [
            'pending' => 'Pending approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        return $descriptions[$this->status] ?? 'Unknown status';
    }

    /**
     * Check if transfer is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transfer is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if transfer is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if transfer is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transfer is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if transfer is effective (past effective date).
     */
    public function isEffective(): bool
    {
        return $this->effective_date->isPast();
    }

    /**
     * Check if transfer is overdue (past effective date but not completed).
     */
    public function isOverdue(): bool
    {
        return $this->isEffective() && !$this->isCompleted();
    }

    /**
     * Approve this transfer.
     */
    public function approve(int $approvedBy, string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => Carbon::now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Reject this transfer.
     */
    public function reject(int $approvedBy, string $notes): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approvedBy,
            'approved_at' => Carbon::now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Complete this transfer.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'actual_transfer_date' => Carbon::now(),
        ]);
    }

    /**
     * Cancel this transfer.
     */
    public function cancel(string $notes = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Get transfer summary.
     */
    public function getTransferSummary(): string
    {
        $from = $this->fromBranch ? $this->fromBranch->name : ($this->fromRestaurant ? $this->fromRestaurant->name : 'Unknown');
        $to = $this->toBranch ? $this->toBranch->name : ($this->toRestaurant ? $this->toRestaurant->name : 'Unknown');
        
        return "Transfer from {$from} to {$to}";
    }

    /**
     * Get transfer duration (days between request and completion).
     */
    public function getTransferDuration(): ?int
    {
        if (!$this->actual_transfer_date) {
            return null;
        }

        return $this->created_at->diffInDays($this->actual_transfer_date);
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
     * Scope to get transfers by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('transfer_type', $type);
    }

    /**
     * Scope to get transfers for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get transfers from a specific restaurant.
     */
    public function scopeFromRestaurant($query, int $restaurantId)
    {
        return $query->where('from_restaurant_id', $restaurantId);
    }

    /**
     * Scope to get transfers to a specific restaurant.
     */
    public function scopeToRestaurant($query, int $restaurantId)
    {
        return $query->where('to_restaurant_id', $restaurantId);
    }

    /**
     * Scope to get transfers from a specific branch.
     */
    public function scopeFromBranch($query, int $branchId)
    {
        return $query->where('from_branch_id', $branchId);
    }

    /**
     * Scope to get transfers to a specific branch.
     */
    public function scopeToBranch($query, int $branchId)
    {
        return $query->where('to_branch_id', $branchId);
    }

    /**
     * Scope to get transfers requested by a specific user.
     */
    public function scopeRequestedBy($query, int $userId)
    {
        return $query->where('requested_by', $userId);
    }

    /**
     * Scope to get transfers approved by a specific user.
     */
    public function scopeApprovedBy($query, int $userId)
    {
        return $query->where('approved_by', $userId);
    }

    /**
     * Scope to get overdue transfers.
     */
    public function scopeOverdue($query)
    {
        return $query->where('effective_date', '<', Carbon::today())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }
} 