<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerChallenge>
 */
class CustomerChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $challenge = Challenge::factory()->create();
        $progressTarget = $this->faker->numberBetween(10, 100);
        $progressCurrent = $this->faker->numberBetween(0, $progressTarget);
        $progressPercentage = ($progressCurrent / $progressTarget) * 100;
        
        return [
            'customer_id' => Customer::factory(),
            'challenge_id' => $challenge->id,
                    'assigned_at' => $this->faker->dateTimeBetween('-1 month', now()),
        'started_at' => $this->faker->optional(70)->dateTimeBetween('-1 month', now()),
        'completed_at' => $this->faker->optional(30)->dateTimeBetween('-1 month', now()),
        'expires_at' => $this->faker->optional(80)->dateTimeBetween(now(), '+1 month'),
            'status' => $this->faker->randomElement(['assigned', 'active', 'completed', 'rewarded', 'expired', 'cancelled']),
            'progress_current' => $progressCurrent,
            'progress_target' => $progressTarget,
            'progress_percentage' => $progressPercentage,
            'progress_details' => $this->faker->optional()->randomElement([
                ['steps_completed' => $this->faker->numberBetween(1, 5)],
                ['milestones' => ['milestone1' => true, 'milestone2' => false]],
                ['checkpoints' => ['checkpoint1' => 'completed', 'checkpoint2' => 'pending']],
            ]),
            'reward_claimed' => $this->faker->boolean(20),
            'reward_claimed_at' => $this->faker->optional(20)->dateTimeBetween('-1 month', now()),
            'reward_details' => $this->faker->optional()->randomElement([
                ['points_earned' => $this->faker->numberBetween(100, 500)],
                ['discount_amount' => $this->faker->randomFloat(2, 5, 50)],
                ['free_item' => $this->faker->word()],
            ]),
            'metadata' => $this->faker->optional()->randomElement([
                ['difficulty' => 'easy', 'category' => 'weekly'],
                ['theme' => 'summer', 'bonus_multiplier' => 1.5],
                ['special_event' => true, 'event_name' => 'Holiday Challenge'],
            ]),
        ];
    }

    /**
     * Indicate that the customer challenge is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate that the customer challenge is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subDays(5),
            'completed_at' => now(),
            'progress_percentage' => 100,
            'progress_current' => $this->faker->numberBetween(10, 100),
        ]);
    }

    /**
     * Indicate that the customer challenge is rewarded.
     */
    public function rewarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rewarded',
            'started_at' => now()->subDays(5),
            'completed_at' => now()->subDays(1),
            'progress_percentage' => 100,
            'progress_current' => $this->faker->numberBetween(10, 100),
            'reward_claimed' => true,
            'reward_claimed_at' => now(),
        ]);
    }

    /**
     * Indicate that the customer challenge has progress.
     */
    public function withProgress(int $progressPercentage = null): static
    {
        $progressTarget = $this->faker->numberBetween(10, 100);
        $progressCurrent = $progressPercentage ? ($progressPercentage / 100) * $progressTarget : $this->faker->numberBetween(1, $progressTarget);
        
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'started_at' => now()->subDays(3),
            'progress_current' => $progressCurrent,
            'progress_target' => $progressTarget,
            'progress_percentage' => $progressPercentage ?? ($progressCurrent / $progressTarget) * 100,
        ]);
    }
} 