<?php

namespace Database\Factories;

use App\Models\LoyaltyPointsHistory;
use App\Models\CustomerLoyaltyPoint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyPointsHistory>
 */
class LoyaltyPointsHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LoyaltyPointsHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customerLoyaltyPoints = CustomerLoyaltyPoint::factory()->create();
        $order = Order::factory()->create();

        return [
            'customer_loyalty_points_id' => $customerLoyaltyPoints->id,
            'order_id' => $order->id,
            'transaction_type' => $this->faker->randomElement(['earned', 'redeemed', 'expired', 'adjusted', 'bonus']),
            'points_amount' => $this->faker->randomFloat(2, 10, 1000),
            'points_balance_after' => $this->faker->randomFloat(2, 0, 10000),
            'description' => $this->faker->sentence(),
            'transaction_details' => [
                'order_number' => $order->order_number,
                'order_amount' => $this->faker->randomFloat(2, 20, 500),
                'items_count' => $this->faker->numberBetween(1, 10),
                'payment_method' => $this->faker->randomElement(['cash', 'card', 'online']),
            ],
            'source' => $this->faker->randomElement(['order', 'bonus', 'referral', 'birthday', 'happy_hour', 'first_order', 'tier_upgrade', 'promotion', 'adjustment']),
            'bonus_multipliers_applied' => [
                'happy_hour' => $this->faker->optional(0.3)->randomFloat(2, 1.5, 3.0),
                'birthday' => $this->faker->optional(0.1)->randomFloat(2, 2.0, 5.0),
                'first_order' => $this->faker->optional(0.05)->randomFloat(2, 3.0, 10.0),
                'referral' => $this->faker->optional(0.1)->randomFloat(2, 1.5, 5.0),
            ],
            'base_amount' => $this->faker->randomFloat(2, 20, 500),
            'multiplier_applied' => $this->faker->randomFloat(2, 1.0, 3.0),
            'reference_id' => $this->faker->uuid(),
            'reference_type' => $this->faker->randomElement(['order', 'promotion', 'adjustment']),
            'is_reversible' => $this->faker->boolean(20),
            'reversed_at' => null,
            'reversed_by' => null,
        ];
    }

    /**
     * Create an earned transaction.
     */
    public function earned(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'earned',
            'source' => $this->faker->randomElement(['order', 'bonus', 'referral', 'birthday', 'happy_hour', 'first_order']),
            'points_amount' => $this->faker->randomFloat(2, 10, 500),
        ]);
    }

    /**
     * Create a redeemed transaction.
     */
    public function redeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'redeemed',
            'source' => $this->faker->randomElement(['discount', 'free_item', 'free_delivery']),
            'points_amount' => $this->faker->randomFloat(2, 50, 1000),
        ]);
    }

    /**
     * Create an expired transaction.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'expired',
            'source' => 'expiration',
            'points_amount' => $this->faker->randomFloat(2, 10, 200),
            'description' => 'Points expired due to inactivity',
        ]);
    }

    /**
     * Create a bonus transaction.
     */
    public function bonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'bonus',
            'source' => $this->faker->randomElement(['birthday', 'happy_hour', 'first_order', 'referral', 'tier_upgrade']),
            'points_amount' => $this->faker->randomFloat(2, 100, 1000),
            'multiplier_applied' => $this->faker->randomFloat(2, 2.0, 10.0),
        ]);
    }

    /**
     * Create a birthday bonus transaction.
     */
    public function birthdayBonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'bonus',
            'source' => 'birthday',
            'points_amount' => $this->faker->randomFloat(2, 200, 1000),
            'multiplier_applied' => 3.0,
            'description' => 'Birthday bonus points',
        ]);
    }

    /**
     * Create a happy hour bonus transaction.
     */
    public function happyHourBonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'bonus',
            'source' => 'happy_hour',
            'points_amount' => $this->faker->randomFloat(2, 50, 300),
            'multiplier_applied' => 2.0,
            'description' => 'Happy hour bonus points',
        ]);
    }

    /**
     * Create a first order bonus transaction.
     */
    public function firstOrderBonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'bonus',
            'source' => 'first_order',
            'points_amount' => $this->faker->randomFloat(2, 500, 2000),
            'multiplier_applied' => 5.0,
            'description' => 'First order bonus points',
        ]);
    }

    /**
     * Create a referral bonus transaction.
     */
    public function referralBonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'bonus',
            'source' => 'referral',
            'points_amount' => $this->faker->randomFloat(2, 1000, 5000),
            'multiplier_applied' => 10.0,
            'description' => 'Referral bonus points',
        ]);
    }

    /**
     * Create a reversed transaction.
     */
    public function reversed(): static
    {
        $user = User::factory()->create();

        return $this->state(fn (array $attributes) => [
            'reversed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'reversed_by' => $user->id,
        ]);
    }

    /**
     * Create a transaction with high points.
     */
    public function highPoints(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'points_balance_after' => $this->faker->randomFloat(2, 5000, 50000),
        ]);
    }

    /**
     * Create a transaction with low points.
     */
    public function lowPoints(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_amount' => $this->faker->randomFloat(2, 1, 50),
            'points_balance_after' => $this->faker->randomFloat(2, 0, 500),
        ]);
    }
} 