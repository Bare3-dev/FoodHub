<?php

namespace Database\Factories;

use App\Models\PosIntegration;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosIntegration>
 */
class PosIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $posTypes = ['square', 'toast', 'local'];
        $posType = $this->faker->randomElement($posTypes);
        
        $configuration = $this->getConfigurationForPosType($posType);

        return [
            'restaurant_id' => Restaurant::factory(),
            'pos_type' => $posType,
            'configuration' => $configuration,
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'last_sync_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the integration is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the integration is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a Square POS integration.
     */
    public function square(): static
    {
        return $this->state(fn (array $attributes) => [
            'pos_type' => 'square',
            'configuration' => $this->getConfigurationForPosType('square'),
        ]);
    }

    /**
     * Create a Toast POS integration.
     */
    public function toast(): static
    {
        return $this->state(fn (array $attributes) => [
            'pos_type' => 'toast',
            'configuration' => $this->getConfigurationForPosType('toast'),
        ]);
    }

    /**
     * Create a Local POS integration.
     */
    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'pos_type' => 'local',
            'configuration' => $this->getConfigurationForPosType('local'),
        ]);
    }

    /**
     * Get configuration for specific POS type.
     */
    private function getConfigurationForPosType(string $posType): array
    {
        switch ($posType) {
            case 'square':
                return [
                    'api_url' => 'https://api.square.com/v2',
                    'api_key' => $this->faker->uuid(),
                    'location_id' => $this->faker->uuid(),
                    'webhook_secret' => $this->faker->sha1(),
                ];
            
            case 'toast':
                return [
                    'api_url' => 'https://api.toasttab.com/v1',
                    'api_key' => $this->faker->uuid(),
                    'restaurant_id' => $this->faker->uuid(),
                    'webhook_secret' => $this->faker->sha1(),
                ];
            
            case 'local':
                return [
                    'api_url' => $this->faker->url(),
                    'api_key' => $this->faker->uuid(),
                    'pos_id' => $this->faker->uuid(),
                    'webhook_secret' => $this->faker->sha1(),
                ];
            
            default:
                return [];
        }
    }
} 