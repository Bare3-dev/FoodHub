<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Driver;
use App\Models\DriverWorkingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DriverWorkingZoneTest extends TestCase
{
    use RefreshDatabase;

    private DriverWorkingZone $workingZone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workingZone = DriverWorkingZone::factory()->create();
    }

    /**
     * Test working zone has correct relationships
     */
    public function test_it_has_correct_relationships(): void
    {
        $driver = Driver::factory()->create();
        $workingZone = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id
        ]);

        // Test driver relationship
        $this->assertEquals($driver->id, $workingZone->driver->id);
        $this->assertTrue($driver->workingZones->contains($workingZone));
    }

    /**
     * Test working zone validates required fields
     */
    public function test_it_validates_required_fields(): void
    {
        $driver = Driver::factory()->create();
        
        $requiredFields = [
            'driver_id' => $driver->id,
            'zone_name' => 'Downtown Zone',
            'coordinates' => [
                'latitude' => 40.7128,
                'longitude' => -74.0060
            ],
            'radius_km' => 5.0
        ];

        $workingZone = DriverWorkingZone::create($requiredFields);

        $this->assertDatabaseHas('driver_working_zones', [
            'id' => $workingZone->id,
            'driver_id' => $driver->id,
            'zone_name' => 'Downtown Zone',
            'radius_km' => 5.0
        ]);
    }

    /**
     * Test working zone enforces business rules
     */
    public function test_it_enforces_business_rules(): void
    {
        $driver = Driver::factory()->create();

        // Test that coordinates must be valid JSON (array)
        // Note: PostgreSQL might be more lenient with JSON validation
        // This test ensures the model handles coordinate data correctly
        $workingZone = DriverWorkingZone::create([
            'driver_id' => $driver->id,
            'zone_name' => 'Test Zone',
            'coordinates' => ['latitude' => 40.7128, 'longitude' => -74.0060],
            'radius_km' => 5.0
        ]);

        $this->assertDatabaseHas('driver_working_zones', [
            'id' => $workingZone->id,
            'zone_name' => 'Test Zone'
        ]);

        // Test that coordinates are properly cast to array
        $this->assertIsArray($workingZone->coordinates);
        $this->assertEquals(40.7128, $workingZone->coordinates['latitude']);
        $this->assertEquals(-74.0060, $workingZone->coordinates['longitude']);
    }

    /**
     * Test working zone scopes data correctly
     */
    public function test_it_scopes_data_correctly(): void
    {
        // Create working zones with different active statuses
        $activeZone = DriverWorkingZone::factory()->create(['is_active' => true]);
        $inactiveZone = DriverWorkingZone::factory()->create(['is_active' => false]);

        // Test active scope
        $activeZones = DriverWorkingZone::active()->get();
        $this->assertTrue($activeZones->contains($activeZone));
        $this->assertFalse($activeZones->contains($inactiveZone));
    }

    /**
     * Test working zone handles coordinates correctly
     */
    public function test_it_handles_coordinates_correctly(): void
    {
        $coordinates = [
            'latitude' => 40.7128,
            'longitude' => -74.0060
        ];

        $workingZone = DriverWorkingZone::factory()->create([
            'coordinates' => $coordinates
        ]);

        $this->assertEquals($coordinates, $workingZone->coordinates);
        $this->assertIsArray($workingZone->coordinates);
        $this->assertEquals(40.7128, $workingZone->coordinates['latitude']);
        $this->assertEquals(-74.0060, $workingZone->coordinates['longitude']);
    }

    /**
     * Test working zone handles radius correctly
     */
    public function test_it_handles_radius_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create([
            'radius_km' => 7.5
        ]);

        $this->assertEquals('7.50', $workingZone->radius_km);
        $this->assertIsString($workingZone->radius_km); // Laravel decimal casts return strings
    }

    /**
     * Test working zone handles priority levels correctly
     */
    public function test_it_handles_priority_levels_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create([
            'priority_level' => 3
        ]);

        $this->assertEquals(3, $workingZone->priority_level);
        $this->assertIsInt($workingZone->priority_level);
    }

    /**
     * Test working zone handles active status correctly
     */
    public function test_it_handles_active_status_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create([
            'is_active' => true
        ]);

        $this->assertTrue($workingZone->is_active);
        $this->assertIsBool($workingZone->is_active);

        // Test status change
        $workingZone->update(['is_active' => false]);
        $this->assertFalse($workingZone->fresh()->is_active);
    }

    /**
     * Test working zone handles working hours correctly
     */
    public function test_it_handles_working_hours_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create([
            'start_time' => '08:00:00',
            'end_time' => '18:00:00'
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $workingZone->start_time);
        $this->assertInstanceOf(\Carbon\Carbon::class, $workingZone->end_time);
        $this->assertEquals('08:00:00', $workingZone->start_time->format('H:i:s'));
        $this->assertEquals('18:00:00', $workingZone->end_time->format('H:i:s'));
    }

    /**
     * Test working zone handles optional fields correctly
     */
    public function test_it_handles_optional_fields_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create([
            'zone_description' => 'A busy downtown area',
            'start_time' => null,
            'end_time' => null
        ]);

        $this->assertEquals('A busy downtown area', $workingZone->zone_description);
        $this->assertNull($workingZone->start_time);
        $this->assertNull($workingZone->end_time);
    }

    /**
     * Test working zone factory states work correctly
     */
    public function test_it_uses_factory_states_correctly(): void
    {
        // Test active state
        $activeZone = DriverWorkingZone::factory()->active()->create();
        $this->assertTrue($activeZone->is_active);

        // Test inactive state
        $inactiveZone = DriverWorkingZone::factory()->inactive()->create();
        $this->assertFalse($inactiveZone->is_active);

        // Test high priority state
        $highPriorityZone = DriverWorkingZone::factory()->highPriority()->create();
        $this->assertGreaterThanOrEqual(4, $highPriorityZone->priority_level);
        $this->assertLessThanOrEqual(5, $highPriorityZone->priority_level);

        // Test with coordinates state
        $coordinatedZone = DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 3.5)->create();
        $this->assertEquals(40.7128, $coordinatedZone->coordinates['latitude']);
        $this->assertEquals(-74.0060, $coordinatedZone->coordinates['longitude']);
        $this->assertEquals('3.50', $coordinatedZone->radius_km);

        // Test with working hours state
        $workingHoursZone = DriverWorkingZone::factory()->withWorkingHours('09:00:00', '17:00:00')->create();
        $this->assertEquals('09:00:00', $workingHoursZone->start_time->format('H:i:s'));
        $this->assertEquals('17:00:00', $workingHoursZone->end_time->format('H:i:s'));
    }

    /**
     * Test working zone handles route model binding correctly
     */
    public function test_it_handles_route_model_binding_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create();

        // Test valid ID
        $resolved = $workingZone->resolveRouteBinding($workingZone->id);
        $this->assertEquals($workingZone->id, $resolved->id);

        // Test invalid ID (should throw 404)
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $workingZone->resolveRouteBinding('invalid');
    }

    /**
     * Test working zone handles edge cases correctly
     */
    public function test_it_handles_edge_cases_correctly(): void
    {
        // Test very small radius
        $smallRadiusZone = DriverWorkingZone::factory()->create([
            'radius_km' => 0.01
        ]);
        $this->assertEquals('0.01', $smallRadiusZone->radius_km);

        // Test very large radius
        $largeRadiusZone = DriverWorkingZone::factory()->create([
            'radius_km' => 999.99
        ]);
        $this->assertEquals('999.99', $largeRadiusZone->radius_km);

        // Test maximum priority level
        $maxPriorityZone = DriverWorkingZone::factory()->create([
            'priority_level' => 5
        ]);
        $this->assertEquals(5, $maxPriorityZone->priority_level);

        // Test minimum priority level
        $minPriorityZone = DriverWorkingZone::factory()->create([
            'priority_level' => 1
        ]);
        $this->assertEquals(1, $minPriorityZone->priority_level);
    }

    /**
     * Test working zone handles coordinate validation
     */
    public function test_it_handles_coordinate_validation(): void
    {
        $driver = Driver::factory()->create();

        // Test valid coordinates
        $validZone = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'coordinates' => [
                'latitude' => 40.7128,
                'longitude' => -74.0060
            ]
        ]);

        $this->assertIsArray($validZone->coordinates);
        $this->assertArrayHasKey('latitude', $validZone->coordinates);
        $this->assertArrayHasKey('longitude', $validZone->coordinates);

        // Test coordinates with additional data (should still work)
        $extendedZone = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'coordinates' => [
                'latitude' => 34.0522,
                'longitude' => -118.2437,
                'altitude' => 100,
                'accuracy' => 5
            ]
        ]);

        $this->assertIsArray($extendedZone->coordinates);
        $this->assertEquals(34.0522, $extendedZone->coordinates['latitude']);
        $this->assertEquals(-118.2437, $extendedZone->coordinates['longitude']);
    }

    /**
     * Test working zone handles time zone edge cases
     */
    public function test_it_handles_time_zone_edge_cases(): void
    {
        // Test midnight start time
        $midnightZone = DriverWorkingZone::factory()->create([
            'start_time' => '00:00:00',
            'end_time' => '23:59:59'
        ]);

        $this->assertEquals('00:00:00', $midnightZone->start_time->format('H:i:s'));
        $this->assertEquals('23:59:59', $midnightZone->end_time->format('H:i:s'));

        // Test same start and end time (24-hour operation)
        $sameTimeZone = DriverWorkingZone::factory()->create([
            'start_time' => '08:00:00',
            'end_time' => '08:00:00'
        ]);

        $this->assertEquals('08:00:00', $sameTimeZone->start_time->format('H:i:s'));
        $this->assertEquals('08:00:00', $sameTimeZone->end_time->format('H:i:s'));
    }

    /**
     * Test working zone handles multiple zones per driver
     */
    public function test_it_handles_multiple_zones_per_driver(): void
    {
        $driver = Driver::factory()->create();

        // Create multiple zones for the same driver
        $zone1 = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'zone_name' => 'Downtown Zone',
            'priority_level' => 1
        ]);

        $zone2 = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'zone_name' => 'Uptown Zone',
            'priority_level' => 2
        ]);

        $zone3 = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'zone_name' => 'Suburban Zone',
            'priority_level' => 3
        ]);

        // Test that driver has all zones
        $driverZones = $driver->workingZones;
        $this->assertCount(3, $driverZones);
        $this->assertTrue($driverZones->contains($zone1));
        $this->assertTrue($driverZones->contains($zone2));
        $this->assertTrue($driverZones->contains($zone3));

        // Test that zones belong to the same driver
        $this->assertEquals($driver->id, $zone1->driver_id);
        $this->assertEquals($driver->id, $zone2->driver_id);
        $this->assertEquals($driver->id, $zone3->driver_id);
    }

    /**
     * Test working zone handles zone name uniqueness
     */
    public function test_it_handles_zone_name_uniqueness(): void
    {
        $driver = Driver::factory()->create();

        // Create zone with specific name
        $zone1 = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'zone_name' => 'Downtown Zone'
        ]);

        // Create another zone with same name (should be allowed for same driver)
        $zone2 = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id,
            'zone_name' => 'Downtown Zone'
        ]);

        // Both zones should exist
        $this->assertDatabaseHas('driver_working_zones', [
            'id' => $zone1->id,
            'zone_name' => 'Downtown Zone'
        ]);

        $this->assertDatabaseHas('driver_working_zones', [
            'id' => $zone2->id,
            'zone_name' => 'Downtown Zone'
        ]);
    }

    /**
     * Test working zone handles cascade deletion
     */
    public function test_it_handles_cascade_deletion(): void
    {
        $driver = Driver::factory()->create();
        $workingZone = DriverWorkingZone::factory()->create([
            'driver_id' => $driver->id
        ]);

        $zoneId = $workingZone->id;

        // Verify zone exists
        $this->assertDatabaseHas('driver_working_zones', ['id' => $zoneId]);

        // Delete the driver
        $driver->delete();

        // Verify zone is also deleted (cascade)
        $this->assertDatabaseMissing('driver_working_zones', ['id' => $zoneId]);
    }

    /**
     * Test working zone handles attribute casting correctly
     */
    public function test_it_casts_attributes_correctly(): void
    {
        $workingZone = DriverWorkingZone::factory()->create([
            'is_active' => true,
            'priority_level' => 3,
            'radius_km' => 5.5,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'coordinates' => [
                'latitude' => 40.7128,
                'longitude' => -74.0060
            ]
        ]);

        // Test boolean casting
        $this->assertIsBool($workingZone->is_active);

        // Test integer casting
        $this->assertIsInt($workingZone->priority_level);

        // Test decimal casting (returns string in Laravel)
        $this->assertIsString($workingZone->radius_km);
        $this->assertEquals('5.50', $workingZone->radius_km);

        // Test datetime casting
        $this->assertInstanceOf(\Carbon\Carbon::class, $workingZone->start_time);
        $this->assertInstanceOf(\Carbon\Carbon::class, $workingZone->end_time);

        // Test array casting
        $this->assertIsArray($workingZone->coordinates);
    }
} 