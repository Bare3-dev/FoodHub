<?php

namespace Database\Factories;

use App\Models\InventoryStockChange;
use App\Models\BranchMenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryStockChange>
 */
class InventoryStockChangeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InventoryStockChange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $changeTypes = ['manual', 'pos_sync', 'order_consumption', 'restock'];
        $sources = ['pos_system', 'manual_entry', 'order_system', 'inventory_service'];
        
        $previousQuantity = $this->faker->numberBetween(0, 100);
        $quantityChange = $this->faker->numberBetween(-20, 30);
        $newQuantity = max(0, $previousQuantity + $quantityChange);

        return [
            'branch_menu_item_id' => BranchMenuItem::factory(),
            'user_id' => User::factory(),
            'change_type' => $this->faker->randomElement($changeTypes),
            'quantity_change' => $quantityChange,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $this->faker->optional()->sentence(),
            'metadata' => $this->faker->optional()->array(),
            'source' => $this->faker->randomElement($sources),
        ];
    }

    /**
     * Indicate that the stock change is an addition.
     */
    public function addition(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_change' => $this->faker->numberBetween(1, 50),
            'change_type' => 'restock',
        ]);
    }

    /**
     * Indicate that the stock change is a reduction.
     */
    public function reduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_change' => $this->faker->numberBetween(-50, -1),
            'change_type' => 'order_consumption',
        ]);
    }

    /**
     * Indicate that the stock change is from POS sync.
     */
    public function posSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'pos_sync',
            'source' => 'pos_system',
        ]);
    }

    /**
     * Indicate that the stock change is manual.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'manual',
            'source' => 'manual_entry',
        ]);
    }
}
