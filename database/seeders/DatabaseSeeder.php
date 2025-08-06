<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting FoodHub Database Seeding...');

        // Seed core data in dependency order
        $this->call([
            RestaurantSeeder::class,
            UserRoleSeeder::class,
            CustomerSeeder::class,
            CustomerAddressSeeder::class,
            MenuSeeder::class,
            DriverSeeder::class,
            LoyaltyProgramSeeder::class,
            RestaurantConfigSeeder::class,
            OrderSeeder::class,
            CustomerComplaintSeeder::class,
            CustomerSupportTicketSeeder::class,
            CustomerServiceSeeder::class,
        ]);

        $this->command->info('✅ FoodHub Database Seeding Completed Successfully!');
        $this->command->line('');
        $this->command->line('🏪 Sample restaurants with branches have been created');
        $this->command->line('👥 User accounts with different roles have been set up');
        $this->command->line('🍕 Sample customers and menu items are ready');
        $this->command->line('');
        $this->command->warn('📧 Remember to configure your .env file with proper database credentials');
        $this->command->warn('🔑 Default passwords are set for development - change them for production');
    }
}
