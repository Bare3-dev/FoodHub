<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 5, 50);
        $totalPrice = $quantity * $unitPrice;

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'item_name' => $this->faker->words(3, true),
            'item_description' => $this->faker->optional()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'special_instructions' => $this->faker->optional()->sentence(),
            'customizations' => $this->faker->optional()->randomElements(['extra_cheese', 'no_onions', 'spicy_level'], 2) ?? [],
            'nutritional_snapshot' => [],
            'allergens_snapshot' => [],
            'sku' => $this->faker->optional()->bothify('SKU-####'),
        ];
    }

    /**
     * Indicate that the order item has special instructions.
     */
    public function withSpecialInstructions(): static
    {
        return $this->state(fn (array $attributes) => [
            'special_instructions' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the order item has customizations.
     */
    public function withCustomizations(): static
    {
        return $this->state(fn (array $attributes) => [
            'customizations' => [
                'extra_cheese' => true,
                'no_onions' => true,
                'spicy_level' => 'medium'
            ],
        ]);
    }
} 