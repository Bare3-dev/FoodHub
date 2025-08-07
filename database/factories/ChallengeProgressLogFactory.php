<?php

namespace Database\Factories;

use App\Models\ChallengeProgressLog;
use App\Models\CustomerChallenge;
use App\Models\Customer;
use App\Models\Challenge;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeProgressLogFactory extends Factory
{
    protected $model = ChallengeProgressLog::class;

    public function definition(): array
    {
        $progressBefore = $this->faker->randomFloat(2, 0, 50);
        $progressIncrement = $this->faker->randomFloat(2, 0.5, 10);
        $progressAfter = $progressBefore + $progressIncrement;
        
        return [
            'customer_challenge_id' => CustomerChallenge::factory(),
            'customer_id' => Customer::factory(),
            'challenge_id' => Challenge::factory(),
            'order_id' => $this->faker->optional(0.7)->randomElement([Order::factory(), null]),
            'action_type' => $this->faker->randomElement([
                'order_placed',
                'review_written',
                'friend_referred',
                'item_tried',
                'amount_spent',
                'milestone_reached',
                'manual_adjustment',
            ]),
            'progress_before' => $progressBefore,
            'progress_after' => $progressAfter,
            'progress_increment' => $progressIncrement,
            'description' => $this->faker->sentence(),
            'event_data' => $this->faker->optional()->randomElement([
                ['order_number' => 'ORD-' . $this->faker->numberBetween(1000, 9999)],
                ['item_id' => $this->faker->numberBetween(1, 100), 'item_name' => 'Pizza Margherita'],
                ['amount' => $this->faker->randomFloat(2, 10, 100)],
            ]),
            'milestone_reached' => $this->faker->boolean(20),
            'milestone_type' => $this->faker->optional(0.2)->randomElement(['25%', '50%', '75%', 'completed']),
        ];
    }

    /**
     * Create a milestone log entry
     */
    public function milestone(string $milestoneType = '50%'): static
    {
        return $this->state(fn (array $attributes) => [
            'milestone_reached' => true,
            'milestone_type' => $milestoneType,
            'action_type' => 'milestone_reached',
            'description' => "Reached {$milestoneType} completion milestone",
        ]);
    }

    /**
     * Create an order-related progress log
     */
    public function orderProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'order_placed',
            'order_id' => Order::factory(),
            'description' => 'Progress updated from order placement',
            'event_data' => [
                'order_number' => 'ORD-' . $this->faker->numberBetween(1000, 9999),
                'order_total' => $this->faker->randomFloat(2, 20, 150),
            ],
        ]);
    }

    /**
     * Create a social action progress log
     */
    public function socialProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => $this->faker->randomElement(['review_written', 'friend_referred']),
            'order_id' => null,
            'description' => 'Progress updated from social action',
            'event_data' => [
                'platform' => $this->faker->randomElement(['app', 'web', 'social_media']),
            ],
        ]);
    }

    /**
     * Create a manual adjustment log
     */
    public function manualAdjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'manual_adjustment',
            'order_id' => null,
            'description' => 'Manual progress adjustment by admin',
            'event_data' => [
                'admin_id' => $this->faker->numberBetween(1, 10),
                'reason' => 'Correction for system error',
            ],
        ]);
    }
}