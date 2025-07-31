<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BranchMenuItem;
use App\Models\RestaurantBranch;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchMenuItemTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_expected_fillable_attributes()
    {
        $branchItem = new BranchMenuItem();
        $this->assertEquals([
            'restaurant_branch_id', 'menu_item_id', 'price', 'is_available', 'is_featured',
            'sort_order', 'settings',
        ], $branchItem->getFillable());
    }

    /** @test */
    public function it_casts_attributes_properly()
    {
        $branchItem = BranchMenuItem::factory()->create([
            'price' => 15.99,
            'is_available' => true,
        ]);
        $this->assertIsFloat((float)$branchItem->price);
        $this->assertIsBool($branchItem->is_available);
        $this->assertNotNull($branchItem->created_at);
        $this->assertNotNull($branchItem->updated_at);
    }

    /** @test */
    public function it_belongs_to_a_branch()
    {
        $branch = RestaurantBranch::factory()->create();
        $branchItem = BranchMenuItem::factory()->create(['restaurant_branch_id' => $branch->id]);
        $this->assertInstanceOf(RestaurantBranch::class, $branchItem->branch);
        $this->assertEquals($branch->id, $branchItem->branch->id);
    }

    /** @test */
    public function it_belongs_to_a_restaurant_branch_alias()
    {
        $branch = RestaurantBranch::factory()->create();
        $branchItem = BranchMenuItem::factory()->create(['restaurant_branch_id' => $branch->id]);
        $this->assertInstanceOf(RestaurantBranch::class, $branchItem->restaurantBranch);
        $this->assertEquals($branch->id, $branchItem->restaurantBranch->id);
    }

    /** @test */
    public function it_belongs_to_a_menu_item()
    {
        $menuItem = MenuItem::factory()->create();
        $branchItem = BranchMenuItem::factory()->create(['menu_item_id' => $menuItem->id]);
        $this->assertInstanceOf(MenuItem::class, $branchItem->menuItem);
        $this->assertEquals($menuItem->id, $branchItem->menuItem->id);
    }
} 