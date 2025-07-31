<?php

namespace Database\Factories;

use App\Models\LoyaltyTier;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyTier>
 */
class LoyaltyTierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LoyaltyTier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loyaltyProgram = LoyaltyProgram::factory()->create();

        return [
            'loyalty_program_id' => $loyaltyProgram->id,
            'name' => $this->faker->randomElement(['bronze', 'silver', 'gold', 'platinum', 'diamond']),
            'display_name' => $this->faker->randomElement(['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond']),
            'description' => $this->faker->sentence(),
            'min_points_required' => $this->faker->randomFloat(2, 0, 10000),
            'max_points_capacity' => $this->faker->optional()->randomFloat(2, 10000, 100000),
            'points_multiplier' => $this->faker->randomFloat(2, 1.0, 3.0),
            'discount_percentage' => $this->faker->randomFloat(2, 0, 25),
            'free_delivery' => $this->faker->boolean(30),
            'priority_support' => $this->faker->boolean(20),
            'exclusive_offers' => $this->faker->boolean(40),
            'birthday_reward' => $this->faker->boolean(50),
            'additional_benefits' => [
                'early_access' => $this->faker->boolean(30),
                'special_menu_items' => $this->faker->boolean(25),
                'reservation_priority' => $this->faker->boolean(20),
                'customized_experience' => $this->faker->boolean(15),
            ],
            'color_code' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement(['star', 'crown', 'gem', 'trophy', 'medal']),
            'sort_order' => $this->faker->numberBetween(1, 5),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    /**
     * Create a Bronze tier.
     */
    public function bronze(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'bronze',
            'display_name' => 'Bronze',
            'min_points_required' => 0,
            'points_multiplier' => 1.0,
            'discount_percentage' => 0,
            'free_delivery' => false,
            'priority_support' => false,
            'exclusive_offers' => false,
            'birthday_reward' => false,
            'color_code' => '#CD7F32',
            'icon' => 'star',
            'sort_order' => 1,
        ]);
    }

    /**
     * Create a Silver tier.
     */
    public function silver(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'silver',
            'display_name' => 'Silver',
            'min_points_required' => 1000,
            'points_multiplier' => 1.2,
            'discount_percentage' => 5,
            'free_delivery' => false,
            'priority_support' => false,
            'exclusive_offers' => true,
            'birthday_reward' => true,
            'color_code' => '#C0C0C0',
            'icon' => 'star',
            'sort_order' => 2,
        ]);
    }

    /**
     * Create a Gold tier.
     */
    public function gold(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'gold',
            'display_name' => 'Gold',
            'min_points_required' => 5000,
            'points_multiplier' => 1.5,
            'discount_percentage' => 10,
            'free_delivery' => true,
            'priority_support' => false,
            'exclusive_offers' => true,
            'birthday_reward' => true,
            'color_code' => '#FFD700',
            'icon' => 'crown',
            'sort_order' => 3,
        ]);
    }

    /**
     * Create a Platinum tier.
     */
    public function platinum(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'platinum',
            'display_name' => 'Platinum',
            'min_points_required' => 15000,
            'points_multiplier' => 2.0,
            'discount_percentage' => 15,
            'free_delivery' => true,
            'priority_support' => true,
            'exclusive_offers' => true,
            'birthday_reward' => true,
            'color_code' => '#E5E4E2',
            'icon' => 'gem',
            'sort_order' => 4,
        ]);
    }

    /**
     * Create a Diamond tier.
     */
    public function diamond(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'diamond',
            'display_name' => 'Diamond',
            'min_points_required' => 50000,
            'points_multiplier' => 3.0,
            'discount_percentage' => 25,
            'free_delivery' => true,
            'priority_support' => true,
            'exclusive_offers' => true,
            'birthday_reward' => true,
            'color_code' => '#B9F2FF',
            'icon' => 'trophy',
            'sort_order' => 5,
        ]);
    }

    /**
     * Create a tier with high benefits.
     */
    public function highBenefits(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_multiplier' => $this->faker->randomFloat(2, 2.0, 5.0),
            'discount_percentage' => $this->faker->randomFloat(2, 15, 50),
            'free_delivery' => true,
            'priority_support' => true,
            'exclusive_offers' => true,
            'birthday_reward' => true,
        ]);
    }

    /**
     * Create a tier with low benefits.
     */
    public function lowBenefits(): static
    {
        return $this->state(fn (array $attributes) => [
            'points_multiplier' => 1.0,
            'discount_percentage' => 0,
            'free_delivery' => false,
            'priority_support' => false,
            'exclusive_offers' => false,
            'birthday_reward' => false,
        ]);
    }

    /**
     * Create an inactive tier.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
} 