<?php

namespace Database\Factories;

use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyProgram>
 */
class LoyaltyProgramFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LoyaltyProgram::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->paragraph(),
            'type' => 'points',
            'is_active' => true,
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'end_date' => $this->faker->dateTimeBetween('now', '+2 years'),
            'rules' => [
                'minimum_spend' => $this->faker->numberBetween(5, 50),
                'points_expiry_months' => $this->faker->numberBetween(6, 24),
                'minimum_points_redemption' => $this->faker->numberBetween(100, 1000),
                'redemption_options' => [
                    'discount' => true,
                    'free_delivery' => true,
                    'free_item' => true,
                    'cash_back' => false,
                ],
                'tier_progression' => [
                    'bronze' => ['min_points' => 0, 'max_points' => 999],
                    'silver' => ['min_points' => 1000, 'max_points' => 4999],
                    'gold' => ['min_points' => 5000, 'max_points' => 19999],
                    'platinum' => ['min_points' => 20000, 'max_points' => 49999],
                    'diamond' => ['min_points' => 50000, 'max_points' => null],
                ],
                'notification_settings' => [
                    'points_earned' => true,
                    'points_redeemed' => true,
                    'points_expiring' => true,
                    'tier_upgrade' => true,
                    'birthday_bonus' => true,
                ],
            ],
            'points_per_dollar' => $this->faker->randomFloat(2, 0.1, 10.0),
            'dollar_per_point' => $this->faker->randomFloat(2, 0.01, 0.1),
            'minimum_spend_for_points' => $this->faker->numberBetween(0, 20),
            'bonus_multipliers' => [
                'happy_hour' => 2.0,
                'birthday' => 3.0,
                'first_order' => 5.0,
                'referral' => 10.0,
            ],
        ];
    }

    /**
     * Indicate that the loyalty program is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the loyalty program has high points per dollar.
     */
    public function highPointsRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_per_dollar' => $this->faker->randomFloat(2, 5.0, 10.0),
        ]);
    }

    /**
     * Indicate that the loyalty program has low points per dollar.
     */
    public function lowPointsRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_per_dollar' => $this->faker->randomFloat(2, 0.1, 2.0),
        ]);
    }

    /**
     * Indicate that the loyalty program has short expiry period.
     */
    public function shortExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_merge($attributes['rules'] ?? [], [
                'points_expiry_months' => $this->faker->numberBetween(3, 6),
            ]),
        ]);
    }

    /**
     * Indicate that the loyalty program has long expiry period.
     */
    public function longExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_merge($attributes['rules'] ?? [], [
                'points_expiry_months' => $this->faker->numberBetween(24, 60),
            ]),
        ]);
    }

    /**
     * Indicate that the loyalty program has high minimum redemption.
     */
    public function highMinimumRedemption(): static
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_merge($attributes['rules'] ?? [], [
                'minimum_points_redemption' => $this->faker->numberBetween(1000, 5000),
            ]),
        ]);
    }

    /**
     * Indicate that the loyalty program has low minimum redemption.
     */
    public function lowMinimumRedemption(): static
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_merge($attributes['rules'] ?? [], [
                'minimum_points_redemption' => $this->faker->numberBetween(50, 200),
            ]),
        ]);
    }

    /**
     * Indicate that the loyalty program has all redemption options enabled.
     */
    public function allRedemptionOptions(): static
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_merge($attributes['rules'] ?? [], [
                'redemption_options' => [
                    'discount' => true,
                    'free_delivery' => true,
                    'free_item' => true,
                    'cash_back' => true,
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the loyalty program has limited redemption options.
     */
    public function limitedRedemptionOptions(): static
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_merge($attributes['rules'] ?? [], [
                'redemption_options' => [
                    'discount' => true,
                    'free_delivery' => false,
                    'free_item' => false,
                    'cash_back' => false,
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the loyalty program has high bonus multipliers.
     */
    public function highBonusMultipliers(): static
    {
        return $this->state(fn (array $attributes) => [
            'bonus_multipliers' => [
                'happy_hour' => 3.0,
                'birthday' => 5.0,
                'first_order' => 10.0,
                'referral' => 20.0,
            ],
        ]);
    }

    /**
     * Indicate that the loyalty program has low bonus multipliers.
     */
    public function lowBonusMultipliers(): static
    {
        return $this->state(fn (array $attributes) => [
            'bonus_multipliers' => [
                'happy_hour' => 1.5,
                'birthday' => 2.0,
                'first_order' => 3.0,
                'referral' => 5.0,
            ],
        ]);
    }

    /**
     * Indicate that the loyalty program is expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => $this->faker->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Indicate that the loyalty program is newly launched.
     */
    public function newlyLaunched(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'end_date' => $this->faker->dateTimeBetween('+1 year', '+3 years'),
        ]);
    }
} 