<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'first_name', 'last_name', 'email', 'phone', 'password',
            'date_of_birth', 'gender', 'profile_image_url', 'preferences',
            'status', 'marketing_emails_enabled', 'sms_notifications_enabled',
            'push_notifications_enabled'
        ];

        $this->assertEqualsCanonicalizing($fillable, $this->customer->getFillable());
    }

    #[Test]
    public function it_hides_sensitive_attributes()
    {
        $hidden = [
            'password', 'remember_token'
        ];

        $this->assertEqualsCanonicalizing($hidden, $this->customer->getHidden());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $casts = [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'preferences' => 'array',
            'last_login_at' => 'datetime',
            'total_spent' => 'decimal:2',
            'marketing_emails_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
        ];

        foreach ($casts as $attribute => $expectedCast) {
            $this->assertArrayHasKey($attribute, $this->customer->getCasts());
        }
    }

    #[Test]
    public function it_has_many_addresses()
    {
        $address1 = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
        $address2 = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $this->assertCount(2, $this->customer->addresses);
        $this->assertInstanceOf(CustomerAddress::class, $this->customer->addresses->first());
    }

    #[Test]
    public function it_has_many_orders()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $order1 = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'customer_address_id' => $address->id
        ]);

        $order2 = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'customer_address_id' => $address->id
        ]);

        $this->assertCount(2, $this->customer->orders);
        $this->assertInstanceOf(Order::class, $this->customer->orders->first());
    }

    #[Test]
    public function it_has_valid_gender_enum_values()
    {
        $validGenders = ['male', 'female', 'other'];

        foreach ($validGenders as $gender) {
            $customer = Customer::factory()->create(['gender' => $gender]);
            $this->assertEquals($gender, $customer->gender);
        }
    }

    #[Test]
    public function it_has_valid_status_enum_values()
    {
        $validStatuses = ['active', 'inactive', 'suspended'];

        foreach ($validStatuses as $status) {
            $customer = Customer::factory()->create(['status' => $status]);
            $this->assertEquals($status, $customer->status);
        }
    }

    #[Test]
    public function it_has_role_methods_for_authorization()
    {
        $this->assertTrue($this->customer->hasRole('CUSTOMER'));
        $this->assertFalse($this->customer->hasRole('ADMIN'));
        
        $this->assertTrue($this->customer->hasPermission('order:create'));
        $this->assertTrue($this->customer->hasPermission('order:view-own'));
        $this->assertTrue($this->customer->hasPermission('order:cancel-own'));
        $this->assertFalse($this->customer->hasPermission('order:delete'));
        
        $this->assertFalse($this->customer->isSuperAdmin());
    }

    #[Test]
    public function it_validates_email_uniqueness()
    {
        $existingEmail = 'test@example.com';
        Customer::factory()->create(['email' => $existingEmail]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Customer::create([
            'name' => 'Test Customer',
            'email' => $existingEmail,
            'password' => Hash::make('password'),
        ]);
    }

    #[Test]
    public function it_handles_email_verification()
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);

        $this->assertNull($customer->email_verified_at);

        $customer->email_verified_at = now();
        $customer->save();

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    #[Test]
    public function it_scopes_active_customers()
    {
        Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'status' => 'inactive'
        ]);
        
        Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'suspended@example.com',
            'password' => Hash::make('password'),
            'status' => 'suspended'
        ]);

        $activeCustomers = Customer::whereStatus('active')->get();
        $this->assertGreaterThan(0, $activeCustomers->count());
        $this->assertEquals('active', $activeCustomers->first()->status);
    }

    #[Test]
    public function it_scopes_verified_customers()
    {
        Customer::factory()->create(['email_verified_at' => null]);

        $verifiedCustomers = Customer::whereNotNull('email_verified_at')->get();
        $this->assertGreaterThan(0, $verifiedCustomers->count());
        $this->assertNotNull($verifiedCustomers->first()->email_verified_at);
    }

    #[Test]
    public function it_handles_preferences_storage()
    {
        $preferences = [
            'dietary_restrictions' => ['vegetarian'],
            'allergies' => ['nuts'],
            'delivery_preferences' => ['contactless']
        ];

        $customer = Customer::factory()->create(['preferences' => $preferences]);

        $this->assertEquals($preferences, $customer->preferences);
        $this->assertArrayHasKey('dietary_restrictions', $customer->preferences);
    }

    #[Test]
    public function it_updates_last_login()
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'login@example.com',
            'password' => Hash::make('password')
        ]);
        
        $this->assertNull($customer->last_login_at);

        $customer->last_login_at = now();
        $customer->save();

        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Customer::create([
            // Missing required fields
        ]);
    }

    #[Test]
    public function it_has_default_address_relationship()
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default' => true
        ]);

        $this->assertInstanceOf(CustomerAddress::class, $this->customer->defaultAddress);
        $this->assertEquals($address->id, $this->customer->defaultAddress->id);
    }

    #[Test]
    public function it_can_have_multiple_addresses()
    {
        CustomerAddress::factory()->count(3)->create(['customer_id' => $this->customer->id]);

        $this->assertCount(3, $this->customer->addresses);
    }
} 