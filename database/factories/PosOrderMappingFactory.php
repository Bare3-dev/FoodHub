<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PosOrderMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosOrderMapping>
 */
class PosOrderMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $posTypes = ['square', 'toast', 'local'];
        $syncStatuses = ['synced', 'failed', 'pending'];
        
        return [
            'foodhub_order_id' => Order::factory(),
            'pos_order_id' => $this->faker->uuid(),
            'pos_type' => $this->faker->randomElement($posTypes),
            'sync_status' => $this->faker->randomElement($syncStatuses),
        ];
    }

    /**
     * Indicate that the order is synced.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'synced',
        ]);
    }

    /**
     * Indicate that the order sync failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'failed',
        ]);
    }

    /**
     * Indicate that the order sync is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'pending',
        ]);
    }

    /**
     * Create a Square POS order mapping.
     */
    public function square(): static
    {
        return $this->state(fn (array $attributes) => [
            'pos_type' => 'square',
        ]);
    }

    /**
     * Create a Toast POS order mapping.
     */
    public function toast(): static
    {
        return $this->state(fn (array $attributes) => [
            'pos_type' => 'toast',
        ]);
    }

    /**
     * Create a Local POS order mapping.
     */
    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'pos_type' => 'local',
        ]);
    }
} 