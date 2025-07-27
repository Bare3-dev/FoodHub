<?php

namespace Tests\Feature\Api;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\BranchMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_lists_restaurants_publicly()
    {
        Restaurant::factory()->count(3)->create();

        $response = $this->getJson('/api/restaurants');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id', 'name', 'description', 'cuisine_type', 
                             'phone', 'email', 'website'
                         ]
                     ]
                 ])
                 ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_shows_restaurant_details_publicly()
    {
        $restaurant = Restaurant::factory()->create();

        $response = $this->getJson("/api/restaurants/{$restaurant->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id', 'name', 'description', 'cuisine_type',
                         'phone', 'email', 'website', 'branches'
                     ]
                 ])
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $restaurant->id,
                         'name' => $restaurant->name
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_restaurant()
    {
        $response = $this->getJson('/api/restaurants/999999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_tests_public_route()
    {
        $response = $this->getJson('/api/test-public');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Public route works']);
    }

    /** @test */
    public function it_tests_controller_route()
    {
        $response = $this->getJson('/api/test-controller');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'message', 'data']);
    }

    /** @test */
    public function it_tests_menu_category_controller_route()
    {
        $response = $this->getJson('/api/test-menu-category');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'message', 'data']);
    }

    /** @test */
    public function it_lists_restaurant_branches_publicly()
    {
        $restaurant = Restaurant::factory()->create();
        RestaurantBranch::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);

        $response = $this->getJson('/api/restaurant-branches');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id', 'name', 'address', 'phone', 'latitude', 'longitude',
                             'opening_hours', 'restaurant'
                         ]
                     ]
                 ])
                 ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_filters_restaurant_branches_by_location()
    {
        $restaurant = Restaurant::factory()->create();
        
        // Create a branch in New York
        RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060, // New York
            'address' => 'New York Branch'
        ]);

        // Create a branch in Los Angeles (outside the search radius)
        RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'latitude' => 34.0522,
            'longitude' => -118.2437, // Los Angeles
            'address' => 'Los Angeles Branch'
        ]);

        // Search near New York
        $response = $this->getJson('/api/restaurant-branches?latitude=40.7128&longitude=-74.0060&radius=10');

        $response->assertStatus(200);
        $branches = $response->json('data');
        
        $this->assertCount(1, $branches);
        $this->assertStringContainsString('New York', $branches[0]['address']);
    }

    /** @test */
    public function it_shows_restaurant_branch_details()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->getJson("/api/restaurant-branches/{$branch->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id', 'name', 'address', 'phone', 'latitude', 'longitude',
                         'opening_hours', 'restaurant', 'menu_items'
                     ]
                 ]);
    }

    /** @test */
    public function it_lists_menu_categories_publicly()
    {
        $restaurant = Restaurant::factory()->create();
        MenuCategory::factory()->count(3)->create(['restaurant_id' => $restaurant->id]);

        $response = $this->getJson('/api/menu-categories');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id', 'name', 'description', 'display_order', 'restaurant'
                         ]
                     ]
                 ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_filters_menu_categories_by_restaurant()
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        
        MenuCategory::factory()->count(2)->create(['restaurant_id' => $restaurant1->id]);
        MenuCategory::factory()->count(3)->create(['restaurant_id' => $restaurant2->id]);

        $response = $this->getJson("/api/restaurants/{$restaurant1->id}/menu-categories");

        $response->assertStatus(200);
        $categories = $response->json('data');
        
        $this->assertCount(2, $categories);
        foreach ($categories as $category) {
            $this->assertEquals($restaurant1->id, $category['restaurant']['id']);
        }
    }

    /** @test */
    public function it_shows_menu_category_details()
    {
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->getJson("/api/menu-categories/{$category->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id', 'name', 'description', 'display_order',
                         'restaurant', 'menu_items'
                     ]
                 ]);
    }

    /** @test */
    public function it_lists_menu_items_publicly()
    {
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        MenuItem::factory()->count(5)->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id
        ]);

        $response = $this->getJson('/api/menu-items');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id', 'name', 'description', 'price', 'category', 'restaurant'
                         ]
                     ]
                 ]);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function it_filters_menu_items_by_category()
    {
        $restaurant = Restaurant::factory()->create();
        $category1 = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $category2 = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        
        MenuItem::factory()->count(3)->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category1->id
        ]);
        MenuItem::factory()->count(2)->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category2->id
        ]);

        $response = $this->getJson("/api/menu-items?category_id={$category1->id}");

        $response->assertStatus(200);
        $items = $response->json('data');
        
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertEquals($category1->id, $item['category']['id']);
        }
    }

    /** @test */
    public function it_searches_menu_items_by_name()
    {
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        
        MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Margherita Pizza'
        ]);
        MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Pepperoni Pizza'
        ]);
        MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Caesar Salad'
        ]);

        $response = $this->getJson('/api/menu-items?search=pizza');

        $response->assertStatus(200);
        $items = $response->json('data');
        
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertStringContainsStringIgnoringCase('pizza', $item['name']);
        }
    }

    /** @test */
    public function it_shows_menu_item_details()
    {
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id
        ]);

        $response = $this->getJson("/api/menu-items/{$menuItem->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id', 'name', 'description', 'price', 'ingredients',
                         'allergens', 'nutritional_info', 'category', 'restaurant'
                     ]
                 ]);
    }

    /** @test */
    public function it_lists_branch_menu_items()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id
        ]);
        
        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem->id,
            'is_available' => true
        ]);

        $response = $this->getJson("/api/restaurant-branches/{$branch->id}/branch-menu-items");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id', 'price_override', 'is_available', 'stock_quantity',
                             'menu_item', 'branch'
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function it_only_shows_available_branch_menu_items()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem1 = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id
        ]);
        $menuItem2 = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id
        ]);
        
        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem1->id,
            'is_available' => true
        ]);
        BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $branch->id,
            'menu_item_id' => $menuItem2->id,
            'is_available' => false
        ]);

        $response = $this->getJson("/api/restaurant-branches/{$branch->id}/branch-menu-items?available_only=true");

        $response->assertStatus(200);
        $items = $response->json('data');
        
        $this->assertCount(1, $items);
        $this->assertTrue($items[0]['is_available']);
    }

    /** @test */
    public function it_paginates_large_result_sets()
    {
        Restaurant::factory()->count(50)->create();

        $response = $this->getJson('/api/restaurants?per_page=10');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data',
                     'meta' => [
                         'current_page', 'last_page', 'per_page', 'total'
                     ]
                 ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(5, $response->json('meta.last_page'));
    }

    /** @test */
    public function it_handles_invalid_pagination_parameters()
    {
        Restaurant::factory()->count(5)->create();

        // Test negative page
        $response = $this->getJson('/api/restaurants?page=-1');
        $response->assertStatus(422);

        // Test invalid per_page
        $response = $this->getJson('/api/restaurants?per_page=0');
        $response->assertStatus(422);

        // Test excessive per_page
        $response = $this->getJson('/api/restaurants?per_page=1000');
        $response->assertStatus(422);
    }

    /** @test */
    public function it_includes_cors_headers_for_public_endpoints()
    {
        Restaurant::factory()->create();

        $response = $this->getJson('/api/restaurants', [
            'Origin' => 'https://example.com'
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', '*')
                 ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                 ->assertHeader('Access-Control-Allow-Headers');
    }

    /** @test */
    public function it_includes_cache_headers_for_public_endpoints()
    {
        $restaurant = Restaurant::factory()->create();

        $response = $this->getJson("/api/restaurants/{$restaurant->id}");

        $response->assertHeader('Cache-Control', 'public, max-age=300') // 5 minutes
                 ->assertHeader('ETag');
    }

    /** @test */
    public function it_handles_malformed_search_parameters()
    {
        $response = $this->getJson('/api/menu-items?search=' . str_repeat('a', 1000));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['search']);
    }

    /** @test */
    public function it_validates_location_parameters()
    {
        // Invalid latitude
        $response = $this->getJson('/api/restaurant-branches?latitude=200&longitude=-74.0060');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude']);

        // Invalid longitude
        $response = $this->getJson('/api/restaurant-branches?latitude=40.7128&longitude=200');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['longitude']);

        // Invalid radius
        $response = $this->getJson('/api/restaurant-branches?latitude=40.7128&longitude=-74.0060&radius=-5');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['radius']);
    }
} 