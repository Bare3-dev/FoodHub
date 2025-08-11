<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\MenuCategory;
use App\Models\BranchMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_expected_fillable_attributes()
    {
        $item = new MenuItem();
        $this->assertEquals([
            'restaurant_id', 'menu_category_id', 'name', 'slug', 'description', 'ingredients',
            'price', 'cost_price', 'currency', 'sku', 'images', 'preparation_time', 'calories',
            'nutritional_info', 'allergens', 'dietary_tags', 'is_available', 'is_featured',
            'is_spicy', 'spice_level', 'sort_order', 'customization_options', 'pos_data',
        ], $item->getFillable());
    }

    #[Test]
    public function it_casts_attributes_properly()
    {
        $item = MenuItem::factory()->create([
            'price' => 12.99,
            'cost_price' => 8.50,
            'images' => ['image1.jpg', 'image2.jpg'],
            'nutritional_info' => ['calories' => 500, 'protein' => 25],
            'allergens' => ['nuts', 'dairy'],
            'dietary_tags' => ['vegetarian', 'gluten-free'],
            'is_available' => true,
            'is_featured' => false,
            'is_spicy' => true,
            'customization_options' => ['size' => ['small', 'large']],
            'pos_data' => ['pos_id' => '12345'],
        ]);
        $this->assertIsFloat((float)$item->price);
        $this->assertIsFloat((float)$item->cost_price);
        $this->assertIsArray($item->images);
        $this->assertIsArray($item->nutritional_info);
        $this->assertIsArray($item->allergens);
        $this->assertIsArray($item->dietary_tags);
        $this->assertIsBool($item->is_available);
        $this->assertIsBool($item->is_featured);
        $this->assertIsBool($item->is_spicy);
        $this->assertIsArray($item->customization_options);
        $this->assertIsArray($item->pos_data);
        $this->assertNotNull($item->created_at);
        $this->assertNotNull($item->updated_at);
    }

    #[Test]
    public function it_belongs_to_a_restaurant()
    {
        $restaurant = Restaurant::factory()->create();
        $item = MenuItem::factory()->create(['restaurant_id' => $restaurant->id]);
        $this->assertInstanceOf(Restaurant::class, $item->restaurant);
        $this->assertEquals($restaurant->id, $item->restaurant->id);
    }

    #[Test]
    public function it_belongs_to_a_category()
    {
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $this->assertInstanceOf(MenuCategory::class, $item->category);
        $this->assertEquals($category->id, $item->category->id);
    }

    #[Test]
    public function it_has_many_branch_menu_items()
    {
        $item = MenuItem::factory()->create();
        $branchItem = BranchMenuItem::factory()->create(['menu_item_id' => $item->id]);
        $this->assertTrue($item->branchMenuItems->contains($branchItem));
    }

    #[Test]
    public function it_can_scope_available()
    {
        $available = MenuItem::factory()->create(['is_available' => true]);
        $unavailable = MenuItem::factory()->create(['is_available' => false]);
        $scoped = MenuItem::available()->get();
        $this->assertTrue($scoped->contains($available));
        $this->assertFalse($scoped->contains($unavailable));
    }
} 