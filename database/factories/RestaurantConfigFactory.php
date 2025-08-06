<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RestaurantConfig>
 */
final class RestaurantConfigFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = RestaurantConfig::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'config_key' => $this->faker->unique()->word(),
            'config_value' => $this->faker->sentence(),
            'is_encrypted' => false,
            'data_type' => 'string',
            'description' => $this->faker->sentence(),
            'is_sensitive' => false,
        ];
    }

    /**
     * Indicate that the config is encrypted.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
        ]);
    }

    /**
     * Indicate that the config is sensitive.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sensitive' => true,
            'is_encrypted' => true,
        ]);
    }

    /**
     * Create a loyalty points per currency config.
     */
    public function loyaltyPointsPerCurrency(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_key' => 'loyalty_points_per_currency',
            'config_value' => (string) $this->faker->numberBetween(1, 10),
            'data_type' => 'integer',
            'description' => 'Points earned per currency unit spent',
        ]);
    }

    /**
     * Create a loyalty currency per point config.
     */
    public function loyaltyCurrencyPerPoint(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_key' => 'loyalty_currency_per_point',
            'config_value' => (string) $this->faker->randomFloat(3, 0.001, 0.1),
            'data_type' => 'float',
            'description' => 'Currency value per loyalty point',
        ]);
    }

    /**
     * Create a loyalty tier thresholds config.
     */
    public function loyaltyTierThresholds(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_key' => 'loyalty_tier_thresholds',
            'config_value' => json_encode([
                'bronze' => 0,
                'silver' => 100,
                'gold' => 500,
                'platinum' => 1000,
            ]),
            'data_type' => 'array',
            'description' => 'Points required for each loyalty tier',
        ]);
    }

    /**
     * Create a spin wheel probabilities config.
     */
    public function spinWheelProbabilities(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_key' => 'loyalty_spin_wheel_probabilities',
            'config_value' => json_encode([
                'points_10' => 0.4,
                'points_25' => 0.3,
                'points_50' => 0.2,
                'points_100' => 0.1,
            ]),
            'data_type' => 'array',
            'description' => 'Probability distribution for spin wheel prizes',
        ]);
    }

    /**
     * Create a stamp card requirements config.
     */
    public function stampCardRequirements(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_key' => 'loyalty_stamp_card_requirements',
            'config_value' => json_encode([
                'stamps_needed' => 10,
                'reward_value' => 5.00,
            ]),
            'data_type' => 'array',
            'description' => 'Requirements for stamp card completion',
        ]);
    }

    /**
     * Create an operating hours config.
     */
    public function operatingHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_key' => 'operating_hours',
            'config_value' => json_encode([
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '21:00'],
            ]),
            'data_type' => 'array',
            'description' => 'Restaurant operating hours',
        ]);
    }
} 