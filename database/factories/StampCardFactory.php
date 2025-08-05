<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\StampCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StampCard>
 */
class StampCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cardTypes = [
            StampCard::TYPE_GENERAL,
            StampCard::TYPE_BEVERAGES,
            StampCard::TYPE_DESSERTS,
            StampCard::TYPE_MAINS,
            StampCard::TYPE_HEALTHY,
        ];

        $cardType = $this->faker->randomElement($cardTypes);
        $stampsRequired = $this->faker->numberBetween(5, 15);
        $stampsEarned = $this->faker->numberBetween(0, $stampsRequired);
        $isCompleted = $stampsEarned >= $stampsRequired;

        return [
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'customer_id' => Customer::factory(),
            'card_type' => $cardType,
            'stamps_earned' => $stampsEarned,
            'stamps_required' => $stampsRequired,
            'is_completed' => $isCompleted,
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'completed_at' => $isCompleted ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'reward_description' => $this->getRewardDescription($cardType),
            'reward_value' => $this->getRewardValue($cardType),
        ];
    }

    /**
     * Get reward description for card type
     */
    private function getRewardDescription(string $cardType): string
    {
        $rewards = [
            StampCard::TYPE_GENERAL => 'Free dessert or beverage',
            StampCard::TYPE_BEVERAGES => 'Free beverage of your choice',
            StampCard::TYPE_DESSERTS => 'Free dessert of your choice',
            StampCard::TYPE_MAINS => 'Free main course up to $15',
            StampCard::TYPE_HEALTHY => 'Free healthy meal option',
        ];

        return $rewards[$cardType] ?? 'Free item of your choice';
    }

    /**
     * Get reward value for card type
     */
    private function getRewardValue(string $cardType): float
    {
        $values = [
            StampCard::TYPE_GENERAL => 8.00,
            StampCard::TYPE_BEVERAGES => 5.00,
            StampCard::TYPE_DESSERTS => 7.00,
            StampCard::TYPE_MAINS => 15.00,
            StampCard::TYPE_HEALTHY => 12.00,
        ];

        return $values[$cardType] ?? 10.00;
    }

    /**
     * Indicate that the stamp card is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stamps_earned' => $attributes['stamps_required'],
            'is_completed' => true,
            'completed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the stamp card is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the stamp card is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
} 