<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\RestaurantConfig;
use Illuminate\Database\Seeder;

final class RestaurantConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            // Create default loyalty program configurations
            $this->createLoyaltyConfigs($restaurant);
            
            // Create operating hours configurations
            $this->createOperatingHoursConfigs($restaurant);
            
            // Create some custom configurations
            $this->createCustomConfigs($restaurant);
        }
    }

    /**
     * Create loyalty program configurations
     */
    private function createLoyaltyConfigs(Restaurant $restaurant): void
    {
        $loyaltyConfigs = [
            [
                'config_key' => 'loyalty_points_per_currency',
                'config_value' => '1',
                'data_type' => 'integer',
                'description' => 'Points earned per currency unit spent',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'loyalty_currency_per_point',
                'config_value' => '0.01',
                'data_type' => 'float',
                'description' => 'Currency value per loyalty point',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'loyalty_tier_thresholds',
                'config_value' => json_encode([
                    'bronze' => 0,
                    'silver' => 100,
                    'gold' => 500,
                    'platinum' => 1000,
                ]),
                'data_type' => 'array',
                'description' => 'Points required for each loyalty tier',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'loyalty_spin_wheel_probabilities',
                'config_value' => json_encode([
                    'points_10' => 0.4,
                    'points_25' => 0.3,
                    'points_50' => 0.2,
                    'points_100' => 0.1,
                ]),
                'data_type' => 'array',
                'description' => 'Probability distribution for spin wheel prizes',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'loyalty_stamp_card_requirements',
                'config_value' => json_encode([
                    'stamps_needed' => 10,
                    'reward_value' => 5.00,
                ]),
                'data_type' => 'array',
                'description' => 'Requirements for stamp card completion',
                'is_sensitive' => false,
            ],
        ];

        foreach ($loyaltyConfigs as $config) {
            RestaurantConfig::create(array_merge($config, [
                'restaurant_id' => $restaurant->id,
            ]));
        }
    }

    /**
     * Create operating hours configurations
     */
    private function createOperatingHoursConfigs(Restaurant $restaurant): void
    {
        $operatingHours = [
            'monday' => ['open' => '09:00', 'close' => '22:00'],
            'tuesday' => ['open' => '09:00', 'close' => '22:00'],
            'wednesday' => ['open' => '09:00', 'close' => '22:00'],
            'thursday' => ['open' => '09:00', 'close' => '22:00'],
            'friday' => ['open' => '09:00', 'close' => '23:00'],
            'saturday' => ['open' => '10:00', 'close' => '23:00'],
            'sunday' => ['open' => '10:00', 'close' => '21:00'],
        ];

        RestaurantConfig::create([
            'restaurant_id' => $restaurant->id,
            'config_key' => 'operating_hours',
            'config_value' => json_encode($operatingHours),
            'data_type' => 'array',
            'description' => 'Restaurant operating hours',
            'is_sensitive' => false,
        ]);
    }

    /**
     * Create custom configurations
     */
    private function createCustomConfigs(Restaurant $restaurant): void
    {
        $customConfigs = [
            [
                'config_key' => 'delivery_radius_km',
                'config_value' => '10',
                'data_type' => 'integer',
                'description' => 'Maximum delivery radius in kilometers',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'minimum_order_amount',
                'config_value' => '15.00',
                'data_type' => 'float',
                'description' => 'Minimum order amount for delivery',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'auto_accept_orders',
                'config_value' => '1',
                'data_type' => 'boolean',
                'description' => 'Automatically accept incoming orders',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'notification_email',
                'config_value' => 'orders@' . $restaurant->slug . '.com',
                'data_type' => 'string',
                'description' => 'Email for order notifications',
                'is_sensitive' => false,
            ],
            [
                'config_key' => 'payment_gateway_key',
                'config_value' => 'sk_test_' . str_random(24),
                'data_type' => 'string',
                'description' => 'Payment gateway API key',
                'is_sensitive' => true,
            ],
        ];

        foreach ($customConfigs as $config) {
            RestaurantConfig::create(array_merge($config, [
                'restaurant_id' => $restaurant->id,
            ]));
        }
    }
} 