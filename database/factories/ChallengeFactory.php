<?php

namespace Database\Factories;

use App\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    public function definition(): array
    {
        $challengeTypes = ['frequency', 'variety', 'value', 'social', 'seasonal', 'referral'];
        $rewardTypes = ['points', 'discount', 'free_item', 'coupon'];
        $selectedType = $this->faker->randomElement($challengeTypes);

        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'challenge_type' => $selectedType,
            'requirements' => $this->getRequirementsForType($selectedType),
            'reward_type' => $this->faker->randomElement($rewardTypes),
            'reward_value' => $this->faker->randomFloat(2, 10, 500),
            'reward_metadata' => null,
            'start_date' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
            'end_date' => $this->faker->dateTimeBetween('+1 week', '+4 weeks'),
            'duration_days' => $this->faker->optional()->numberBetween(1, 30),
            'target_segments' => $this->faker->optional()->randomElement([
                ['tier' => 'gold'],
                ['region' => 'riyadh'],
                ['age_group' => '25-35'],
            ]),
            'is_active' => $this->faker->boolean(80),
            'is_repeatable' => $this->faker->boolean(30),
            'max_participants' => $this->faker->optional()->numberBetween(10, 1000),
            'priority' => $this->faker->numberBetween(1, 10),
            'metadata' => $this->faker->optional()->randomElement([
                ['difficulty' => 'easy'],
                ['category' => 'weekly'],
                ['theme' => 'summer'],
            ]),
        ];
    }

    /**
     * Get requirements based on challenge type
     */
    private function getRequirementsForType(string $type): array
    {
        return match ($type) {
            'frequency' => ['order_count' => $this->faker->numberBetween(3, 10)],
            'variety' => ['unique_items' => $this->faker->numberBetween(2, 8)],
            'value' => ['total_amount' => $this->faker->randomFloat(2, 50, 500)],
            'social' => ['action_count' => $this->faker->numberBetween(1, 5)],
            'seasonal' => [
                'target_value' => $this->faker->numberBetween(1, 3),
                'seasonal_items' => $this->faker->randomElements([1, 2, 3, 4, 5], 2),
            ],
            'referral' => ['referral_count' => $this->faker->numberBetween(1, 5)],
            default => [],
        };
    }

    /**
     * Create an active challenge
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
        ]);
    }

    /**
     * Create an inactive challenge
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an expired challenge
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subWeeks(2),
            'end_date' => now()->subWeek(),
        ]);
    }

    /**
     * Create a frequency type challenge
     */
    public function frequency(): static
    {
        return $this->state(fn (array $attributes) => [
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 5],
        ]);
    }

    /**
     * Create a variety type challenge
     */
    public function variety(): static
    {
        return $this->state(fn (array $attributes) => [
            'challenge_type' => 'variety',
            'requirements' => ['unique_items' => 3],
        ]);
    }

    /**
     * Create a value type challenge
     */
    public function value(): static
    {
        return $this->state(fn (array $attributes) => [
            'challenge_type' => 'value',
            'requirements' => ['total_amount' => 100.00],
        ]);
    }

    /**
     * Create a challenge with max participants
     */
    public function withMaxParticipants(int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => $max,
        ]);
    }
}