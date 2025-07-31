<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class StaffAvailability extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
        'notes',
        'effective_from',
        'effective_until',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'is_available' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user (staff member) for this availability.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the day name for this availability.
     */
    public function getDayNameAttribute(): string
    {
        $days = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return $days[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * Check if this availability is currently effective.
     */
    public function isCurrentlyEffective(): bool
    {
        $today = Carbon::today();
        
        if ($this->effective_from && $today->lt($this->effective_from)) {
            return false;
        }
        
        if ($this->effective_until && $today->gt($this->effective_until)) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if staff is available for a specific time on a specific day.
     */
    public function isAvailableForTime(string $time, int $dayOfWeek): bool
    {
        if ($this->day_of_week !== $dayOfWeek || !$this->is_available) {
            return false;
        }

        if (!$this->isCurrentlyEffective()) {
            return false;
        }

        $requestedTime = Carbon::parse($time);
        $startTime = Carbon::parse($this->start_time);
        $endTime = Carbon::parse($this->end_time);

        return $requestedTime->between($startTime, $endTime);
    }

    /**
     * Get availability duration in minutes.
     */
    public function getDurationInMinutes(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    /**
     * Scope to get available time slots.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to get availability for a specific day.
     */
    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    /**
     * Scope to get availability for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get currently effective availability.
     */
    public function scopeCurrentlyEffective($query)
    {
        $today = Carbon::today();
        
        return $query->where(function ($q) use ($today) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', $today);
        })->where(function ($q) use ($today) {
            $q->whereNull('effective_until')
              ->orWhere('effective_until', '>=', $today);
        });
    }
} 