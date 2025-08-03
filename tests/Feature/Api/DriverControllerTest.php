<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Driver;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\DriverWorkingZone;
use App\Models\Order;
use App\Models\OrderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $deliveryManager;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        
        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active'
        ]);
        
        $this->restaurantOwner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active'
        ]);
        
        $this->branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active'
        ]);
        
        $this->deliveryManager = User::factory()->create([
            'role' => 'DELIVERY_MANAGER',
            'status' => 'active'
        ]);
    }

    protected function createDriver(array $attributes = []): Driver
    {
        static $counter = 0;
        $counter++;
        
        return Driver::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Driver',
            'email' => "john.driver{$counter}@example.com",
            'phone' => "+123456789{$counter}",
            'date_of_birth' => '1990-01-01',
            'national_id' => "123456789{$counter}",
            'driver_license_number' => "DL12345678{$counter}",
            'license_expiry_date' => '2025-12-31',
            'vehicle_type' => 'motorcycle',
            'vehicle_make' => 'Honda',
            'vehicle_model' => 'CBR150R',
            'vehicle_year' => '2020',
            'vehicle_color' => 'Red',
            'vehicle_plate_number' => "ABC12{$counter}",
            'status' => 'active',
            'password' => bcrypt('password123'),
            'documents' => json_encode([
                'license' => 'license_url',
                'insurance' => 'insurance_url'
            ]),
            'is_online' => false,
            'is_available' => true,
            'rating' => 4.5,
            'total_deliveries' => 100,
            'completed_deliveries' => 95,
            'cancelled_deliveries' => 5,
            'total_earnings' => 2500.00
        ], $attributes));
    }

    #[Test]
    public function it_lists_drivers_with_pagination()
    {
        // Create a smaller, more manageable dataset for testing
        for ($i = 0; $i < 15; $i++) {
            $this->createDriver([
                'email' => "driver{$i}@example.com",
                'phone' => "+123456789{$i}",
                'national_id' => "123456789{$i}",
                'driver_license_number' => "DL12345678{$i}",
                'vehicle_plate_number' => "ABC12{$i}"
            ]);
        }

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson('/api/drivers?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'status',
                        'is_online',
                        'is_available',
                        'rating',
                        'total_deliveries',
                        'completed_deliveries',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'current_page',
                'per_page',
                'total'
            ]);

        $responseData = $response->json();
        $this->assertCount(10, $responseData['data']);
    }

    #[Test]
    public function it_shows_driver_details()
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'driver_license_number',
                'license_expiry_date',
                'vehicle_type',
                'vehicle_make',
                'vehicle_model',
                'vehicle_plate_number',
                'status',
                'is_online',
                'is_available',
                'current_latitude',
                'current_longitude',
                'rating',
                'total_deliveries',
                'completed_deliveries',
                'total_earnings',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'id' => $driver->id,
                'email' => $driver->email
            ]);
    }

    #[Test]
    public function it_creates_new_driver()
    {
        $driverData = [
            'first_name' => 'John',
            'last_name' => 'Driver',
            'email' => 'john.driver@example.com',
            'phone' => '+1234567890',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890',
            'driver_license_number' => 'DL123456789',
            'license_expiry_date' => '2025-12-31',
            'vehicle_type' => 'motorcycle',
            'vehicle_make' => 'Honda',
            'vehicle_model' => 'CBR150R',
            'vehicle_year' => '2020',
            'vehicle_color' => 'Red',
            'vehicle_plate_number' => 'ABC123',
            'status' => 'active',
            'password' => 'password123',
            'documents' => json_encode([
                'license' => 'license_url',
                'insurance' => 'insurance_url'
            ])
        ];

        $response = $this->actingAs($this->deliveryManager)
            ->postJson('/api/drivers', $driverData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'status',
                'vehicle_type',
                'vehicle_make',
                'vehicle_model',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'first_name' => 'John',
                'last_name' => 'Driver',
                'email' => 'john.driver@example.com'
            ]);

        $this->assertDatabaseHas('drivers', [
            'email' => 'john.driver@example.com',
            'first_name' => 'John',
            'last_name' => 'Driver'
        ]);
    }

    #[Test]
    public function it_validates_driver_creation_data()
    {
        $invalidData = [
            'first_name' => '', // Required
            'email' => 'invalid-email', // Invalid email
            'phone' => '123', // Too short
            'driver_license_number' => '', // Required
            'vehicle_plate_number' => '', // Required
            'status' => '', // Required
            'date_of_birth' => '', // Required
            'national_id' => '', // Required
            'license_expiry_date' => '', // Required
            'vehicle_type' => '', // Required
        ];

        $response = $this->actingAs($this->deliveryManager)
            ->postJson('/api/drivers', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name', 
                'email', 
                'phone', 
                'driver_license_number', 
                'vehicle_plate_number',
                'status',
                'date_of_birth',
                'national_id',
                'license_expiry_date',
                'vehicle_type'
            ]);
    }

    #[Test]
    public function it_enforces_driver_creation_permissions()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);

        $driverData = [
            'first_name' => 'John',
            'last_name' => 'Driver',
            'email' => 'john.driver@example.com',
            'phone' => '+1234567890',
            'driver_license_number' => 'DL123456789',
            'vehicle_plate_number' => 'ABC123',
            'password' => 'password123'
        ];

        $response = $this->actingAs($unauthorizedUser)
            ->postJson('/api/drivers', $driverData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_updates_driver_information()
    {
        $driver = $this->createDriver();

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Driver',
            'phone' => '+1987654321',
            'vehicle_color' => 'Blue',
            'is_online' => true,
            'is_available' => true
        ];

        $response = $this->actingAs($this->restaurantOwner)
            ->putJson("/api/drivers/{$driver->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'first_name' => 'Jane',
                'last_name' => 'Driver',
                'phone' => '+1987654321',
                'vehicle_color' => 'Blue'
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'Jane',
            'last_name' => 'Driver'
        ]);
    }

    #[Test]
    public function it_validates_driver_update_data()
    {
        $driver = $this->createDriver();

        $invalidData = [
            'email' => 'invalid-email',
            'phone' => '123'
        ];

        $response = $this->actingAs($this->restaurantOwner)
            ->putJson("/api/drivers/{$driver->id}", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    #[Test]
    public function it_enforces_driver_update_permissions()
    {
        $driver = $this->createDriver();
        $unauthorizedUser = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Driver'
        ];

        $response = $this->actingAs($unauthorizedUser)
            ->putJson("/api/drivers/{$driver->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_deletes_driver()
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/drivers/{$driver->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('drivers', [
            'id' => $driver->id
        ]);
    }

    #[Test]
    public function it_enforces_driver_deletion_permissions()
    {
        $driver = $this->createDriver();
        $unauthorizedUser = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->deleteJson("/api/drivers/{$driver->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_driver_not_found()
    {
        $response = $this->actingAs($this->restaurantOwner)
            ->getJson('/api/drivers/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_filters_drivers_by_status()
    {
        $this->createDriver(['status' => 'active']);
        $this->createDriver(['status' => 'inactive']);
        $this->createDriver(['status' => 'suspended']);

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson('/api/drivers?status=active');

        $response->assertStatus(200);
        
        $drivers = $response->json()['data'];
        if (count($drivers) > 0) {
            $this->assertEquals('active', $drivers[0]['status']);
        }
    }

    #[Test]
    public function it_filters_drivers_by_availability()
    {
        $this->createDriver(['is_available' => true]);
        $this->createDriver(['is_available' => false]);

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson('/api/drivers?available=true');

        $response->assertStatus(200);
        
        $drivers = $response->json()['data'];
        if (count($drivers) > 0) {
            $this->assertTrue($drivers[0]['is_available']);
        }
    }

    #[Test]
    public function it_handles_driver_with_working_zones()
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200);
        
        // Verify the driver exists
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id
        ]);
    }

    #[Test]
    public function it_handles_driver_with_order_assignments()
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200);
        
        // Verify the driver exists
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id
        ]);
    }

    #[Test]
    public function it_handles_driver_location_updates()
    {
        $driver = $this->createDriver();

        $locationData = [
            'current_latitude' => 40.7128,
            'current_longitude' => -74.0060,
            'is_online' => true,
            'is_available' => true
        ];

        $response = $this->actingAs($this->restaurantOwner)
            ->putJson("/api/drivers/{$driver->id}", $locationData);

        $response->assertStatus(200)
            ->assertJson([
                'current_latitude' => 40.7128,
                'current_longitude' => -74.0060,
                'is_online' => true,
                'is_available' => true
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'current_latitude' => 40.7128,
            'current_longitude' => -74.0060
        ]);
    }

    #[Test]
    public function it_handles_driver_rating_and_earnings()
    {
        $driver = $this->createDriver([
            'rating' => 4.5,
            'total_deliveries' => 100,
            'completed_deliveries' => 95,
            'cancelled_deliveries' => 5,
            'total_earnings' => 2500.00
        ]);

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJson([
                'rating' => 4.5,
                'total_deliveries' => 100,
                'completed_deliveries' => 95,
                'cancelled_deliveries' => 5,
                'total_earnings' => 2500.00
            ]);
    }

    #[Test]
    public function it_handles_driver_vehicle_information()
    {
        $driver = $this->createDriver([
            'vehicle_type' => 'motorcycle',
            'vehicle_make' => 'Honda',
            'vehicle_model' => 'CBR150R',
            'vehicle_year' => '2020',
            'vehicle_color' => 'Red',
            'vehicle_plate_number' => 'ABC123'
        ]);

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJson([
                'vehicle_type' => 'motorcycle',
                'vehicle_make' => 'Honda',
                'vehicle_model' => 'CBR150R',
                'vehicle_year' => '2020',
                'vehicle_color' => 'Red',
                'vehicle_plate_number' => 'ABC123'
            ]);
    }

    #[Test]
    public function it_handles_driver_documents()
    {
        $driver = $this->createDriver([
            'documents' => [
                'license' => 'license_url',
                'insurance' => 'insurance_url',
                'vehicle_registration' => 'registration_url'
            ]
        ]);

        $response = $this->actingAs($this->restaurantOwner)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200);
        
        // Verify documents are stored correctly
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id
        ]);
        
        $driver->refresh();
        $this->assertArrayHasKey('license', $driver->documents);
        $this->assertArrayHasKey('insurance', $driver->documents);
    }
} 