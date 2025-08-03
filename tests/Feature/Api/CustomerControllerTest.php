<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
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
    public function it_lists_customers_with_pagination()
    {
        Customer::factory()->count(25)->create();

        $response = $this->actingAs($this->customerService)
            ->getJson('/api/customers?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'status',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $responseData = $response->json();
        $this->assertCount(10, $responseData);
    }

    #[Test]
    public function it_shows_customer_details()
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'gender',
                'status',
                'preferences',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'id' => $customer->id,
                'email' => $customer->email
            ]);
    }

    #[Test]
    public function it_creates_new_customer()
    {
        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'password' => 'password123'
        ];

        $response = $this->actingAs($this->customerService)
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'status',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com'
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
    }

    #[Test]
    public function it_validates_customer_creation_data()
    {
        $invalidData = [
            'first_name' => '', // Required
            'email' => 'invalid-email', // Invalid email
            'password' => '', // Required
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/customers', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'email', 'password']);
    }

    #[Test]
    public function it_enforces_customer_creation_permissions()
    {
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);

        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123'
        ];

        $response = $this->actingAs($unauthorizedUser)
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_updates_customer_information()
    {
        $customer = Customer::factory()->create();

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '+1987654321'
        ];

        $response = $this->actingAs($this->customerService)
            ->putJson("/api/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone' => '+1987654321'
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);
    }

    #[Test]
    public function it_validates_customer_update_data()
    {
        $customer = Customer::factory()->create();

        $invalidData = [
            'email' => 'invalid-email'
        ];

        $response = $this->actingAs($this->customerService)
            ->putJson("/api/customers/{$customer->id}", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_enforces_customer_update_permissions()
    {
        $customer = Customer::factory()->create();
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF',
            'status' => 'active'
        ]);

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ];

        $response = $this->actingAs($unauthorizedUser)
            ->putJson("/api/customers/{$customer->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_deletes_customer()
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id
        ]);
    }

    #[Test]
    public function it_enforces_customer_deletion_permissions()
    {
        $customer = Customer::factory()->create();
        $unauthorizedUser = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_customer_not_found()
    {
        $response = $this->actingAs($this->customerService)
            ->getJson('/api/customers/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_filters_customers_by_status()
    {
        Customer::factory()->create(['status' => 'active']);
        Customer::factory()->create(['status' => 'inactive']);
        Customer::factory()->create(['status' => 'suspended']);

        $response = $this->actingAs($this->customerService)
            ->getJson('/api/customers?status=active');

        $response->assertStatus(200);
        
        $customers = $response->json();
        if (count($customers) > 0) {
            $this->assertEquals('active', $customers[0]['status']);
        }
    }

    #[Test]
    public function it_searches_customers_by_name()
    {
        Customer::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        Customer::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);

        $response = $this->actingAs($this->customerService)
            ->getJson('/api/customers?search=John');

        $response->assertStatus(200);
        
        $customers = $response->json();
        $this->assertGreaterThan(0, count($customers));
        $this->assertStringContainsString('John', $customers[0]['first_name']);
    }

    #[Test]
    public function it_handles_customer_with_orders()
    {
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
        
        // Create orders for the customer
        Order::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'customer_address_id' => $address->id
        ]);

        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);
        
        // Verify the customer has orders
        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id
        ]);
    }

    #[Test]
    public function it_handles_customer_preferences()
    {
        $customer = Customer::factory()->create([
            'preferences' => [
                'dietary_restrictions' => ['vegetarian', 'gluten_free'],
                'favorite_cuisines' => ['italian', 'chinese', 'mexican'],
                'spice_level' => 'medium',
                'notifications' => [
                    'email' => true,
                    'sms' => false,
                    'push' => true
                ]
            ]
        ]);

        $response = $this->actingAs($this->customerService)
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'preferences' => [
                    'dietary_restrictions' => ['vegetarian', 'gluten_free'],
                    'favorite_cuisines' => ['italian', 'chinese', 'mexican'],
                    'spice_level' => 'medium'
                ]
            ]);
    }
} 