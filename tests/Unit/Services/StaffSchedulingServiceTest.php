<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\StaffSchedulingService;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\StaffShift;
use App\Models\StaffAvailability;
use App\Models\ShiftConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Hash;

final class StaffSchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private StaffSchedulingService $service;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private User $staffMember;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(StaffSchedulingService::class);
        
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        
        $this->staffMember = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function test_creates_shift_without_conflicts(): void
    {
        // Arrange
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ];

        // Create availability for the staff member
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act
        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertInstanceOf(StaffShift::class, $shift);
        $this->assertEquals($this->staffMember->id, $shift->user_id);
        $this->assertEquals($this->branch->id, $shift->restaurant_branch_id);
        $this->assertEquals('scheduled', $shift->status);
        $this->assertFalse($shift->hasConflicts());
    }

    #[Test]
    public function test_detects_overlapping_shifts_conflict(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        
        // Create existing shift
        StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date,
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => 'scheduled',
        ]);

        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);

        // Act - Try to create overlapping shift
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date,
            'start_time' => '14:00',
            'end_time' => '22:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'overlap'));
    }

    #[Test]
    public function test_detects_unavailable_staff_conflict(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        
        // Create availability that doesn't cover the shift time
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '12:00', // Only available until 12:00
            'is_available' => true,
        ]);

        // Act - Try to create shift outside availability
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date,
            'start_time' => '14:00',
            'end_time' => '22:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'unavailable'));
    }

    #[Test]
    public function test_detects_max_weekly_hours_conflict(): void
    {
        // Arrange
        $weekStart = Carbon::now()->startOfWeek();
        
        // Create shifts that exceed weekly limit
        for ($i = 0; $i < 5; $i++) {
            StaffShift::create([
                'user_id' => $this->staffMember->id,
                'restaurant_branch_id' => $this->branch->id,
                'shift_date' => $weekStart->copy()->addDays($i),
                'start_time' => '09:00',
                'end_time' => '18:00', // 9 hours per day = 45 hours total
                'status' => 'scheduled',
            ]);
        }

        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);

        // Act - Try to create another shift
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $weekStart->copy()->addDays(5), // Saturday of the same week
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Debug: Check what conflicts were created
        $shift->refresh();
        $conflicts = $shift->conflicts;
        $unresolvedConflicts = $shift->unresolvedConflicts;
        
        // Debug output
        dump([
            'shift_id' => $shift->id,
            'total_conflicts' => $conflicts->count(),
            'unresolved_conflicts' => $unresolvedConflicts->count(),
            'conflict_types' => $conflicts->pluck('conflict_type')->toArray(),
            'unresolved_types' => $unresolvedConflicts->pluck('conflict_type')->toArray(),
        ]);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'max_hours'));
    }

    #[Test]
    public function test_detects_insufficient_rest_period_conflict(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        
        // Create shift ending at 22:00
        StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date,
            'start_time' => '14:00',
            'end_time' => '22:00',
            'status' => 'scheduled',
        ]);

        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);

        // Act - Try to create shift starting too early next day (insufficient rest)
        $nextDayShiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date->copy()->addDay(),
            'start_time' => '06:00', // Only 8 hours rest (22:00 to 06:00)
            'end_time' => '14:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($nextDayShiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'min_rest'));
    }

    #[Test]
    public function test_detects_branch_mismatch_conflict(): void
    {
        // Arrange
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        
        // Create a user that belongs to a different branch
        $otherBranchUser = User::factory()->create([
            'restaurant_branch_id' => $otherBranch->id,
            'role' => 'CASHIER',
        ]);
        
        // Create availability for the user
        StaffAvailability::create([
            'user_id' => $otherBranchUser->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act - Try to create shift at different branch
        $shiftData = [
            'user_id' => $otherBranchUser->id,
            'restaurant_branch_id' => $this->branch->id, // Different branch
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'branch_mismatch'));
    }

    #[Test]
    public function test_gets_available_staff_for_time_slot(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $startTime = '09:00';
        $endTime = '17:00';
        
        // Create available staff
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act
        $availableStaff = $this->service->getAvailableStaffForTimeSlot(
            $this->branch,
            $date,
            $startTime,
            $endTime
        );

        // Assert
        $this->assertCount(1, $availableStaff);
        $this->assertTrue($availableStaff->contains($this->staffMember));
    }

    #[Test]
    public function test_excludes_unavailable_staff_from_available_list(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $startTime = '09:00';
        $endTime = '17:00';
        
        // Create unavailable staff
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '12:00', // Only available until 12:00
            'is_available' => true,
        ]);

        // Act
        $availableStaff = $this->service->getAvailableStaffForTimeSlot(
            $this->branch,
            $date,
            $startTime,
            $endTime
        );

        // Assert
        $this->assertCount(0, $availableStaff);
    }

    #[Test]
    public function test_excludes_staff_with_existing_shifts(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $startTime = '09:00';
        $endTime = '17:00';
        
        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Create existing shift
        StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date,
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => 'scheduled',
        ]);

        // Act
        $availableStaff = $this->service->getAvailableStaffForTimeSlot(
            $this->branch,
            $date,
            $startTime,
            $endTime
        );

        // Assert
        $this->assertCount(0, $availableStaff);
    }

    #[Test]
    public function test_auto_schedules_staff_based_on_requirements(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $requirements = [
            'CASHIER' => 2,
            'KITCHEN_STAFF' => 1,
        ];

        // Create available staff directly
        $cashier1 = User::create([
            'name' => 'Cashier 1',
            'email' => 'cashier1@test.com',
            'password' => Hash::make('password'),
            'role' => 'CASHIER',
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        
        $cashier2 = User::create([
            'name' => 'Cashier 2',
            'email' => 'cashier2@test.com',
            'password' => Hash::make('password'),
            'role' => 'CASHIER',
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        
        $kitchenStaff = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@test.com',
            'password' => Hash::make('password'),
            'role' => 'KITCHEN_STAFF',
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        // Create availability for all staff
        foreach ([$cashier1, $cashier2, $kitchenStaff] as $staff) {
            StaffAvailability::create([
                'user_id' => $staff->id,
                'day_of_week' => $date->dayOfWeek,
                'start_time' => '08:00',
                'end_time' => '18:00',
                'is_available' => true,
            ]);
        }

        // Act
        $result = $this->service->autoScheduleStaff($this->branch, $date, $requirements);

        // Assert
        $this->assertCount(3, $result['scheduled_shifts']);
        $this->assertEquals(2, $result['scheduled_shifts']->where('user.role', 'CASHIER')->count());
        $this->assertEquals(1, $result['scheduled_shifts']->where('user.role', 'KITCHEN_STAFF')->count());
    }

    #[Test]
    public function test_handles_no_available_staff_scenario(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $requirements = [
            'CASHIER' => 1,
        ];

        // No staff availability created

        // Act
        $result = $this->service->autoScheduleStaff($this->branch, $date, $requirements);

        // Assert
        $this->assertCount(0, $result['scheduled_shifts']);
        $this->assertCount(1, $result['conflicts']);
    }

    #[Test]
    public function test_calculates_shift_statistics(): void
    {
        // Arrange
        $date = Carbon::now()->startOfWeek();
        
        // Create shifts for the week
        for ($i = 0; $i < 5; $i++) {
            StaffShift::create([
                'user_id' => $this->staffMember->id,
                'restaurant_branch_id' => $this->branch->id,
                'shift_date' => $date->copy()->addDays($i),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'status' => 'completed',
                'total_hours' => 8.0,
            ]);
        }

        // Act
        $stats = $this->service->calculateShiftStatistics($this->branch, $date, $date->copy()->addDays(4));

        // Assert
        $this->assertEquals(5, $stats['total_shifts']);
        $this->assertEquals(40, $stats['total_hours']); // 8 hours per day * 5 days
    }

    #[Test]
    public function test_updates_shift_with_conflict_re_detection(): void
    {
        // Arrange
        $shift = StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ]);

        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act - Update shift to create conflict
        $updatedShift = $this->service->updateShift($shift, [
            'start_time' => '06:00', // Outside availability
            'end_time' => '14:00',
        ]);

        // Assert
        $this->assertTrue($updatedShift->hasConflicts());
        $this->assertTrue($updatedShift->conflicts->contains('conflict_type', 'unavailable'));
    }

    #[Test]
    public function test_deletes_shift_and_its_conflicts(): void
    {
        // Arrange
        $shift = StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => Carbon::tomorrow(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ]);

        // Create a conflict
        ShiftConflict::create([
            'shift_id' => $shift->id,
            'conflict_type' => 'overlap',
            'conflict_details' => ['test' => 'data'],
            'severity' => 'medium',
        ]);

        // Act
        $this->service->deleteShift($shift);

        // Assert
        $this->assertDatabaseMissing('staff_shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('shift_conflicts', ['shift_id' => $shift->id]);
    }
} 