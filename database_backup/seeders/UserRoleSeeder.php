<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a Super Admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@foodhub.com',
            'password' => Hash::make('password'), // Default password for development
            'role' => 'SUPER_ADMIN',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Fetch the first restaurant and branch created by RestaurantSeeder
        $restaurant1 = Restaurant::where('slug', 'al-baik')->first();
        $restaurantBranch1 = $restaurant1 ? $restaurant1->branches()->where('slug', 'al-baik-corniche')->first() : null;

        $restaurant2 = Restaurant::where('slug', 'kudu')->first();
        $restaurantBranch2 = $restaurant2 ? $restaurant2->branches()->where('slug', 'kudu-riyadh-center')->first() : null;

        if ($restaurant1) {
            // Create Restaurant Owner for Al Baik
            User::create([
                'name' => 'Al Baik Owner',
                'email' => 'albaik.owner@foodhub.com',
                'password' => Hash::make('password'),
                'restaurant_id' => $restaurant1->id,
                'role' => 'RESTAURANT_OWNER',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            if ($restaurantBranch1) {
                // Create Branch Manager for Al Baik Corniche
                User::create([
                    'name' => 'Al Baik Corniche Manager',
                    'email' => 'albaik.corniche@foodhub.com',
                    'password' => Hash::make('password'),
                    'restaurant_id' => $restaurant1->id,
                    'restaurant_branch_id' => $restaurantBranch1->id,
                    'role' => 'BRANCH_MANAGER',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                // Create Cashier for Al Baik Corniche
                User::create([
                    'name' => 'Al Baik Corniche Cashier',
                    'email' => 'albaik.cashier@foodhub.com',
                    'password' => Hash::make('password'),
                    'restaurant_id' => $restaurant1->id,
                    'restaurant_branch_id' => $restaurantBranch1->id,
                    'role' => 'CASHIER',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }
        }

        if ($restaurant2) {
            if ($restaurantBranch2) {
                // Create Branch Manager for Kudu Riyadh
                User::create([
                    'name' => 'Kudu Riyadh Manager',
                    'email' => 'kudu.riyadh@foodhub.com',
                    'password' => Hash::make('password'),
                    'restaurant_id' => $restaurant2->id,
                    'restaurant_branch_id' => $restaurantBranch2->id,
                    'role' => 'BRANCH_MANAGER',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }
        }

        // Create a Delivery Manager (not tied to a specific restaurant/branch initially)
        User::create([
            'name' => 'Delivery Manager',
            'email' => 'delivery.manager@foodhub.com',
            'password' => Hash::make('password'),
            'role' => 'DELIVERY_MANAGER',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create a Customer Service user
        User::create([
            'name' => 'Customer Service',
            'email' => 'customerservice@foodhub.com',
            'password' => Hash::make('password'),
            'role' => 'CUSTOMER_SERVICE',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Successfully seeded User roles.');
    }
}
