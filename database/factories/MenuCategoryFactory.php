<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Appetizers', 'Salads', 'Soups', 'Main Courses', 'Pasta', 'Pizza',
            'Seafood', 'Steaks', 'Chicken', 'Vegetarian', 'Desserts', 'Beverages',
            'Sandwiches', 'Burgers', 'Asian', 'Mexican', 'Italian', 'Breakfast'
        ];

        $name = $this->faker->unique()->randomElement($categories);
        
        return [
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => $this->faker->sentence(8),
            'sort_order' => $this->faker->numberBetween(1, 20),
            'is_active' => $this->faker->boolean(95),
            'image_url' => $this->faker->imageUrl(300, 200, 'food'),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
