<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            // Create main categories for each restaurant
            $category1 = MenuCategory::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Main Courses',
                'slug' => Str::slug($restaurant->name . ' main courses'),
                'description' => 'Delicious main dishes',
                'is_active' => true,
                'sort_order' => 10,
            ]);

            $category2 = MenuCategory::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Appetizers',
                'slug' => Str::slug($restaurant->name . ' appetizers'),
                'description' => 'Tasty starters',
                'is_active' => true,
                'sort_order' => 20,
            ]);

            $category3 = MenuCategory::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Drinks',
                'slug' => Str::slug($restaurant->name . ' drinks'),
                'description' => 'Refreshing beverages',
                'is_active' => true,
                'sort_order' => 30,
            ]);

            // Create menu items for each restaurant and category
            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'menu_category_id' => $category1->id,
                'name' => 'Chicken Fillet',
                'slug' => Str::slug($restaurant->name . ' chicken fillet'),
                'description' => 'Crispy fried chicken fillet served with fries and a drink.',
                'price' => 25.00,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 15,
                'allergens' => ['gluten', 'dairy'],
                'dietary_tags' => ['non-vegetarian'],
            ]);

            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'menu_category_id' => $category1->id,
                'name' => 'Beef Burger',
                'slug' => Str::slug($restaurant->name . ' beef burger'),
                'description' => 'Juicy beef patty with cheese, lettuce, and special sauce.',
                'price' => 30.00,
                'is_available' => true,
                'preparation_time' => 20,
                'allergens' => ['gluten', 'dairy'],
                'dietary_tags' => ['non-vegetarian'],
            ]);

            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'menu_category_id' => $category2->id,
                'name' => 'French Fries',
                'slug' => Str::slug($restaurant->name . ' french fries'),
                'description' => 'Crispy golden french fries.',
                'price' => 8.00,
                'is_available' => true,
                'preparation_time' => 10,
                'dietary_tags' => ['vegetarian', 'vegan', 'gluten-free'],
            ]);

            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'menu_category_id' => $category3->id,
                'name' => 'Coca-Cola',
                'slug' => Str::slug($restaurant->name . ' coca-cola'),
                'description' => 'Refreshing soft drink.',
                'price' => 5.00,
                'is_available' => true,
                'preparation_time' => 2,
            ]);

            $this->command->info('Seeded menu for restaurant: ' . $restaurant->name);
        }

        $this->command->info('Successfully seeded Menu data.');
    }
}
