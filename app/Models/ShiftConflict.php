<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class ShiftConflict extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'shift_id',
        'conflict_type',
        'conflict_details',
        'severity',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'conflict_details' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the shift that has this conflict.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(StaffShift::class, 'shift_id');
    }

    /**
     * Get the user who resolved this conflict.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Check if this conflict is resolved.
     */
    public function isResolved(): bool
    {
        return $this->is_resolved;
    }

    /**
     * Resolve this conflict.
     */
    public function resolve(int $resolvedBy, string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => Carbon::now(),
            'resolved_by' => $resolvedBy,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Get conflict description.
     */
    public function getConflictDescription(): string
    {
        $descriptions = [
            'overlap' => 'Shift overlaps with another scheduled shift',
            'unavailable' => 'Staff is not available during this time',
            'max_hours' => 'Shift would exceed maximum weekly hours',
            'min_rest' => 'Insufficient rest period between shifts',
            'branch_mismatch' => 'Staff assigned to incorrect branch',
            'role_mismatch' => 'Staff role does not match shift requirements',
        ];

        return $descriptions[$this->conflict_type] ?? 'Unknown conflict type';
    }

    /**
     * Get severity color for UI display.
     */
    public function getSeverityColor(): string
    {
        $colors = [
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
        ];

        return $colors[$this->severity] ?? 'gray';
    }

    /**
     * Scope to get unresolved conflicts.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope to get resolved conflicts.
     */
    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope to get conflicts by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('conflict_type', $type);
    }

    /**
     * Scope to get conflicts by severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get critical conflicts.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope to get high priority conflicts.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }
} 