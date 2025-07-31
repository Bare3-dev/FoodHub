<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

final class StaffShift extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'restaurant_branch_id',
        'shift_date',
        'start_time',
        'end_time',
        'status',
        'clock_in_at',
        'clock_out_at',
        'notes',
        'break_times',
        'total_hours',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'break_times' => 'array',
            'total_hours' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user (staff member) for this shift.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the restaurant branch for this shift.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get the conflicts for this shift.
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(ShiftConflict::class, 'shift_id');
    }

    /**
     * Get unresolved conflicts for this shift.
     */
    public function unresolvedConflicts(): HasMany
    {
        return $this->hasMany(ShiftConflict::class, 'shift_id')->where('is_resolved', false);
    }

    /**
     * Check if shift has any conflicts.
     */
    public function hasConflicts(): bool
    {
        return $this->unresolvedConflicts()->exists();
    }

    /**
     * Calculate total hours worked for this shift.
     */
    public function calculateTotalHours(): float
    {
        if (!$this->clock_in_at || !$this->clock_out_at) {
            return 0.0;
        }

        $startTime = Carbon::parse($this->clock_in_at);
        $endTime = Carbon::parse($this->clock_out_at);
        
        // Subtract break times
        $breakMinutes = 0;
        if (is_array($this->break_times)) {
            foreach ($this->break_times as $break) {
                if (isset($break['start']) && isset($break['end'])) {
                    $breakStart = Carbon::parse($break['start']);
                    $breakEnd = Carbon::parse($break['end']);
                    $breakMinutes += $breakStart->diffInMinutes($breakEnd);
                }
            }
        }

        $totalMinutes = $startTime->diffInMinutes($endTime) - $breakMinutes;
        return round($totalMinutes / 60, 2);
    }

    /**
     * Check if shift is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if shift is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if shift is scheduled for today.
     */
    public function isToday(): bool
    {
        return $this->shift_date->isToday();
    }

    /**
     * Get shift duration in minutes.
     */
    public function getDurationInMinutes(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    /**
     * Scope to get active shifts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get scheduled shifts.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get shifts for a specific date range.
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('shift_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get shifts for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get shifts for a specific branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('restaurant_branch_id', $branchId);
    }
} 