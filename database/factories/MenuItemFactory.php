<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\MenuCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $foodItems = [
            'Margherita Pizza', 'Chicken Caesar Salad', 'Beef Burger', 'Spaghetti Carbonara',
            'Grilled Salmon', 'Chicken Tikka Masala', 'Pad Thai', 'Fish Tacos',
            'BBQ Ribs', 'Vegetable Stir Fry', 'Mushroom Risotto', 'Buffalo Wings',
            'Greek Salad', 'Clam Chowder', 'Beef Stroganoff', 'Chicken Quesadilla'
        ];

        return [
            'category_id' => MenuCategory::factory(),
            'name' => $this->faker->randomElement($foodItems),
            'description' => $this->faker->paragraph(2),
            'price' => $this->faker->randomFloat(2, 8.99, 28.99),
            'prep_time' => $this->faker->numberBetween(10, 45),
            'calories' => $this->faker->numberBetween(250, 800),
            'ingredients' => json_encode($this->faker->randomElements([
                'chicken', 'beef', 'pork', 'fish', 'cheese', 'tomatoes', 'onions',
                'garlic', 'herbs', 'spices', 'pasta', 'rice', 'vegetables', 'sauce'
            ], $this->faker->numberBetween(3, 8))),
            'allergens' => json_encode($this->faker->randomElements([
                'gluten', 'dairy', 'nuts', 'soy', 'eggs', 'shellfish'
            ], $this->faker->numberBetween(0, 3))),
            'dietary_tags' => json_encode($this->faker->randomElements([
                'vegetarian', 'vegan', 'gluten_free', 'dairy_free', 'low_carb', 'keto'
            ], $this->faker->numberBetween(0, 2))),
            'image_url' => $this->faker->imageUrl(400, 300, 'food'),
            'is_available' => $this->faker->boolean(90),
            'is_featured' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'nutritional_info' => json_encode([
                'protein' => $this->faker->numberBetween(10, 40) . 'g',
                'carbs' => $this->faker->numberBetween(20, 60) . 'g',
                'fat' => $this->faker->numberBetween(5, 25) . 'g',
                'fiber' => $this->faker->numberBetween(2, 15) . 'g',
                'sugar' => $this->faker->numberBetween(1, 20) . 'g'
            ]),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
