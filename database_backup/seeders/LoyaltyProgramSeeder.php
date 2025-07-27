<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LoyaltyProgram;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

final class LoyaltyProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            // Create a general Points-based Loyalty Program
            LoyaltyProgram::create([
                'restaurant_id' => $restaurant->id,
                'name' => $restaurant->name . ' Rewards',
                'description' => 'Earn points for every purchase at ' . $restaurant->name,
                'type' => 'points',
                'is_active' => true,
                'start_date' => now()->subMonths(6),
                'points_per_dollar' => 1.00,
                'dollar_per_point' => 0.01,
                'minimum_spend_for_points' => 10,
                'rules' => [
                    'signup_bonus_points' => 50,
                    'birthday_bonus_points' => 100,
                    'redemption_min_points' => 500,
                ],
            ]);

            // Create a Stamp Card Loyalty Program for select restaurants
            if (in_array($restaurant->slug, ['al-baik', 'pizza-hut-saudi'])) {
                LoyaltyProgram::create([
                    'restaurant_id' => $restaurant->id,
                    'name' => $restaurant->name . ' Stamp Card',
                    'description' => 'Collect stamps and get a free meal!',
                    'type' => 'stamps',
                    'is_active' => true,
                    'start_date' => now()->subMonths(3),
                    'rules' => [
                        'stamps_needed' => 10,
                        'reward_item_id' => null, // Placeholder for a specific free item
                        'reward_description' => 'Free meal on 10th stamp',
                    ],
                ]);
            }

            $this->command->info('Seeded loyalty programs for restaurant: ' . $restaurant->name);
        }

        $this->command->info('Successfully seeded Loyalty Program data.');
    }
}
