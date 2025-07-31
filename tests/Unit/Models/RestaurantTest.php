<?php

namespace Tests\Unit\Models;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'name', 'slug', 'description', 'cuisine_type', 'phone', 'email',
            'website', 'logo_url', 'cover_image_url', 'business_hours',
            'settings', 'status', 'commission_rate', 'is_featured', 'verified_at'
        ];

        $this->assertEqualsCanonicalizing($fillable, $this->restaurant->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $casts = [
            'business_hours' => 'array',
            'settings' => 'array',
            'commission_rate' => 'decimal:2',
            'is_featured' => 'boolean',
            'verified_at' => 'datetime',
        ];

        foreach ($casts as $attribute => $expectedCast) {
            $this->assertArrayHasKey($attribute, $this->restaurant->getCasts());
        }
    }

    #[Test]
    public function it_has_many_branches()
    {
        $branch1 = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $branch2 = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->assertCount(2, $this->restaurant->branches);
        $this->assertInstanceOf(RestaurantBranch::class, $this->restaurant->branches->first());
    }

    #[Test]
    public function it_has_many_staff()
    {
        $owner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $manager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'restaurant_id' => $this->restaurant->id
        ]);

        $this->assertCount(2, $this->restaurant->users);
        $this->assertInstanceOf(User::class, $this->restaurant->users->first());
    }

    #[Test]
    public function it_has_many_menu_items()
    {
        $menuItem1 = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $menuItem2 = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->assertCount(2, $this->restaurant->menuItems);
        $this->assertInstanceOf(MenuItem::class, $this->restaurant->menuItems->first());
    }

    #[Test]
    public function it_has_many_menu_categories()
    {
        $category1 = MenuCategory::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $category2 = MenuCategory::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->assertCount(2, $this->restaurant->menuCategories);
        $this->assertInstanceOf(MenuCategory::class, $this->restaurant->menuCategories->first());
    }

    #[Test]
    public function it_has_many_orders()
    {
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        $order1 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id
        ]);

        $order2 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id
        ]);

        $this->assertCount(2, $this->restaurant->orders);
        $this->assertInstanceOf(Order::class, $this->restaurant->orders->first());
    }

    #[Test]
    public function it_has_valid_status_enum_values()
    {
        $validStatuses = ['active', 'inactive', 'suspended'];

        foreach ($validStatuses as $status) {
            $restaurant = Restaurant::factory()->create(['status' => $status]);
            $this->assertEquals($status, $restaurant->status);
        }
    }

    #[Test]
    public function it_has_valid_cuisine_types()
    {
        $validCuisineTypes = [
            'italian', 'chinese', 'indian', 'mexican', 'japanese',
            'thai', 'american', 'mediterranean', 'french', 'greek'
        ];

        foreach ($validCuisineTypes as $cuisineType) {
            $restaurant = Restaurant::factory()->create(['cuisine_type' => $cuisineType]);
            $this->assertEquals($cuisineType, $restaurant->cuisine_type);
        }
    }

    #[Test]
    public function it_calculates_average_rating()
    {
        $restaurant = Restaurant::factory()->create([
            'is_featured' => true
        ]);

        $this->assertTrue($restaurant->is_featured);
    }

    #[Test]
    public function it_handles_operating_hours()
    {
        $businessHours = [
            'monday' => ['open' => '09:00', 'close' => '22:00'],
            'tuesday' => ['open' => '09:00', 'close' => '22:00'],
            'wednesday' => ['open' => '09:00', 'close' => '22:00'],
            'thursday' => ['open' => '09:00', 'close' => '22:00'],
            'friday' => ['open' => '09:00', 'close' => '23:00'],
            'saturday' => ['open' => '10:00', 'close' => '23:00'],
            'sunday' => ['open' => '10:00', 'close' => '21:00']
        ];

        $restaurant = Restaurant::factory()->create(['business_hours' => $businessHours]);

        $this->assertEquals($businessHours, $restaurant->business_hours);
        $this->assertArrayHasKey('monday', $restaurant->business_hours);
    }

    #[Test]
    public function it_handles_payment_methods()
    {
        $settings = [
            'accepts_cash' => true,
            'accepts_card' => true,
            'max_delivery_distance' => 10,
            'auto_accept_orders' => false
        ];

        $restaurant = Restaurant::factory()->create(['settings' => $settings]);

        $this->assertEquals($settings, $restaurant->settings);
        $this->assertArrayHasKey('accepts_cash', $restaurant->settings);
    }

    #[Test]
    public function it_scopes_active_restaurants()
    {
        // Create restaurants with different statuses
        Restaurant::create([
            'name' => 'Inactive Restaurant',
            'slug' => 'inactive-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'italian',
            'status' => 'inactive',
            'business_hours' => [],
            'settings' => []
        ]);
        
        Restaurant::create([
            'name' => 'Suspended Restaurant',
            'slug' => 'suspended-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'italian',
            'status' => 'suspended',
            'business_hours' => [],
            'settings' => []
        ]);

        // Create an active restaurant explicitly
        Restaurant::create([
            'name' => 'Active Restaurant',
            'slug' => 'active-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'italian',
            'status' => 'active',
            'business_hours' => [],
            'settings' => []
        ]);

        $activeRestaurants = Restaurant::whereStatus('active')->get();
        $this->assertGreaterThan(0, $activeRestaurants->count());
        $this->assertEquals('active', $activeRestaurants->first()->status);
    }

    #[Test]
    public function it_scopes_featured_restaurants()
    {
        // Create a non-featured restaurant
        Restaurant::create([
            'name' => 'Non Featured Restaurant',
            'slug' => 'non-featured-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'italian',
            'is_featured' => false,
            'business_hours' => [],
            'settings' => []
        ]);

        // Create a featured restaurant explicitly
        Restaurant::create([
            'name' => 'Featured Restaurant',
            'slug' => 'featured-restaurant',
            'description' => 'Test restaurant',
            'cuisine_type' => 'italian',
            'is_featured' => true,
            'business_hours' => [],
            'settings' => []
        ]);

        $featuredRestaurants = Restaurant::whereIsFeatured(true)->get();
        $this->assertGreaterThan(0, $featuredRestaurants->count());
        $this->assertTrue($featuredRestaurants->first()->is_featured);
    }

    #[Test]
    public function it_scopes_restaurants_by_cuisine()
    {
        Restaurant::factory()->create(['cuisine_type' => 'italian']);
        Restaurant::factory()->create(['cuisine_type' => 'chinese']);

        $italianRestaurants = Restaurant::whereCuisineType('italian')->get();
        $this->assertCount(1, $italianRestaurants);
        $this->assertEquals('italian', $italianRestaurants->first()->cuisine_type);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Restaurant::create([
            // Missing required fields
        ]);
    }

    #[Test]
    public function it_has_owner_relationship()
    {
        $owner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $this->restaurant->id
        ]);

        $this->assertInstanceOf(User::class, $this->restaurant->users->first());
        $this->assertEquals($owner->id, $this->restaurant->users->first()->id);
    }

    #[Test]
    public function it_can_have_multiple_branches()
    {
        RestaurantBranch::factory()->count(3)->create(['restaurant_id' => $this->restaurant->id]);

        $this->assertCount(3, $this->restaurant->branches);
    }
} 