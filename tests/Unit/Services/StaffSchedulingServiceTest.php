<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\StaffShift;
use App\Models\StaffAvailability;
use App\Models\ShiftConflict;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Services\StaffSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffSchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private StaffSchedulingService $service;
    private User $staffMember;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new StaffSchedulingService();
        
        // Create test data
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

    /** @test */
    public function it_creates_shift_without_conflicts(): void
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

    /** @test */
    public function it_detects_overlapping_shifts_conflict(): void
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

    /** @test */
    public function it_detects_unavailable_staff_conflict(): void
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
            'end_time' => '18:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'unavailable'));
    }

    /** @test */
    public function it_detects_max_weekly_hours_conflict(): void
    {
        // Arrange
        $weekStart = Carbon::now()->startOfWeek();
        
        // Create multiple shifts that exceed 48 hours
        for ($i = 0; $i < 6; $i++) {
            StaffShift::create([
                'user_id' => $this->staffMember->id,
                'restaurant_branch_id' => $this->branch->id,
                'shift_date' => $weekStart->copy()->addDays($i),
                'start_time' => '09:00',
                'end_time' => '17:00', // 8 hours per day = 48 hours total
                'status' => 'scheduled',
            ]);
        }

        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $weekStart->copy()->addDays(6)->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act - Try to create additional shift
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $weekStart->copy()->addDays(6),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'max_hours'));
    }

    /** @test */
    public function it_detects_insufficient_rest_period_conflict(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        
        // Create previous shift ending at 22:00
        StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date->copy()->subDay(),
            'start_time' => '14:00',
            'end_time' => '22:00',
            'status' => 'completed',
        ]);

        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '06:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act - Try to create shift starting at 06:00 (only 8 hours rest)
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $date,
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'min_rest'));
    }

    /** @test */
    public function it_detects_branch_mismatch_conflict(): void
    {
        // Arrange
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $date = Carbon::tomorrow();
        
        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act - Try to assign staff to different branch
        $shiftData = [
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $otherBranch->id, // Different branch
            'shift_date' => $date,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
        ];

        $shift = $this->service->createShift($shiftData);

        // Assert
        $this->assertTrue($shift->hasConflicts());
        $this->assertTrue($shift->conflicts->contains('conflict_type', 'branch_mismatch'));
    }

    /** @test */
    public function it_gets_available_staff_for_time_slot(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $startTime = '09:00';
        $endTime = '17:00';

        // Create availability for staff member
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        // Act
        $availableStaff = $this->service->getAvailableStaff($this->branch, $date, $startTime, $endTime);

        // Assert
        $this->assertCount(1, $availableStaff);
        $this->assertEquals($this->staffMember->id, $availableStaff->first()->id);
    }

    /** @test */
    public function it_excludes_unavailable_staff_from_available_list(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        $startTime = '09:00';
        $endTime = '17:00';

        // Create availability that doesn't cover the shift time
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '12:00', // Only available until 12:00
            'is_available' => true,
        ]);

        // Act
        $availableStaff = $this->service->getAvailableStaff($this->branch, $date, $startTime, $endTime);

        // Assert
        $this->assertCount(0, $availableStaff);
    }

    /** @test */
    public function it_excludes_staff_with_existing_shifts(): void
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
        $availableStaff = $this->service->getAvailableStaff($this->branch, $date, $startTime, $endTime);

        // Assert
        $this->assertCount(0, $availableStaff);
    }

    /** @test */
    public function it_auto_schedules_staff_based_on_requirements(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        
        // Create availability
        StaffAvailability::create([
            'user_id' => $this->staffMember->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);

        $requirements = [
            [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'notes' => 'Morning shift',
            ],
        ];

        // Act
        $result = $this->service->autoScheduleStaff($this->branch, $date, $requirements);

        // Assert
        $this->assertCount(1, $result['scheduled_shifts']);
        $this->assertCount(0, $result['conflicts']);
        $this->assertEquals($this->staffMember->id, $result['scheduled_shifts'][0]->user_id);
    }

    /** @test */
    public function it_handles_no_available_staff_scenario(): void
    {
        // Arrange
        $date = Carbon::tomorrow();
        
        // Don't create any availability

        $requirements = [
            [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'notes' => 'Morning shift',
            ],
        ];

        // Act
        $result = $this->service->autoScheduleStaff($this->branch, $date, $requirements);

        // Assert
        $this->assertCount(0, $result['scheduled_shifts']);
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals('no_available_staff', $result['conflicts'][0]['type']);
    }

    /** @test */
    public function it_calculates_shift_statistics(): void
    {
        // Arrange
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();
        
        // Create some shifts
        StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $startDate,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'completed',
            'total_hours' => 8.0,
        ]);

        StaffShift::create([
            'user_id' => $this->staffMember->id,
            'restaurant_branch_id' => $this->branch->id,
            'shift_date' => $startDate->copy()->addDay(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'completed',
            'total_hours' => 8.0,
        ]);

        // Act
        $statistics = $this->service->getShiftStatistics($this->branch->id, $startDate, $endDate);

        // Assert
        $this->assertEquals(2, $statistics['total_shifts']);
        $this->assertEquals(2, $statistics['completed_shifts']);
        $this->assertEquals(100, $statistics['completion_rate']);
        $this->assertEquals(16.0, $statistics['total_hours']);
        $this->assertEquals(8.0, $statistics['average_hours_per_shift']);
    }

    /** @test */
    public function it_updates_shift_with_conflict_re_detection(): void
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

        // Act - Update shift to conflict with itself (same time)
        $updatedData = [
            'start_time' => '10:00',
            'end_time' => '18:00',
        ];

        $updatedShift = $this->service->updateShift($shift, $updatedData);

        // Assert
        $this->assertEquals('10:00', $updatedShift->start_time->format('H:i'));
        $this->assertEquals('18:00', $updatedShift->end_time->format('H:i'));
    }

    /** @test */
    public function it_deletes_shift_and_its_conflicts(): void
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
            'severity' => 'high',
            'is_resolved' => false,
        ]);

        // Act
        $result = $this->service->deleteShift($shift);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('staff_shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('shift_conflicts', ['shift_id' => $shift->id]);
    }
} 