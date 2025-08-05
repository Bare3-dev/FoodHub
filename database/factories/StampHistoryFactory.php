<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\StampCard;
use App\Models\StampHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StampHistory>
 */
class StampHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actionTypes = [
            StampHistory::ACTION_STAMP_EARNED,
            StampHistory::ACTION_CARD_COMPLETED,
            StampHistory::ACTION_REWARD_CLAIMED,
        ];

        $actionType = $this->faker->randomElement($actionTypes);
        $stampsBefore = $this->faker->numberBetween(0, 8);
        $stampsAdded = $actionType === StampHistory::ACTION_STAMP_EARNED ? $this->faker->numberBetween(1, 3) : 0;
        $stampsAfter = $stampsBefore + $stampsAdded;

        return [
            'stamp_card_id' => StampCard::factory(),
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'stamps_added' => $stampsAdded,
            'stamps_before' => $stampsBefore,
            'stamps_after' => $stampsAfter,
            'action_type' => $actionType,
            'description' => $this->getDescription($actionType),
            'metadata' => $this->getMetadata($actionType),
        ];
    }

    /**
     * Get description for action type
     */
    private function getDescription(string $actionType): string
    {
        $descriptions = [
            StampHistory::ACTION_STAMP_EARNED => 'Earned stamp(s) from order',
            StampHistory::ACTION_CARD_COMPLETED => 'Stamp card completed!',
            StampHistory::ACTION_REWARD_CLAIMED => 'Reward claimed from completed card',
        ];

        return $descriptions[$actionType] ?? 'Stamp card activity';
    }

    /**
     * Get metadata for action type
     */
    private function getMetadata(string $actionType): array
    {
        $baseMetadata = [
            'order_number' => 'ORD-' . $this->faker->numberBetween(1000, 9999),
            'order_total' => $this->faker->randomFloat(2, 10, 100),
            'card_type' => $this->faker->randomElement(['general', 'beverages', 'desserts', 'mains', 'healthy']),
        ];

        switch ($actionType) {
            case StampHistory::ACTION_CARD_COMPLETED:
                return array_merge($baseMetadata, [
                    'reward_description' => 'Free item of your choice',
                    'reward_value' => $this->faker->randomFloat(2, 5, 20),
                    'completed_at' => now(),
                ]);

            case StampHistory::ACTION_REWARD_CLAIMED:
                return array_merge($baseMetadata, [
                    'reward_description' => 'Free item of your choice',
                    'reward_value' => $this->faker->randomFloat(2, 5, 20),
                    'claimed_at' => now(),
                ]);

            default:
                return $baseMetadata;
        }
    }

    /**
     * Indicate that the history record is for stamp earned.
     */
    public function stampEarned(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => StampHistory::ACTION_STAMP_EARNED,
            'stamps_added' => $this->faker->numberBetween(1, 3),
            'description' => 'Earned stamp(s) from order',
        ]);
    }

    /**
     * Indicate that the history record is for card completed.
     */
    public function cardCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => StampHistory::ACTION_CARD_COMPLETED,
            'stamps_added' => 0,
            'description' => 'Stamp card completed!',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'reward_description' => 'Free item of your choice',
                'reward_value' => $this->faker->randomFloat(2, 5, 20),
                'completed_at' => now(),
            ]),
        ]);
    }

    /**
     * Indicate that the history record is for reward claimed.
     */
    public function rewardClaimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => StampHistory::ACTION_REWARD_CLAIMED,
            'stamps_added' => 0,
            'description' => 'Reward claimed from completed card',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'reward_description' => 'Free item of your choice',
                'reward_value' => $this->faker->randomFloat(2, 5, 20),
                'claimed_at' => now(),
            ]),
        ]);
    }
} 