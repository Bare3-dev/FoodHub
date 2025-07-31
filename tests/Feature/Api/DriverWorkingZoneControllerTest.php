<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\DriverWorkingZone;
use App\Models\Driver;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class DriverWorkingZoneControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected DriverWorkingZone $driverWorkingZone;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with active status
        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active'
        ]);
        $this->restaurantOwner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'status' => 'active'
        ]);
        $this->branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'status' => 'active'
        ]);
        
        // Create test driver and working zone
        $this->driver = Driver::factory()->create();
        $this->driverWorkingZone = DriverWorkingZone::factory()->create([
            'driver_id' => $this->driver->id
        ]);
    }

    /** @test */
    public function it_lists_driver_working_zones_with_pagination()
    {
        // Create multiple driver working zones
        DriverWorkingZone::factory()->count(15)->create();
        
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/driver-working-zones');
        
        $response->assertStatus(200);
        
        // Check if response has data key or is direct array
        $responseData = $response->json();
        if (isset($responseData['data'])) {
            $response->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'driver_id',
                        'zone_name',
                        'zone_description',
                        'coordinates',
                        'latitude',
                        'longitude',
                        'radius_km',
                        'is_active',
                        'priority_level',
                        'start_time',
                        'end_time',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'links',
                'meta'
            ]);
            
            // Verify pagination
            $response->assertJsonCount(15, 'data');
        } else {
            // Direct array response
            $response->assertJsonStructure([
                '*' => [
                    'id',
                    'driver_id',
                    'zone_name',
                    'zone_description',
                    'coordinates',
                    'latitude',
                    'longitude',
                    'radius_km',
                    'is_active',
                    'priority_level',
                    'start_time',
                    'end_time',
                    'created_at',
                    'updated_at'
                ]
            ]);
            
            // Verify count
            $response->assertJsonCount(15);
        }
    }

    /** @test */
    public function it_creates_new_driver_working_zone()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $zoneData = [
            'driver_id' => $this->driver->id,
            'zone_name' => 'Downtown Zone',
            'zone_description' => 'Central business district delivery zone',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_km' => 5.0,
            'is_active' => true,
            'priority_level' => 3,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00'
        ];
        
        $response = $this->postJson('/api/driver-working-zones', $zoneData);
        
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'driver_id',
                        'zone_name',
                        'zone_description',
                        'coordinates',
                        'latitude',
                        'longitude',
                        'radius_km',
                        'is_active',
                        'priority_level',
                        'start_time',
                        'end_time',
                        'created_at',
                        'updated_at'
                    ]
                ]);
        
        $this->assertDatabaseHas('driver_working_zones', [
            'driver_id' => $this->driver->id,
            'zone_name' => 'Downtown Zone',
            'radius_km' => 5.0,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_shows_specific_driver_working_zone()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson("/api/driver-working-zones/{$this->driverWorkingZone->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'driver_id',
                        'zone_name',
                        'zone_description',
                        'coordinates',
                        'latitude',
                        'longitude',
                        'radius_km',
                        'is_active',
                        'priority_level',
                        'start_time',
                        'end_time',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_updates_driver_working_zone()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $updateData = [
            'zone_name' => 'Updated Zone Name',
            'zone_description' => 'Updated zone description',
            'latitude' => 40.7589,
            'longitude' => -73.9851,
            'radius_km' => 7.5,
            'is_active' => false,
            'priority_level' => 4
        ];
        
        $response = $this->putJson("/api/driver-working-zones/{$this->driverWorkingZone->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJsonFragment([
                    'zone_name' => 'Updated Zone Name',
                    'zone_description' => 'Updated zone description',
                    'radius_km' => '7.50', // Cast as decimal:2
                    'is_active' => false
                ]);
        
        // Check database with transformed data
        $this->assertDatabaseHas('driver_working_zones', [
            'id' => $this->driverWorkingZone->id,
            'zone_name' => 'Updated Zone Name',
            'zone_description' => 'Updated zone description',
            'radius_km' => 7.5,
            'is_active' => false
        ]);
    }

    /** @test */
    public function it_deletes_driver_working_zone()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->deleteJson("/api/driver-working-zones/{$this->driverWorkingZone->id}");
        
        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('driver_working_zones', [
            'id' => $this->driverWorkingZone->id
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->postJson('/api/driver-working-zones', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['driver_id', 'zone_name', 'latitude', 'longitude', 'radius_km']);
    }

    /** @test */
    public function it_validates_coordinate_ranges()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $invalidData = [
            'driver_id' => $this->driver->id,
            'zone_name' => 'Invalid Zone',
            'latitude' => 200.0, // Invalid latitude
            'longitude' => -200.0, // Invalid longitude
            'radius_km' => -5.0 // Invalid radius
        ];
        
        $response = $this->postJson('/api/driver-working-zones', $invalidData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['latitude', 'longitude', 'radius_km']);
    }

    /** @test */
    public function it_enforces_pagination_limits()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/driver-working-zones?per_page=150');
        
        $response->assertStatus(200);
        
        // Should default to maximum of 100 items
        $responseData = $response->json();
        $this->assertLessThanOrEqual(100, count($responseData['data']));
    }

    /** @test */
    public function it_handles_nonexistent_zone()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/driver-working-zones/99999');
        
        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_zone_with_driver_info()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson("/api/driver-working-zones/{$this->driverWorkingZone->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'driver_id',
                        'zone_name',
                        'zone_description',
                        'coordinates',
                        'radius_km',
                        'is_active',
                        'priority_level',
                        'driver' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'status',
                            'is_available'
                        ],
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_filters_zones_by_active_status()
    {
        // Create active and inactive zones
        DriverWorkingZone::factory()->create(['is_active' => true]);
        DriverWorkingZone::factory()->create(['is_active' => false]);
        
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/driver-working-zones?is_active=1');
        
        $response->assertStatus(200);
        
        $zones = $response->json('data');
        foreach ($zones as $zone) {
            $this->assertTrue($zone['is_active']);
        }
    }

    // ========== ENHANCED GEOSPATIAL TESTS ==========

    /** @test */
    public function it_calculates_distance_between_points()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create zones with known coordinates
        $zone1 = DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 5.0)->create();
        $zone2 = DriverWorkingZone::factory()->withCoordinates(40.7589, -73.9851, 3.0)->create();
        
        $response = $this->getJson('/api/driver-working-zones?calculate_distance=1&lat=40.7128&lng=-74.0060');
        
        $response->assertStatus(200);
        
        $zones = $response->json('data');
        $this->assertNotEmpty($zones);
        
        // Verify distance calculation is included
        foreach ($zones as $zone) {
            $this->assertArrayHasKey('distance_km', $zone);
            $this->assertIsNumeric($zone['distance_km']);
        }
    }

    /** @test */
    public function it_detects_address_within_zone_boundaries()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create zone with specific coordinates (Downtown Manhattan)
        $zone = DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 10.0)->create();
        
        // Test address within zone (Midtown Manhattan - closer to zone center)
        $response = $this->getJson('/api/driver-working-zones?check_address=1&lat=40.7589&lng=-73.9851&per_page=100');
        
        $response->assertStatus(200);
        
        $zones = $response->json('data');
        $this->assertNotEmpty($zones);
        
        // Should find zones that include this address
        $foundZone = collect($zones)->firstWhere('id', $zone->id);
        $this->assertNotNull($foundZone);
        
        $this->assertTrue($foundZone['address_within_zone'] ?? false);
    }

    /** @test */
    public function it_detects_address_outside_zone_boundaries()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create zone with specific coordinates
        $zone = DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 5.0)->create();
        
        // Test address outside zone (Brooklyn)
        $response = $this->getJson('/api/driver-working-zones?check_address=1&lat=40.6782&lng=-73.9442&per_page=100');
        
        $response->assertStatus(200);
        
        $zones = $response->json('data');
        
        // Should not find zones that include this address
        $foundZone = collect($zones)->firstWhere('id', $zone->id);
        if ($foundZone) {
            $this->assertFalse($foundZone['address_within_zone'] ?? true);
        }
    }

    /** @test */
    public function it_optimizes_route_for_multiple_stops()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create multiple zones
        $zones = [
            DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 5.0)->create(),
            DriverWorkingZone::factory()->withCoordinates(40.7589, -73.9851, 3.0)->create(),
            DriverWorkingZone::factory()->withCoordinates(40.7505, -73.9934, 4.0)->create(),
        ];
        
        // Test route optimization
        $stops = [
            ['latitude' => 40.7128, 'longitude' => -74.0060, 'priority' => 1],
            ['latitude' => 40.7589, 'longitude' => -73.9851, 'priority' => 2],
            ['latitude' => 40.7505, 'longitude' => -73.9934, 'priority' => 3],
        ];
        
        $response = $this->postJson('/api/driver-working-zones/optimize-route', [
            'stops' => $stops,
            'driver_id' => $this->driver->id
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'optimized_route',
                        'total_distance',
                        'estimated_time'
                    ]
                ]);
    }

    /** @test */
    public function it_assigns_driver_based_on_proximity_and_availability()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create drivers with different locations and availability
        $driver1 = Driver::factory()->create([
            'current_latitude' => 40.7128,
            'current_longitude' => -74.0060,
            'is_available' => true
        ]);
        
        $driver2 = Driver::factory()->create([
            'current_latitude' => 40.7589,
            'current_longitude' => -73.9851,
            'is_available' => true
        ]);
        
        $driver3 = Driver::factory()->create([
            'current_latitude' => 40.7505,
            'current_longitude' => -73.9934,
            'is_available' => false
        ]);
        
        // Create zones for each driver
        DriverWorkingZone::factory()->create(['driver_id' => $driver1->id]);
        DriverWorkingZone::factory()->create(['driver_id' => $driver2->id]);
        DriverWorkingZone::factory()->create(['driver_id' => $driver3->id]);
        
        // Create a valid order for testing
        $order = \App\Models\Order::factory()->create();
        
        // Test driver assignment
        $response = $this->postJson('/api/driver-working-zones/assign-driver', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'order_id' => $order->id
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'driver_id',
                        'driver_name',
                        'distance_km',
                        'estimated_pickup_time'
                    ]
                ]);
        
        // Should assign the closest available driver
        $assignedDriver = $response->json('data');
        $this->assertEquals($driver1->id, $assignedDriver['driver_id']);
    }

    /** @test */
    public function it_handles_concurrent_driver_assignments()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create multiple drivers in different areas
        $drivers = [];
        for ($i = 0; $i < 5; $i++) {
            $drivers[] = Driver::factory()->create([
                'current_latitude' => 40.7128 + ($i * 0.01),
                'current_longitude' => -74.0060 + ($i * 0.01),
                'is_available' => true
            ]);
        }
        
        // Create zones for each driver
        foreach ($drivers as $driver) {
            DriverWorkingZone::factory()->create(['driver_id' => $driver->id]);
        }
        
        // Create valid orders for testing
        $orders = \App\Models\Order::factory()->count(3)->create();
        
        // Simulate concurrent assignment requests
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson('/api/driver-working-zones/assign-driver', [
                'latitude' => 40.7128 + ($i * 0.001),
                'longitude' => -74.0060 + ($i * 0.001),
                'order_id' => $orders[$i]->id
            ]);
        }
        
        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Verify we got valid driver assignments
        $assignedDriverIds = collect($responses)->map(function ($response) {
            return $response->json('data.driver_id');
        })->filter()->unique();
        
        $this->assertGreaterThan(0, $assignedDriverIds->count());
    }

    /** @test */
    public function it_validates_zone_name_uniqueness_per_driver()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create first zone
        $zoneData = [
            'driver_id' => $this->driver->id,
            'zone_name' => 'Unique Zone',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_km' => 5.0
        ];
        
        $this->postJson('/api/driver-working-zones', $zoneData);
        
        // Try to create second zone with same name for same driver
        $response = $this->postJson('/api/driver-working-zones', $zoneData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['zone_name']);
    }

    /** @test */
    public function it_allows_same_name_for_different_drivers()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $driver2 = Driver::factory()->create();
        
        $zoneData = [
            'zone_name' => 'Same Name Zone',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_km' => 5.0
        ];
        
        // Create zone for first driver
        $zoneData['driver_id'] = $this->driver->id;
        $this->postJson('/api/driver-working-zones', $zoneData);
        
        // Create zone with same name for different driver
        $zoneData['driver_id'] = $driver2->id;
        $response = $this->postJson('/api/driver-working-zones', $zoneData);
        
        $response->assertStatus(201);
    }

    /** @test */
    public function it_handles_zone_radius_validation()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $zoneData = [
            'driver_id' => $this->driver->id,
            'zone_name' => 'Test Zone',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_km' => 0 // Invalid radius
        ];
        
        $response = $this->postJson('/api/driver-working-zones', $zoneData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['radius_km']);
    }

    /** @test */
    public function it_returns_proper_error_for_invalid_zone_id()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->putJson('/api/driver-working-zones/invalid-id', []);
        
        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_zone_deletion_with_active_driver()
    {
        // Create driver and assign to zone
        $driver = Driver::factory()->create(['is_available' => true]);
        $this->driverWorkingZone->update(['driver_id' => $driver->id]);
        
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->deleteJson("/api/driver-working-zones/{$this->driverWorkingZone->id}");
        
        $response->assertStatus(204);
        
        // Verify zone is deleted but driver remains
        $this->assertDatabaseMissing('driver_working_zones', [
            'id' => $this->driverWorkingZone->id
        ]);
        
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id
        ]);
    }

    /** @test */
    public function it_calculates_estimated_delivery_time()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create zone with specific coordinates
        $zone = DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 5.0)->create();
        
        $response = $this->postJson('/api/driver-working-zones/calculate-delivery-time', [
            'pickup_latitude' => 40.7128,
            'pickup_longitude' => -74.0060,
            'delivery_latitude' => 40.7589,
            'delivery_longitude' => -73.9851
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'distance_km',
                        'estimated_minutes',
                        'estimated_arrival'
                    ]
                ]);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['distance_km']);
        $this->assertGreaterThan(0, $data['estimated_minutes']);
    }

    /** @test */
    public function it_handles_edge_case_addresses_on_zone_boundaries()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create zone with specific coordinates
        $zone = DriverWorkingZone::factory()->withCoordinates(40.7128, -74.0060, 5.0)->create();
        
        // Test address exactly on zone boundary
        $boundaryLat = 40.7128 + (5.0 / 111.0); // 5km north in degrees
        $boundaryLng = -74.0060;
        
        $response = $this->getJson("/api/driver-working-zones?check_address=1&lat={$boundaryLat}&lng={$boundaryLng}");
        
        $response->assertStatus(200);
        
        $zones = $response->json('data');
        $foundZone = collect($zones)->firstWhere('id', $zone->id);
        
        // Should handle boundary cases gracefully
        $this->assertNotNull($foundZone);
    }

    /** @test */
    public function it_performs_high_volume_route_optimization()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create many zones for performance testing
        $zones = DriverWorkingZone::factory()->count(50)->create();
        
        // Create many delivery stops
        $stops = [];
        for ($i = 0; $i < 20; $i++) {
            $stops[] = [
                'latitude' => 40.7128 + ($i * 0.001),
                'longitude' => -74.0060 + ($i * 0.001),
                'priority' => rand(1, 5)
            ];
        }
        
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/driver-working-zones/optimize-route', [
            'stops' => $stops,
            'driver_id' => $this->driver->id
        ]);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        $response->assertStatus(200);
        
        // Should complete within reasonable time (less than 2 seconds)
        $this->assertLessThan(2.0, $executionTime);
        
        $data = $response->json('data');
        $this->assertCount(20, $data['optimized_route']);
    }
} 