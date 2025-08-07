<?php

namespace Database\Factories;

use App\Models\PosIntegration;
use App\Models\PosSyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSyncLog>
 */
class PosSyncLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $syncTypes = ['order', 'menu', 'inventory'];
        $statuses = ['success', 'failed', 'pending'];
        
        return [
            'pos_integration_id' => PosIntegration::factory(),
            'sync_type' => $this->faker->randomElement($syncTypes),
            'status' => $this->faker->randomElement($statuses),
            'details' => $this->getDetailsForSyncType($this->faker->randomElement($syncTypes)),
            'synced_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the sync was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
        ]);
    }

    /**
     * Indicate that the sync failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Indicate that the sync is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Create an order sync log.
     */
    public function order(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => 'order',
            'details' => $this->getDetailsForSyncType('order'),
        ]);
    }

    /**
     * Create a menu sync log.
     */
    public function menu(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => 'menu',
            'details' => $this->getDetailsForSyncType('menu'),
        ]);
    }

    /**
     * Create an inventory sync log.
     */
    public function inventory(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => 'inventory',
            'details' => $this->getDetailsForSyncType('inventory'),
        ]);
    }

    /**
     * Get details for specific sync type.
     */
    private function getDetailsForSyncType(string $syncType): array
    {
        switch ($syncType) {
            case 'order':
                return [
                    'foodhub_order_id' => $this->faker->uuid(),
                    'pos_order_id' => $this->faker->uuid(),
                    'items_count' => $this->faker->numberBetween(1, 10),
                    'total_amount' => $this->faker->randomFloat(2, 10, 200),
                    'sync_duration_ms' => $this->faker->numberBetween(100, 2000),
                ];
            
            case 'menu':
                return [
                    'items_synced' => $this->faker->numberBetween(5, 50),
                    'categories_synced' => $this->faker->numberBetween(1, 10),
                    'prices_updated' => $this->faker->numberBetween(0, 20),
                    'sync_duration_ms' => $this->faker->numberBetween(500, 5000),
                ];
            
            case 'inventory':
                return [
                    'items_updated' => $this->faker->numberBetween(10, 100),
                    'out_of_stock_items' => $this->faker->numberBetween(0, 5),
                    'stock_levels_updated' => $this->faker->numberBetween(5, 30),
                    'sync_duration_ms' => $this->faker->numberBetween(300, 3000),
                ];
            
            default:
                return [];
        }
    }
} 