<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StaffShift;
use App\Models\StaffAvailability;
use App\Models\ShiftConflict;
use App\Models\User;
use App\Models\RestaurantBranch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffSchedulingService
{
    /**
     * Maximum weekly hours for staff members.
     */
    private const MAX_WEEKLY_HOURS = 48;

    /**
     * Minimum rest period between shifts (hours).
     */
    private const MIN_REST_HOURS = 8;

    /**
     * Create a new staff shift with conflict detection.
     */
    public function createShift(array $shiftData): StaffShift
    {
        return DB::transaction(function () use ($shiftData) {
            // Create the shift
            $shift = StaffShift::create($shiftData);
            
            // Detect and create conflicts
            $this->detectConflicts($shift);
            
            return $shift->load(['user', 'branch', 'conflicts']);
        });
    }

    /**
     * Update an existing shift with conflict re-detection.
     */
    public function updateShift(StaffShift $shift, array $shiftData): StaffShift
    {
        return DB::transaction(function () use ($shift, $shiftData) {
            // Remove existing conflicts
            $shift->conflicts()->delete();
            
            // Update the shift
            $shift->update($shiftData);
            
            // Re-detect conflicts
            $this->detectConflicts($shift);
            
            return $shift->load(['user', 'branch', 'conflicts']);
        });
    }

    /**
     * Delete a shift and its conflicts.
     */
    public function deleteShift(StaffShift $shift): bool
    {
        return DB::transaction(function () use ($shift) {
            // Delete conflicts first
            $shift->conflicts()->delete();
            
            // Delete the shift
            return $shift->delete();
        });
    }

    /**
     * Detect all possible conflicts for a shift.
     */
    public function detectConflicts(StaffShift $shift): void
    {
        // Check for overlapping shifts
        if ($this->hasOverlappingShifts($shift)) {
            $this->createConflict($shift, 'overlap', 'high');
        }

        // Check staff availability
        if (!$this->isStaffAvailable($shift)) {
            $this->createConflict($shift, 'unavailable', 'high');
        }

        // Check maximum weekly hours
        if ($this->exceedsMaxWeeklyHours($shift)) {
            $this->createConflict($shift, 'max_hours', 'critical');
        }

        // Check minimum rest period
        if ($this->insufficientRestPeriod($shift)) {
            $this->createConflict($shift, 'min_rest', 'medium');
        }

        // Check branch assignment
        if (!$this->isValidBranchAssignment($shift)) {
            $this->createConflict($shift, 'branch_mismatch', 'high');
        }

        // Check role requirements
        if (!$this->isValidRoleAssignment($shift)) {
            $this->createConflict($shift, 'role_mismatch', 'medium');
        }
    }

    /**
     * Check if shift overlaps with existing shifts.
     */
    private function hasOverlappingShifts(StaffShift $shift): bool
    {
        $overlappingShifts = StaffShift::where('user_id', $shift->user_id)
            ->where('shift_date', $shift->shift_date)
            ->where('id', '!=', $shift->id)
            ->where(function ($query) use ($shift) {
                $query->where(function ($q) use ($shift) {
                    $q->where('start_time', '<', $shift->end_time)
                      ->where('end_time', '>', $shift->start_time);
                });
            })
            ->exists();

        return $overlappingShifts;
    }

    /**
     * Check if staff is available for the shift.
     */
    private function isStaffAvailable(StaffShift $shift): bool
    {
        $dayOfWeek = $shift->shift_date->dayOfWeek;
        $startTime = $shift->start_time->format('H:i:s');
        $endTime = $shift->end_time->format('H:i:s');

        $availability = StaffAvailability::where('user_id', $shift->user_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->currentlyEffective()
            ->exists();

        return $availability;
    }

    /**
     * Check if shift would exceed maximum weekly hours.
     */
    private function exceedsMaxWeeklyHours(StaffShift $shift): bool
    {
        $weekStart = $shift->shift_date->startOfWeek();
        $weekEnd = $shift->shift_date->endOfWeek();

        $weeklyHours = StaffShift::where('user_id', $shift->user_id)
            ->whereBetween('shift_date', [$weekStart, $weekEnd])
            ->get()
            ->sum(function ($existingShift) {
                return $existingShift->getDurationInMinutes() / 60;
            });

        $totalHours = $weeklyHours;

        return $totalHours > self::MAX_WEEKLY_HOURS;
    }

    /**
     * Check if there's insufficient rest between shifts.
     */
    private function insufficientRestPeriod(StaffShift $shift): bool
    {
        $previousShift = StaffShift::where('user_id', $shift->user_id)
            ->where('shift_date', '<=', $shift->shift_date)
            ->where('id', '!=', $shift->id)
            ->orderBy('shift_date', 'desc')
            ->orderBy('end_time', 'desc')
            ->first();

        if (!$previousShift) {
            return false;
        }

        $restHours = $previousShift->shift_date->diffInHours($shift->shift_date, false);
        if ($restHours > 24) {
            return false; // Different days, sufficient rest
        }

        $previousEnd = Carbon::parse($previousShift->end_time);
        $currentStart = Carbon::parse($shift->start_time);
        $restHours = $previousEnd->diffInHours($currentStart, false);

        return $restHours < self::MIN_REST_HOURS;
    }

    /**
     * Check if branch assignment is valid.
     */
    private function isValidBranchAssignment(StaffShift $shift): bool
    {
        $user = $shift->user;
        $branch = $shift->branch;

        // Super admins can work at any branch
        if ($user->role === 'SUPER_ADMIN') {
            return true;
        }

        // Restaurant owners can work at any branch of their restaurants
        if ($user->role === 'RESTAURANT_OWNER') {
            return $user->restaurant_id === $branch->restaurant_id;
        }

        // Branch managers can only work at their assigned branch
        if ($user->role === 'BRANCH_MANAGER') {
            return $user->restaurant_branch_id === $branch->id;
        }

        // Other staff can work at their assigned branch
        return $user->restaurant_branch_id === $branch->id;
    }

    /**
     * Check if role assignment is valid for the shift.
     */
    private function isValidRoleAssignment(StaffShift $shift): bool
    {
        $user = $shift->user;
        $branch = $shift->branch;

        // All roles can work at their assigned locations
        return true; // This can be enhanced with role-specific requirements
    }

    /**
     * Create a conflict record.
     */
    private function createConflict(StaffShift $shift, string $type, string $severity): ShiftConflict
    {
        return ShiftConflict::create([
            'shift_id' => $shift->id,
            'conflict_type' => $type,
            'conflict_details' => $this->getConflictDetails($shift, $type),
            'severity' => $severity,
            'is_resolved' => false,
        ]);
    }

    /**
     * Get detailed conflict information.
     */
    private function getConflictDetails(StaffShift $shift, string $type): array
    {
        $details = [
            'shift_id' => $shift->id,
            'user_id' => $shift->user_id,
            'shift_date' => $shift->shift_date->toDateString(),
            'start_time' => $shift->start_time->format('H:i'),
            'end_time' => $shift->end_time->format('H:i'),
        ];

        switch ($type) {
            case 'overlap':
                $overlappingShifts = StaffShift::where('user_id', $shift->user_id)
                    ->where('shift_date', $shift->shift_date)
                    ->where('id', '!=', $shift->id)
                    ->where(function ($query) use ($shift) {
                        $query->where(function ($q) use ($shift) {
                            $q->where('start_time', '<', $shift->end_time)
                              ->where('end_time', '>', $shift->start_time);
                        });
                    })
                    ->get(['id', 'start_time', 'end_time']);
                
                $details['overlapping_shifts'] = $overlappingShifts->toArray();
                break;

            case 'max_hours':
                $weekStart = $shift->shift_date->startOfWeek();
                $weekEnd = $shift->shift_date->endOfWeek();
                
                $weeklyHours = StaffShift::where('user_id', $shift->user_id)
                    ->whereBetween('shift_date', [$weekStart, $weekEnd])
                    ->where('id', '!=', $shift->id)
                    ->get()
                    ->sum(function ($existingShift) {
                        return $existingShift->getDurationInMinutes() / 60;
                    });
                
                $details['current_weekly_hours'] = $weeklyHours;
                $details['max_weekly_hours'] = self::MAX_WEEKLY_HOURS;
                break;

            case 'min_rest':
                $previousShift = StaffShift::where('user_id', $shift->user_id)
                    ->where('shift_date', '<=', $shift->shift_date)
                    ->where('id', '!=', $shift->id)
                    ->orderBy('shift_date', 'desc')
                    ->orderBy('end_time', 'desc')
                    ->first();
                
                if ($previousShift) {
                    $details['previous_shift'] = [
                        'id' => $previousShift->id,
                        'date' => $previousShift->shift_date->toDateString(),
                        'end_time' => $previousShift->end_time->format('H:i'),
                    ];
                }
                break;
        }

        return $details;
    }

    /**
     * Get available staff for a specific time slot.
     */
    public function getAvailableStaff(RestaurantBranch $branch, Carbon $date, string $startTime, string $endTime): Collection
    {
        $dayOfWeek = $date->dayOfWeek;

        return User::where('restaurant_branch_id', $branch->id)
            ->where('status', 'active')
            ->whereHas('availability', function ($query) use ($dayOfWeek, $startTime, $endTime) {
                $query->where('day_of_week', $dayOfWeek)
                      ->where('is_available', true)
                      ->where('start_time', '<=', $startTime)
                      ->where('end_time', '>=', $endTime)
                      ->currentlyEffective();
            })
            ->whereDoesntHave('shifts', function ($query) use ($date, $startTime, $endTime) {
                $query->where('shift_date', $date)
                      ->where(function ($q) use ($startTime, $endTime) {
                          $q->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                      });
            })
            ->get();
    }

    /**
     * Get available staff for a specific time slot (alias for getAvailableStaff).
     */
    public function getAvailableStaffForTimeSlot(RestaurantBranch $branch, Carbon $date, string $startTime, string $endTime): Collection
    {
        return $this->getAvailableStaff($branch, $date, $startTime, $endTime);
    }

    /**
     * Get available staff for auto-scheduling (doesn't check for existing shifts).
     */
    public function getAvailableStaffForAutoScheduling(RestaurantBranch $branch, Carbon $date, string $startTime, string $endTime): Collection
    {
        $dayOfWeek = $date->dayOfWeek;

        $query = User::where('restaurant_branch_id', $branch->id)
            ->where('status', 'active')
            ->whereHas('availability', function ($query) use ($dayOfWeek, $startTime, $endTime) {
                $query->where('day_of_week', $dayOfWeek)
                      ->where('is_available', true)
                      ->where('start_time', '<=', $startTime)
                      ->where('end_time', '>=', $endTime)
                      ->currentlyEffective();
            });

        // Debug: Log the SQL query
        \Log::info('Auto-scheduling query:', [
            'branch_id' => $branch->id,
            'date' => $date->toDateString(),
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        $result = $query->get();

        // Debug: Log the result
        \Log::info('Auto-scheduling result:', [
            'count' => $result->count(),
            'user_ids' => $result->pluck('id')->toArray(),
            'user_roles' => $result->pluck('role')->toArray()
        ]);

        return $result;
    }

    /**
     * Auto-schedule staff based on availability and requirements.
     */
    public function autoScheduleStaff(RestaurantBranch $branch, Carbon $date, array $requirements): array
    {
        $scheduledShifts = [];
        $conflicts = [];
        $scheduledStaffIds = []; // Track already scheduled staff

        foreach ($requirements as $role => $count) {
            for ($i = 0; $i < $count; $i++) {
                $availableStaff = $this->getAvailableStaffForAutoScheduling(
                    $branch,
                    $date,
                    '09:00', // Default start time
                    '17:00'  // Default end time
                );

                // Filter by role if specified
                if ($role !== 'any') {
                    $availableStaff = $availableStaff->where('role', $role);
                }

                // Exclude already scheduled staff
                $availableStaff = $availableStaff->whereNotIn('id', $scheduledStaffIds);

                if ($availableStaff->isEmpty()) {
                    $conflicts[] = [
                        'type' => 'no_available_staff',
                        'time_slot' => '09:00 - 17:00',
                        'required_role' => $role,
                    ];
                    continue;
                }

                // Select best available staff member
                $selectedStaff = $this->selectBestStaffMember($availableStaff, ['roles' => [$role]]);
                
                $shift = $this->createShift([
                    'user_id' => $selectedStaff->id,
                    'restaurant_branch_id' => $branch->id,
                    'shift_date' => $date,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'notes' => "Auto-scheduled for {$role} role",
                ]);

                $scheduledShifts[] = $shift;
                $scheduledStaffIds[] = $selectedStaff->id; // Mark as scheduled
            }
        }

        return [
            'scheduled_shifts' => collect($scheduledShifts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Select the best staff member for a shift based on various criteria.
     */
    private function selectBestStaffMember(Collection $availableStaff, array $requirement): User
    {
        // Score each staff member based on various criteria
        $scoredStaff = $availableStaff->map(function ($staff) use ($requirement) {
            $score = 0;

            // Prefer staff with fewer conflicts
            $conflictCount = $staff->shifts()
                ->whereHas('conflicts', function ($query) {
                    $query->where('is_resolved', false);
                })
                ->count();
            $score -= $conflictCount * 10;

            // Prefer staff with matching roles
            if (isset($requirement['roles']) && in_array($staff->role, $requirement['roles'])) {
                $score += 50;
            }

            // Prefer staff with more experience (longer employment)
            $employmentDays = $staff->created_at->diffInDays(now());
            $score += min($employmentDays / 30, 20); // Max 20 points for experience

            // Prefer staff with better performance (if available)
            // This can be enhanced with performance metrics

            return [
                'staff' => $staff,
                'score' => $score,
            ];
        });

        // Return the staff member with the highest score
        return $scoredStaff->sortByDesc('score')->first()['staff'];
    }

    /**
     * Get shift statistics for a date range.
     */
    public function getShiftStatistics(int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $shifts = StaffShift::forBranch($branchId)
            ->forDateRange($startDate, $endDate)
            ->with(['user', 'conflicts'])
            ->get();

        $totalShifts = $shifts->count();
        $completedShifts = $shifts->where('status', 'completed')->count();
        $conflictedShifts = $shifts->filter(function ($shift) {
            return $shift->hasConflicts();
        })->count();

        $totalHours = $shifts->where('status', 'completed')->sum('total_hours');
        $averageHoursPerShift = $totalShifts > 0 ? $totalHours / $totalShifts : 0;

        return [
            'total_shifts' => $totalShifts,
            'completed_shifts' => $completedShifts,
            'conflicted_shifts' => $conflictedShifts,
            'completion_rate' => $totalShifts > 0 ? ($completedShifts / $totalShifts) * 100 : 0,
            'total_hours' => $totalHours,
            'average_hours_per_shift' => round($averageHoursPerShift, 2),
            'conflict_rate' => $totalShifts > 0 ? ($conflictedShifts / $totalShifts) * 100 : 0,
        ];
    }

    /**
     * Calculate shift statistics for a date range (alias for getShiftStatistics).
     */
    public function calculateShiftStatistics(RestaurantBranch $branch, Carbon $startDate, Carbon $endDate): array
    {
        return $this->getShiftStatistics($branch->id, $startDate, $endDate);
    }
} 