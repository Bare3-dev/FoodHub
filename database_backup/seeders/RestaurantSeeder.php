<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class RestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $restaurants = [
            [
                'name' => 'Al Baik',
                'description' => 'Famous Saudi fast food chain known for crispy fried chicken and garlic sauce',
                'cuisine_type' => 'Fast Food',
                'phone' => '+966-12-6677788',
                'email' => 'info@albaik.com',
                'website' => 'https://albaik.com',
                'business_hours' => [
                    'monday' => ['11:00', '23:00'],
                    'tuesday' => ['11:00', '23:00'],
                    'wednesday' => ['11:00', '23:00'],
                    'thursday' => ['11:00', '23:00'],
                    'friday' => ['14:00', '23:00'],
                    'saturday' => ['11:00', '23:00'],
                    'sunday' => ['11:00', '23:00'],
                ],
                'is_featured' => true,
                'verified_at' => now(),
                'branches' => [
                    [
                        'name' => 'Al Baik Corniche',
                        'address' => 'King Abdulaziz Road, Corniche',
                        'city' => 'Jeddah',
                        'state' => 'Makkah',
                        'postal_code' => '21514',
                        'latitude' => 21.5169,
                        'longitude' => 39.1748,
                        'phone' => '+966-12-6677789',
                        'delivery_fee' => 5.00,
                        'minimum_order_amount' => 25.00,
                    ],
                    [
                        'name' => 'Al Baik Tahlia',
                        'address' => 'Tahlia Street',
                        'city' => 'Jeddah',
                        'state' => 'Makkah',
                        'postal_code' => '21454',
                        'latitude' => 21.5755,
                        'longitude' => 39.1689,
                        'phone' => '+966-12-6677790',
                        'delivery_fee' => 5.00,
                        'minimum_order_amount' => 25.00,
                    ],
                ],
            ],
            [
                'name' => 'Kudu',
                'description' => 'Popular Saudi fast food restaurant chain serving burgers and local favorites',
                'cuisine_type' => 'Fast Food',
                'phone' => '+966-11-4444555',
                'email' => 'contact@kudu.com.sa',
                'website' => 'https://kudu.com.sa',
                'business_hours' => [
                    'monday' => ['10:00', '02:00'],
                    'tuesday' => ['10:00', '02:00'],
                    'wednesday' => ['10:00', '02:00'],
                    'thursday' => ['10:00', '02:00'],
                    'friday' => ['14:00', '02:00'],
                    'saturday' => ['10:00', '02:00'],
                    'sunday' => ['10:00', '02:00'],
                ],
                'is_featured' => true,
                'verified_at' => now(),
                'branches' => [
                    [
                        'name' => 'Kudu Riyadh Center',
                        'address' => 'King Fahd Road',
                        'city' => 'Riyadh',
                        'state' => 'Riyadh',
                        'postal_code' => '11564',
                        'latitude' => 24.7136,
                        'longitude' => 46.6753,
                        'phone' => '+966-11-4444556',
                        'delivery_fee' => 7.00,
                        'minimum_order_amount' => 30.00,
                    ],
                ],
            ],
            [
                'name' => 'Mama Noura',
                'description' => 'Traditional Saudi restaurant specializing in authentic local cuisine and traditional bread',
                'cuisine_type' => 'Saudi Traditional',
                'phone' => '+966-11-2233445',
                'email' => 'info@mamanoura.com',
                'business_hours' => [
                    'monday' => ['06:00', '23:00'],
                    'tuesday' => ['06:00', '23:00'],
                    'wednesday' => ['06:00', '23:00'],
                    'thursday' => ['06:00', '23:00'],
                    'friday' => ['06:00', '23:00'],
                    'saturday' => ['06:00', '23:00'],
                    'sunday' => ['06:00', '23:00'],
                ],
                'verified_at' => now(),
                'branches' => [
                    [
                        'name' => 'Mama Noura Al Olaya',
                        'address' => 'Olaya Street',
                        'city' => 'Riyadh',
                        'state' => 'Riyadh',
                        'postal_code' => '11372',
                        'latitude' => 24.6877,
                        'longitude' => 46.6859,
                        'phone' => '+966-11-2233446',
                        'delivery_fee' => 8.00,
                        'minimum_order_amount' => 40.00,
                    ],
                ],
            ],
            [
                'name' => 'Pizza Hut Saudi',
                'description' => 'International pizza chain with local adaptations and Saudi favorites',
                'cuisine_type' => 'Italian',
                'phone' => '+966-920-000-102',
                'email' => 'customer.care@pizzahut-sa.com',
                'website' => 'https://pizzahut.sa',
                'business_hours' => [
                    'monday' => ['11:00', '02:00'],
                    'tuesday' => ['11:00', '02:00'],
                    'wednesday' => ['11:00', '02:00'],
                    'thursday' => ['11:00', '02:00'],
                    'friday' => ['11:00', '02:00'],
                    'saturday' => ['11:00', '02:00'],
                    'sunday' => ['11:00', '02:00'],
                ],
                'verified_at' => now(),
                'branches' => [
                    [
                        'name' => 'Pizza Hut Dhahran',
                        'address' => 'King Faisal University Road',
                        'city' => 'Dhahran',
                        'state' => 'Eastern Province',
                        'postal_code' => '31261',
                        'latitude' => 26.3354,
                        'longitude' => 50.1329,
                        'phone' => '+966-13-8577888',
                        'delivery_fee' => 6.00,
                        'minimum_order_amount' => 35.00,
                    ],
                ],
            ],
        ];

        foreach ($restaurants as $restaurantData) {
            $branches = $restaurantData['branches'];
            unset($restaurantData['branches']);

            // Create restaurant
            $restaurant = Restaurant::create([
                'slug' => Str::slug($restaurantData['name']),
                'status' => 'active',
                'commission_rate' => 15.00,
                'settings' => [
                    'auto_accept_orders' => false,
                    'preparation_time_buffer' => 5,
                    'max_daily_orders' => 200,
                ],
                ...$restaurantData,
            ]);

            // Create branches for this restaurant
            foreach ($branches as $branchData) {
                RestaurantBranch::create([
                    'restaurant_id' => $restaurant->id,
                    'slug' => Str::slug($branchData['name']),
                    'operating_hours' => $restaurant->business_hours,
                    'delivery_zones' => [],
                    'estimated_delivery_time' => 30,
                    'status' => 'active',
                    'accepts_online_orders' => true,
                    'accepts_delivery' => true,
                    'accepts_pickup' => true,
                    'country' => 'SA',
                    'settings' => [
                        'auto_print_orders' => true,
                        'sound_notification' => true,
                    ],
                    ...$branchData,
                ]);
            }
        }

        $this->command->info('Successfully created ' . count($restaurants) . ' restaurants with their branches.');
    }
}
