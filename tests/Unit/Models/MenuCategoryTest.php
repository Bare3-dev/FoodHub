<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuCategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_expected_fillable_attributes()
    {
        $category = new MenuCategory();
        $this->assertEquals([
            'restaurant_id', 'parent_category_id', 'name', 'slug', 'description', 'image_url',
            'sort_order', 'is_active', 'settings',
        ], $category->getFillable());
    }

    #[Test]
    public function it_casts_attributes_properly()
    {
        $category = MenuCategory::factory()->create([
            'is_active' => true,
            'settings' => ['foo' => 'bar'],
        ]);
        $this->assertIsBool($category->is_active);
        $this->assertIsArray($category->settings);
        $this->assertNotNull($category->created_at);
        $this->assertNotNull($category->updated_at);
    }

    #[Test]
    public function it_belongs_to_a_restaurant()
    {
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $this->assertInstanceOf(Restaurant::class, $category->restaurant);
        $this->assertEquals($restaurant->id, $category->restaurant->id);
    }

    #[Test]
    public function it_can_have_a_parent_category()
    {
        $parent = MenuCategory::factory()->create();
        $child = MenuCategory::factory()->create(['parent_category_id' => $parent->id]);
        $this->assertInstanceOf(MenuCategory::class, $child->parentCategory);
        $this->assertEquals($parent->id, $child->parentCategory->id);
    }

    #[Test]
    public function it_can_have_subcategories()
    {
        $parent = MenuCategory::factory()->create();
        $child = MenuCategory::factory()->create(['parent_category_id' => $parent->id]);
        $this->assertTrue($parent->subCategories->contains($child));
    }

    #[Test]
    public function it_has_many_menu_items()
    {
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $this->assertTrue($category->menuItems->contains($item));
    }

    #[Test]
    public function it_can_scope_active()
    {
        $active = MenuCategory::factory()->create(['is_active' => true]);
        $inactive = MenuCategory::factory()->create(['is_active' => false]);
        $scoped = MenuCategory::active()->get();
        $this->assertTrue($scoped->contains($active));
        $this->assertFalse($scoped->contains($inactive));
    }

    #[Test]
    public function it_generates_unique_slug_per_restaurant()
    {
        $restaurant = Restaurant::factory()->create();
        $cat1 = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers']);
        $cat2 = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers']);
        $this->assertNotEquals($cat1->slug, $cat2->slug);
        $this->assertStringStartsWith('burgers', $cat1->slug);
        $this->assertStringStartsWith('burgers', $cat2->slug);
    }
} 