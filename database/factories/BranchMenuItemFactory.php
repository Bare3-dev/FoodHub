<?php

namespace Database\Factories;

use App\Models\BranchMenuItem;
use App\Models\RestaurantBranch;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BranchMenuItem>
 */
class BranchMenuItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BranchMenuItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_branch_id' => RestaurantBranch::factory(),
            'menu_item_id' => MenuItem::factory(),
            'price' => $this->faker->randomFloat(2, 5, 50),
            'is_available' => $this->faker->boolean(80), // 80% chance of being available
            'is_featured' => $this->faker->boolean(20), // 20% chance of being featured
            'sort_order' => $this->faker->numberBetween(1, 100),
            'settings' => [
                'preparation_time' => $this->faker->numberBetween(5, 30),
                'allergens' => $this->faker->randomElements(['nuts', 'dairy', 'gluten', 'soy'], $this->faker->numberBetween(0, 2)),
                'dietary_info' => $this->faker->randomElements(['vegetarian', 'vegan', 'halal', 'kosher'], $this->faker->numberBetween(0, 2)),
            ],
        ];
    }

    /**
     * Indicate that the menu item is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
        ]);
    }

    /**
     * Indicate that the menu item is not available.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Indicate that the menu item is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Set a specific price range.
     */
    public function priceRange(float $min, float $max): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $this->faker->randomFloat(2, $min, $max),
        ]);
    }
} 