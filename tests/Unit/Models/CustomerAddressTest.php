<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = Customer::factory()->create();
        $this->address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id
        ]);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'customer_id',
            'label',
            'street_address',
            'apartment_number',
            'building_name',
            'floor_number',
            'city',
            'state',
            'postal_code',
            'country',
            'latitude',
            'longitude',
            'delivery_notes',
            'is_default',
            'is_validated',
            'validated_at',
        ];

        $this->assertEquals($fillable, $this->address->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $casts = [
            'id' => 'int',
            'is_default' => 'boolean',
            'is_validated' => 'boolean',
            'validated_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        $this->assertEquals($casts, $this->address->getCasts());
    }

    #[Test]
    public function it_belongs_to_customer()
    {
        $this->assertInstanceOf(Customer::class, $this->address->customer);
        $this->assertEquals($this->customer->id, $this->address->customer->id);
    }

    #[Test]
    public function it_has_valid_label_values()
    {
        $validLabels = ['Home', 'Work', 'Other'];

        foreach ($validLabels as $label) {
            $address = CustomerAddress::factory()->create([
                'customer_id' => $this->customer->id,
                'label' => $label
            ]);
            $this->assertEquals($label, $address->label);
        }
    }

    #[Test]
    public function it_handles_geolocation_data()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060
        ]);

        $this->assertEquals(40.7128, $address->latitude);
        $this->assertEquals(-74.0060, $address->longitude);
    }

    #[Test]
    public function it_handles_default_address_logic()
    {
        // Create first address as default
        $address1 = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Create second address as default
        $address2 = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        // Check that both addresses exist
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address1->id,
            'is_default' => true
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address2->id,
            'is_default' => true
        ]);
    }

    #[Test]
    public function it_scopes_default_addresses()
    {
        CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => false
        ]);

        $defaultAddresses = CustomerAddress::default()->get();
        $this->assertGreaterThan(0, $defaultAddresses->count());
        
        foreach ($defaultAddresses as $address) {
            $this->assertTrue($address->is_default);
        }
    }

    #[Test]
    public function it_handles_validation_status()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_validated' => true,
            'validated_at' => now()
        ]);

        $this->assertTrue($address->is_validated);
        $this->assertNotNull($address->validated_at);
    }

    #[Test]
    public function it_handles_delivery_notes()
    {
        $deliveryNotes = 'Please ring doorbell and leave at front door';
        
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'delivery_notes' => $deliveryNotes
        ]);

        $this->assertEquals($deliveryNotes, $address->delivery_notes);
    }

    #[Test]
    public function it_handles_apartment_and_building_info()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'apartment_number' => '4B',
            'building_name' => 'Sunset Towers',
            'floor_number' => '3'
        ]);

        $this->assertEquals('4B', $address->apartment_number);
        $this->assertEquals('Sunset Towers', $address->building_name);
        $this->assertEquals('3', $address->floor_number);
    }

    #[Test]
    public function it_handles_address_formatting()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'street_address' => '123 Main Street',
            'apartment_number' => 'Apt 4B',
            'building_name' => 'Sunset Towers',
            'floor_number' => '3',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA'
        ]);

        $this->assertEquals('123 Main Street', $address->street_address);
        $this->assertEquals('Apt 4B', $address->apartment_number);
        $this->assertEquals('Sunset Towers', $address->building_name);
        $this->assertEquals('3', $address->floor_number);
        $this->assertEquals('New York', $address->city);
        $this->assertEquals('NY', $address->state);
        $this->assertEquals('10001', $address->postal_code);
        $this->assertEquals('USA', $address->country);
    }

    #[Test]
    public function it_requires_customer_id()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        CustomerAddress::create([
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001'
        ]);
    }

    #[Test]
    public function it_requires_street_address()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001'
        ]);
    }

    #[Test]
    public function it_requires_city()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street_address' => '123 Main Street',
            'state' => 'NY',
            'postal_code' => '10001'
        ]);
    }

    #[Test]
    public function it_requires_state()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'postal_code' => '10001'
        ]);
    }

    #[Test]
    public function it_requires_postal_code()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY'
        ]);
    }

    #[Test]
    public function it_has_default_country()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id
        ]);

        $this->assertEquals('Saudi Arabia', $address->country);
    }

    #[Test]
    public function it_has_default_label()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id
        ]);

        $validLabels = ['Home', 'Work', 'Office', 'Other'];
        $this->assertContains($address->label, $validLabels);
    }

    #[Test]
    public function it_handles_multiple_addresses_per_customer()
    {
        CustomerAddress::factory()->count(3)->create([
            'customer_id' => $this->customer->id
        ]);

        $addresses = $this->customer->addresses;
        $this->assertCount(4, $addresses); // Including the one from setUp
    }

    #[Test]
    public function it_validates_coordinate_ranges()
    {
        // Valid coordinates
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060
        ]);

        $this->assertEquals(40.7128, $address->latitude);
        $this->assertEquals(-74.0060, $address->longitude);

        // Test boundary values
        $address2 = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude' => 90.0,
            'longitude' => 180.0
        ]);

        $this->assertEquals(90.0, $address2->latitude);
        $this->assertEquals(180.0, $address2->longitude);
    }
} 