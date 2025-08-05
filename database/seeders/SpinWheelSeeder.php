<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SpinWheel;
use App\Models\SpinWheelPrize;
use Illuminate\Database\Seeder;

final class SpinWheelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main spin wheel
        $spinWheel = SpinWheel::create([
            'name' => 'FoodHub Daily Spin Wheel',
            'description' => 'Spin the wheel daily to win amazing prizes!',
            'is_active' => true,
            'daily_free_spins_base' => 1,
            'max_daily_spins' => 5,
            'spin_cost_points' => 100.00,
            'tier_spin_multipliers' => [
                1 => 1.0,  // Bronze tier
                2 => 1.5,  // Silver tier
                3 => 2.0,  // Gold tier
                4 => 2.5,  // Platinum tier
                5 => 3.0,  // Diamond tier
            ],
            'tier_probability_boost' => [
                1 => 1.0,  // Bronze tier - no boost
                2 => 1.2,  // Silver tier - 20% better odds
                3 => 1.5,  // Gold tier - 50% better odds
                4 => 2.0,  // Platinum tier - 100% better odds
                5 => 3.0,  // Diamond tier - 200% better odds
            ],
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        // Create prizes
        $prizes = [
            // Discount prizes
            [
                'name' => '10% Off Next Order',
                'description' => 'Get 10% off your next order',
                'type' => 'discount',
                'value' => 10.00,
                'value_type' => 'percentage',
                'probability' => 0.25,
                'max_redemptions' => null,
                'tier_restrictions' => null,
                'conditions' => ['expiration_days' => 7],
            ],
            [
                'name' => '15% Off Next Order',
                'description' => 'Get 15% off your next order',
                'type' => 'discount',
                'value' => 15.00,
                'value_type' => 'percentage',
                'probability' => 0.15,
                'max_redemptions' => null,
                'tier_restrictions' => [2, 3, 4, 5], // Silver and above
                'conditions' => ['expiration_days' => 7],
            ],
            [
                'name' => '20% Off Next Order',
                'description' => 'Get 20% off your next order',
                'type' => 'discount',
                'value' => 20.00,
                'value_type' => 'percentage',
                'probability' => 0.10,
                'max_redemptions' => null,
                'tier_restrictions' => [3, 4, 5], // Gold and above
                'conditions' => ['expiration_days' => 7],
            ],
            
            // Bonus points prizes
            [
                'name' => '50 Bonus Points',
                'description' => 'Earn 50 bonus loyalty points',
                'type' => 'bonus_points',
                'value' => 50.00,
                'value_type' => 'points',
                'probability' => 0.20,
                'max_redemptions' => null,
                'tier_restrictions' => null,
                'conditions' => null,
            ],
            [
                'name' => '100 Bonus Points',
                'description' => 'Earn 100 bonus loyalty points',
                'type' => 'bonus_points',
                'value' => 100.00,
                'value_type' => 'points',
                'probability' => 0.10,
                'max_redemptions' => null,
                'tier_restrictions' => [2, 3, 4, 5], // Silver and above
                'conditions' => null,
            ],
            [
                'name' => '200 Bonus Points',
                'description' => 'Earn 200 bonus loyalty points',
                'type' => 'bonus_points',
                'value' => 200.00,
                'value_type' => 'points',
                'probability' => 0.05,
                'max_redemptions' => null,
                'tier_restrictions' => [3, 4, 5], // Gold and above
                'conditions' => null,
            ],
            
            // Free delivery prizes
            [
                'name' => 'Free Delivery',
                'description' => 'Free delivery on your next order',
                'type' => 'free_delivery',
                'value' => 0.00,
                'value_type' => 'service',
                'probability' => 0.15,
                'max_redemptions' => null,
                'tier_restrictions' => null,
                'conditions' => ['expiration_days' => 3],
            ],
            
            // Free item prizes
            [
                'name' => 'Free Dessert',
                'description' => 'Get a free dessert with your next order',
                'type' => 'free_item',
                'value' => 0.00,
                'value_type' => 'item',
                'probability' => 0.10,
                'max_redemptions' => null,
                'tier_restrictions' => null,
                'conditions' => ['expiration_days' => 7, 'item_type' => 'dessert'],
            ],
            [
                'name' => 'Free Beverage',
                'description' => 'Get a free beverage with your next order',
                'type' => 'free_item',
                'value' => 0.00,
                'value_type' => 'item',
                'probability' => 0.10,
                'max_redemptions' => null,
                'tier_restrictions' => null,
                'conditions' => ['expiration_days' => 7, 'item_type' => 'beverage'],
            ],
            
            // Cashback prizes (rare)
            [
                'name' => '$5 Cashback',
                'description' => 'Get $5 cashback on your next order',
                'type' => 'cashback',
                'value' => 5.00,
                'value_type' => 'fixed_amount',
                'probability' => 0.05,
                'max_redemptions' => null,
                'tier_restrictions' => [3, 4, 5], // Gold and above
                'conditions' => ['expiration_days' => 14],
            ],
            [
                'name' => '$10 Cashback',
                'description' => 'Get $10 cashback on your next order',
                'type' => 'cashback',
                'value' => 10.00,
                'value_type' => 'fixed_amount',
                'probability' => 0.02,
                'max_redemptions' => null,
                'tier_restrictions' => [4, 5], // Platinum and above
                'conditions' => ['expiration_days' => 14],
            ],
        ];

        foreach ($prizes as $prizeData) {
            SpinWheelPrize::create(array_merge($prizeData, [
                'spin_wheel_id' => $spinWheel->id,
                'is_active' => true,
                'current_redemptions' => 0,
            ]));
        }

        $this->command->info('Spin wheel seeded successfully!');
    }
} 