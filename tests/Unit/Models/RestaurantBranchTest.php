<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RestaurantBranch;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\BranchMenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantBranchTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_expected_fillable_attributes()
    {
        $branch = new RestaurantBranch();
        $this->assertEquals([
            'restaurant_id', 'name', 'slug', 'address', 'city', 'state', 'postal_code', 'country',
            'latitude', 'longitude', 'phone', 'manager_name', 'manager_phone', 'operating_hours',
            'delivery_zones', 'delivery_fee', 'minimum_order_amount', 'estimated_delivery_time',
            'status', 'accepts_online_orders', 'accepts_delivery', 'accepts_pickup', 'settings',
        ], $branch->getFillable());
    }

    /** @test */
    public function it_casts_attributes_properly()
    {
        $branch = RestaurantBranch::factory()->create([
            'operating_hours' => ['mon' => '9-5'],
            'delivery_zones' => ['zone1'],
            'settings' => ['foo' => 'bar'],
            'delivery_fee' => 12.34,
            'minimum_order_amount' => 50.00,
            'accepts_online_orders' => true,
            'accepts_delivery' => false,
            'accepts_pickup' => true,
        ]);
        $this->assertIsArray($branch->operating_hours);
        $this->assertIsArray($branch->delivery_zones);
        $this->assertIsArray($branch->settings);
        $this->assertIsFloat((float)$branch->delivery_fee);
        $this->assertIsFloat((float)$branch->minimum_order_amount);
        $this->assertIsBool($branch->accepts_online_orders);
        $this->assertIsBool($branch->accepts_delivery);
        $this->assertIsBool($branch->accepts_pickup);
        $this->assertNotNull($branch->created_at);
        $this->assertNotNull($branch->updated_at);
    }

    /** @test */
    public function it_belongs_to_a_restaurant()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $this->assertInstanceOf(Restaurant::class, $branch->restaurant);
        $this->assertEquals($restaurant->id, $branch->restaurant->id);
    }

    /** @test */
    public function it_has_many_users()
    {
        $branch = RestaurantBranch::factory()->create();
        $user = User::factory()->create(['restaurant_branch_id' => $branch->id]);
        $this->assertTrue($branch->users->contains($user));
    }

    /** @test */
    public function it_has_many_menu_items()
    {
        $branch = RestaurantBranch::factory()->create();
        $item = BranchMenuItem::factory()->create(['restaurant_branch_id' => $branch->id]);
        $this->assertTrue($branch->menuItems->contains($item));
    }

    /** @test */
    public function it_has_many_orders()
    {
        $branch = RestaurantBranch::factory()->create();
        $order = Order::factory()->create(['restaurant_branch_id' => $branch->id]);
        $this->assertTrue($branch->orders->contains($order));
    }

    /** @test */
    public function it_can_scope_active()
    {
        $active = RestaurantBranch::factory()->create(['status' => 'active']);
        $inactive = RestaurantBranch::factory()->create(['status' => 'inactive']);
        $scoped = RestaurantBranch::active()->get();
        $this->assertTrue($scoped->contains($active));
        $this->assertFalse($scoped->contains($inactive));
    }

    /** @test */
    public function it_can_scope_accepts_online_orders()
    {
        $accepts = RestaurantBranch::factory()->create(['accepts_online_orders' => true]);
        $rejects = RestaurantBranch::factory()->create(['accepts_online_orders' => false]);
        $scoped = RestaurantBranch::acceptsOnlineOrders()->get();
        $this->assertTrue($scoped->contains($accepts));
        $this->assertFalse($scoped->contains($rejects));
    }

    /** @test */
    public function it_can_scope_within_radius()
    {
        // Create a branch at a known location
        $branch = RestaurantBranch::factory()->create([
            'latitude' => 24.7136,
            'longitude' => 46.6753, // Riyadh
        ]);
        // Should be found within 10km of itself
        $scoped = RestaurantBranch::withinRadius(24.7136, 46.6753, 10)->get();
        $this->assertTrue($scoped->contains($branch));
    }
} 