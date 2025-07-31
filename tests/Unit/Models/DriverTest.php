<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Driver;
use App\Models\DriverWorkingZone;
use App\Models\OrderAssignment;
use App\Models\DeliveryReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DriverTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = Driver::factory()->create();
    }

    /**
     * Test driver has correct relationships
     */
    public function test_it_has_correct_relationships(): void
    {
        // Test working zones relationship
        $workingZone = DriverWorkingZone::factory()->create([
            'driver_id' => $this->driver->id
        ]);

        $this->assertTrue($this->driver->workingZones->contains($workingZone));
        $this->assertEquals($this->driver->id, $workingZone->driver->id);

        // Test order assignments relationship
        $orderAssignment = OrderAssignment::factory()->create([
            'driver_id' => $this->driver->id
        ]);

        $this->assertTrue($this->driver->orderAssignments->contains($orderAssignment));
        $this->assertEquals($this->driver->id, $orderAssignment->driver->id);

        // Test delivery reviews relationship
        $deliveryReview = DeliveryReview::factory()->create([
            'driver_id' => $this->driver->id
        ]);

        $this->assertTrue($this->driver->deliveryReviews->contains($deliveryReview));
        $this->assertEquals($this->driver->id, $deliveryReview->driver->id);
    }

    /**
     * Test driver validates required fields
     */
    public function test_it_validates_required_fields(): void
    {
        $requiredFields = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'password' => 'password123',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890',
            'driver_license_number' => 'DL123456',
            'license_expiry_date' => '2025-12-31',
            'vehicle_type' => 'car',
            'vehicle_plate_number' => 'ABC-1234'
        ];

        $driver = Driver::create($requiredFields);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com'
        ]);
    }

    /**
     * Test driver enforces business rules
     */
    public function test_it_enforces_business_rules(): void
    {
        // Test unique email constraint
        $driver1 = Driver::factory()->create(['email' => 'test@example.com']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Driver::factory()->create(['email' => 'test@example.com']);

        // Test unique phone constraint
        $driver2 = Driver::factory()->create(['phone' => '+1234567890']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Driver::factory()->create(['phone' => '+1234567890']);

        // Test unique national_id constraint
        $driver3 = Driver::factory()->create(['national_id' => '1234567890']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Driver::factory()->create(['national_id' => '1234567890']);

        // Test unique driver_license_number constraint
        $driver4 = Driver::factory()->create(['driver_license_number' => 'DL123456']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Driver::factory()->create(['driver_license_number' => 'DL123456']);

        // Test unique vehicle_plate_number constraint
        $driver5 = Driver::factory()->create(['vehicle_plate_number' => 'ABC-1234']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Driver::factory()->create(['vehicle_plate_number' => 'ABC-1234']);
    }

    /**
     * Test driver scopes data correctly
     */
    public function test_it_scopes_data_correctly(): void
    {
        // Create drivers with different statuses
        $activeDriver = Driver::factory()->create(['status' => 'active']);
        $inactiveDriver = Driver::factory()->create(['status' => 'inactive']);
        $suspendedDriver = Driver::factory()->create(['status' => 'suspended']);

        // Test active scope
        $activeDrivers = Driver::active()->get();
        $this->assertTrue($activeDrivers->contains($activeDriver));
        $this->assertFalse($activeDrivers->contains($inactiveDriver));
        $this->assertFalse($activeDrivers->contains($suspendedDriver));

        // Test online scope
        $onlineDriver = Driver::factory()->create(['is_online' => true]);
        $offlineDriver = Driver::factory()->create(['is_online' => false]);

        $onlineDrivers = Driver::online()->get();
        $this->assertTrue($onlineDrivers->contains($onlineDriver));
        $this->assertFalse($onlineDrivers->contains($offlineDriver));

        // Test available scope
        $availableDriver = Driver::factory()->create(['is_available' => true]);
        $unavailableDriver = Driver::factory()->create(['is_available' => false]);

        $availableDrivers = Driver::available()->get();
        $this->assertTrue($availableDrivers->contains($availableDriver));
        $this->assertFalse($availableDrivers->contains($unavailableDriver));
    }

    /**
     * Test driver handles soft deletes (if implemented)
     */
    public function test_it_handles_soft_deletes(): void
    {
        // Note: Driver model doesn't currently use soft deletes
        // This test is a placeholder for when soft deletes are implemented
        
        $driver = Driver::factory()->create();
        $driverId = $driver->id;

        // For now, just verify the driver exists
        $this->assertDatabaseHas('drivers', ['id' => $driverId]);

        // If soft deletes are added later, uncomment these tests:
        // $driver->delete();
        // $this->assertSoftDeleted('drivers', ['id' => $driverId]);
        // $this->assertTrue($driver->trashed());
    }

    /**
     * Test driver coordinates handling
     */
    public function test_it_handles_coordinates_correctly(): void
    {
        $driver = Driver::factory()->create([
            'current_latitude' => 40.7128,
            'current_longitude' => -74.0060
        ]);

        // Test getCoordinatesAttribute
        $coordinates = $driver->coordinates;
        $this->assertEquals(40.7128, $coordinates['latitude']);
        $this->assertEquals(-74.0060, $coordinates['longitude']);

        // Test setCoordinatesAttribute
        $driver->coordinates = [
            'latitude' => 34.0522,
            'longitude' => -118.2437
        ];

        $this->assertEquals(34.0522, $driver->current_latitude);
        $this->assertEquals(-118.2437, $driver->current_longitude);
    }

    /**
     * Test driver status transitions
     */
    public function test_it_handles_status_transitions(): void
    {
        $driver = Driver::factory()->create(['status' => 'pending_verification']);

        // Test status update
        $driver->update(['status' => 'active']);
        $this->assertEquals('active', $driver->fresh()->status);

        // Test invalid status
        $this->expectException(\Illuminate\Database\QueryException::class);
        $driver->update(['status' => 'invalid_status']);
    }

    /**
     * Test driver availability logic
     */
    public function test_it_handles_availability_logic(): void
    {
        $driver = Driver::factory()->create([
            'is_online' => false,
            'is_available' => false
        ]);

        // Test going online
        $driver->update(['is_online' => true]);
        $this->assertTrue($driver->fresh()->is_online);

        // Test going available
        $driver->update(['is_available' => true]);
        $this->assertTrue($driver->fresh()->is_available);

        // Test going offline
        $driver->update(['is_online' => false]);
        $this->assertFalse($driver->fresh()->is_online);
    }

    /**
     * Test driver rating calculations
     */
    public function test_it_handles_rating_calculations(): void
    {
        $driver = Driver::factory()->create(['rating' => 0.00]);

        // Test rating update
        $driver->update(['rating' => 4.5]);
        $this->assertEquals(4.5, $driver->fresh()->rating);

        // Test rating validation (should be between 0 and 5)
        $driver->update(['rating' => 5.0]);
        $this->assertEquals(5.0, $driver->fresh()->rating);

        $driver->update(['rating' => 0.0]);
        $this->assertEquals(0.0, $driver->fresh()->rating);
    }

    /**
     * Test driver delivery statistics
     */
    public function test_it_tracks_delivery_statistics(): void
    {
        $driver = Driver::factory()->create([
            'total_deliveries' => 0,
            'completed_deliveries' => 0,
            'cancelled_deliveries' => 0,
            'total_earnings' => 0.00
        ]);

        // Test delivery completion
        $driver->update([
            'total_deliveries' => 10,
            'completed_deliveries' => 8,
            'cancelled_deliveries' => 2,
            'total_earnings' => 150.50
        ]);

        $this->assertEquals(10, $driver->fresh()->total_deliveries);
        $this->assertEquals(8, $driver->fresh()->completed_deliveries);
        $this->assertEquals(2, $driver->fresh()->cancelled_deliveries);
        $this->assertEquals(150.50, $driver->fresh()->total_earnings);
    }

    /**
     * Test driver document handling
     */
    public function test_it_handles_documents_correctly(): void
    {
        $documents = [
            'id_document' => 'id_123.jpg',
            'license_document' => 'license_456.jpg',
            'vehicle_registration' => 'reg_789.jpg'
        ];

        $driver = Driver::factory()->create(['documents' => $documents]);

        $this->assertEquals($documents, $driver->documents);
        $this->assertIsArray($driver->documents);
    }

    /**
     * Test driver banking info encryption
     */
    public function test_it_encrypts_banking_info(): void
    {
        $bankingInfo = [
            'account_number' => '1234567890',
            'routing_number' => '987654321',
            'bank_name' => 'Test Bank'
        ];

        $driver = Driver::factory()->create(['banking_info' => $bankingInfo]);

        // Banking info should be accessible as array when accessed
        $this->assertEquals($bankingInfo, $driver->banking_info);
        
        // The raw value in database should be encrypted (not plain JSON)
        $rawValue = $driver->getRawOriginal('banking_info');
        $this->assertNotEquals(json_encode($bankingInfo), $rawValue);
        $this->assertIsString($rawValue);
    }

    /**
     * Test driver verification status
     */
    public function test_it_handles_verification_status(): void
    {
        // Create driver manually to avoid factory's random verification dates
        $driver = Driver::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'password' => 'password',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890',
            'driver_license_number' => 'DL123456',
            'license_expiry_date' => '2025-12-31',
            'vehicle_type' => 'car',
            'vehicle_plate_number' => 'ABC-1234',
            'status' => 'active'
        ]);

        // Test email verification
        $driver->update(['email_verified_at' => now()]);
        $freshDriver = $driver->fresh();
        $this->assertNotNull($freshDriver->email_verified_at, 'Email verification should be set');

        // Test phone verification
        $driver->update(['phone_verified_at' => now()]);
        $this->assertNotNull($driver->fresh()->phone_verified_at);

        // Test overall verification
        $driver->update(['verified_at' => now()]);
        $this->assertNotNull($driver->fresh()->verified_at);
    }

    /**
     * Test driver last active tracking
     */
    public function test_it_tracks_last_active_time(): void
    {
        $driver = Driver::factory()->create(['last_active_at' => null]);

        $now = now();
        $driver->update(['last_active_at' => $now]);

        $this->assertEquals($now->toDateTimeString(), $driver->fresh()->last_active_at->toDateTimeString());
    }

    /**
     * Test driver location updates
     */
    public function test_it_updates_location_correctly(): void
    {
        $driver = Driver::factory()->create([
            'current_latitude' => null,
            'current_longitude' => null,
            'last_location_update' => null
        ]);

        $newLatitude = 40.7128;
        $newLongitude = -74.0060;
        $updateTime = now();

        $driver->update([
            'current_latitude' => $newLatitude,
            'current_longitude' => $newLongitude,
            'last_location_update' => $updateTime
        ]);

        $this->assertEquals($newLatitude, $driver->fresh()->current_latitude);
        $this->assertEquals($newLongitude, $driver->fresh()->current_longitude);
        $this->assertEquals($updateTime->toDateTimeString(), $driver->fresh()->last_location_update->toDateTimeString());
    }

    /**
     * Test driver factory states
     */
    public function test_it_uses_factory_states_correctly(): void
    {
        // Test available state
        $availableDriver = Driver::factory()->available()->create();
        $this->assertTrue($availableDriver->is_available);
        $this->assertTrue($availableDriver->is_online);
        $this->assertEquals('active', $availableDriver->status);

        // Test unavailable state
        $unavailableDriver = Driver::factory()->unavailable()->create();
        $this->assertFalse($unavailableDriver->is_available);
        $this->assertFalse($unavailableDriver->is_online);

        // Test with coordinates state
        $driverWithCoords = Driver::factory()->withCoordinates(40.7128, -74.0060)->create();
        $this->assertEquals(40.7128, $driverWithCoords->current_latitude);
        $this->assertEquals(-74.0060, $driverWithCoords->current_longitude);
    }

    /**
     * Test driver hidden attributes
     */
    public function test_it_hides_sensitive_attributes(): void
    {
        $driver = Driver::factory()->create();

        $driverArray = $driver->toArray();

        // Sensitive attributes should be hidden
        $this->assertArrayNotHasKey('password', $driverArray);
        $this->assertArrayNotHasKey('national_id', $driverArray);
        $this->assertArrayNotHasKey('driver_license_number', $driverArray);
        $this->assertArrayNotHasKey('banking_info', $driverArray);
    }

    /**
     * Test driver attribute casting
     */
    public function test_it_casts_attributes_correctly(): void
    {
        $driver = Driver::factory()->create([
            'is_online' => true,
            'is_available' => false,
            'rating' => 4.5,
            'total_earnings' => 150.75,
            'date_of_birth' => '1990-01-01',
            'license_expiry_date' => '2025-12-31'
        ]);
        


        // Test boolean casting
        $this->assertIsBool($driver->is_online);
        $this->assertIsBool($driver->is_available);

        // Test decimal casting (Laravel decimal casts return strings)
        $this->assertIsString($driver->rating);
        $this->assertIsString($driver->total_earnings);
        $this->assertEquals('4.50', $driver->rating);
        $this->assertEquals('150.75', $driver->total_earnings);

        // Test date casting
        $this->assertInstanceOf(\Carbon\Carbon::class, $driver->date_of_birth);
        $this->assertInstanceOf(\Carbon\Carbon::class, $driver->license_expiry_date);

        // Test array casting
        $this->assertIsArray($driver->documents);
        // Note: banking_info is encrypted and may not be testable in this context
    }
} 