<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerAddressControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
    protected Customer $customer;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->customer = Customer::factory()->create();
        
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
        
        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'active'
        ]);
        
        $this->customerService = User::factory()->create([
            'role' => 'CUSTOMER_SERVICE',
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
            'permissions' => ['customer:update-basic-info']
        ]);
    }

    #[Test]
    public function it_lists_customer_addresses()
    {
        CustomerAddress::factory()->count(3)->create(['customer_id' => $this->customer->id]);

        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$this->customer->id}/addresses");

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'customer_id',
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'state',
                    'postal_code',
                    'country',
                    'is_default',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $responseData = $response->json();
        $this->assertCount(3, $responseData);
    }

    #[Test]
    public function it_shows_address_details()
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$this->customer->id}/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'customer_id',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
                'is_default',
                'latitude',
                'longitude',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'id' => $address->id,
                'customer_id' => $this->customer->id
            ]);
    }

    #[Test]
    public function it_creates_new_address()
    {
        $addressData = [
            'street_address' => '123 Main Street',
            'apartment_number' => 'Apt 4B',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA',
            'is_default' => true,
            'latitude' => 40.7128,
            'longitude' => -74.0060
        ];

        $response = $this->actingAs($this->customerService)
            ->postJson("/api/customers/{$this->customer->id}/addresses", $addressData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'customer_id',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
                'is_default',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'address_line_1' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY'
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'street_address' => '123 Main Street',
            'city' => 'New York'
        ]);
    }

    #[Test]
    public function it_validates_address_creation_data()
    {
        $invalidData = [
            'street_address' => '', // Required
            'city' => '', // Required
            'postal_code' => '123', // Too short
        ];

        $response = $this->actingAs($this->customerService)
            ->postJson("/api/customers/{$this->customer->id}/addresses", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['street_address', 'city', 'state', 'country']);
    }

    #[Test]
    public function it_enforces_address_creation_permissions()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);

        $addressData = [
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA'
        ];

        $response = $this->actingAs($unauthorizedUser)
            ->postJson("/api/customers/{$this->customer->id}/addresses", $addressData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_updates_address_information()
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $updateData = [
            'street_address' => '456 Oak Avenue',
            'apartment_number' => 'Suite 10',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'postal_code' => '90210',
            'is_default' => false
        ];

        $response = $this->actingAs($this->customerService)
            ->putJson("/api/customers/{$this->customer->id}/addresses/{$address->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'address_line_1' => '456 Oak Avenue',
                'city' => 'Los Angeles',
                'state' => 'CA'
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'street_address' => '456 Oak Avenue',
            'city' => 'Los Angeles'
        ]);
    }

    #[Test]
    public function it_validates_address_update_data()
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $invalidData = [
            'street_address' => '', // Required
            'postal_code' => '123' // Too short
        ];

        $response = $this->actingAs($this->customerService)
            ->putJson("/api/customers/{$this->customer->id}/addresses/{$address->id}", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['street_address']);
    }

    #[Test]
    public function it_enforces_address_update_permissions()
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);

        $updateData = [
            'street_address' => '456 Oak Avenue',
            'city' => 'Los Angeles'
        ];

        $response = $this->actingAs($unauthorizedUser)
            ->putJson("/api/customers/{$this->customer->id}/addresses/{$address->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_deletes_address()
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/customers/{$this->customer->id}/addresses/{$address->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('customer_addresses', [
            'id' => $address->id
        ]);
    }

    #[Test]
    public function it_enforces_address_deletion_permissions()
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
        $unauthorizedUser = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->deleteJson("/api/customers/{$this->customer->id}/addresses/{$address->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_address_not_found()
    {
        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$this->customer->id}/addresses/99999");

        $response->assertStatus(404);
    }

    #[Test]
    public function it_handles_geolocation_data()
    {
        $addressData = [
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA',
            'latitude' => 40.7128,
            'longitude' => -74.0060
        ];

        $response = $this->actingAs($this->customerService)
            ->postJson("/api/customers/{$this->customer->id}/addresses", $addressData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060
        ]);
    }

    #[Test]
    public function it_handles_default_address_management()
    {
        // Create first address as default
        $address1 = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Create second address as default (should make first non-default)
        $addressData = [
            'street_address' => '456 Oak Avenue',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'postal_code' => '90210',
            'country' => 'USA',
            'is_default' => true
        ];

        $response = $this->actingAs($this->customerService)
            ->postJson("/api/customers/{$this->customer->id}/addresses", $addressData);

        $response->assertStatus(201);

        // Check that first address is no longer default
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address1->id,
            'is_default' => false
        ]);

        // Check that new address is default
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'street_address' => '456 Oak Avenue',
            'is_default' => true
        ]);
    }

    #[Test]
    public function it_validates_coordinates_range()
    {
        $addressData = [
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA',
            'latitude' => 200.0, // Invalid latitude
            'longitude' => -74.0060
        ];

        $response = $this->actingAs($this->customerService)
            ->postJson("/api/customers/{$this->customer->id}/addresses", $addressData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }

    #[Test]
    public function it_handles_address_formatting()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'street_address' => '123 Main Street',
            'apartment_number' => 'Apt 4B',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA'
        ]);

        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$this->customer->id}/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson([
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA'
            ]);
    }
} 