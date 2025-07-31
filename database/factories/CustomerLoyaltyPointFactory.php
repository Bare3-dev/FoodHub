<?php

namespace Database\Factories;

use App\Models\CustomerLoyaltyPoint;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerLoyaltyPoint>
 */
class CustomerLoyaltyPointFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CustomerLoyaltyPoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $customer = Customer::factory()->create();
        $tier = LoyaltyTier::factory()->create(['loyalty_program_id' => $loyaltyProgram->id]);

        return [
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'loyalty_tier_id' => $tier->id,
            'current_points' => $this->faker->randomFloat(2, 0, 10000),
            'total_points_earned' => $this->faker->randomFloat(2, 0, 15000),
            'total_points_redeemed' => $this->faker->randomFloat(2, 0, 5000),
            'total_points_expired' => $this->faker->randomFloat(2, 0, 1000),
            'last_points_earned_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'last_points_redeemed_date' => $this->faker->optional()->dateTimeBetween('-3 months', 'now'),
            'points_expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'bonus_multipliers_used' => [
                'happy_hour' => $this->faker->numberBetween(0, 5),
                'birthday' => $this->faker->numberBetween(0, 2),
                'first_order' => $this->faker->numberBetween(0, 1),
                'referral' => $this->faker->numberBetween(0, 3),
            ],
            'redemption_history' => [
                'total_redemptions' => $this->faker->numberBetween(0, 20),
                'last_redemption_amount' => $this->faker->randomFloat(2, 0, 500),
                'favorite_redemption_type' => $this->faker->randomElement(['discount', 'free_item', 'free_delivery']),
            ],
        ];
    }

    /**
     * Indicate that the customer has no points.
     */
    public function noPoints(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_points' => 0.00,
            'total_points_earned' => 0.00,
            'total_points_redeemed' => 0.00,
            'total_points_expired' => 0.00,
            'last_points_earned_date' => null,
            'last_points_redeemed_date' => null,
        ]);
    }

    /**
     * Indicate that the customer has high points.
     */
    public function highPoints(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_points' => $this->faker->randomFloat(2, 5000, 50000),
            'total_points_earned' => $this->faker->randomFloat(2, 8000, 60000),
            'total_points_redeemed' => $this->faker->randomFloat(2, 2000, 10000),
        ]);
    }

    /**
     * Indicate that the customer has expiring points.
     */
    public function expiringPoints(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_expiry_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'current_points' => $this->faker->randomFloat(2, 100, 1000),
        ]);
    }

    /**
     * Indicate that the customer has expired points.
     */
    public function expiredPoints(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_expiry_date' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            'current_points' => $this->faker->randomFloat(2, 100, 1000),
        ]);
    }

    /**
     * Indicate that the customer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
} 