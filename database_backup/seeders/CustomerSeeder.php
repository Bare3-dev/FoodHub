<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+966501234567',
            'password' => Hash::make('password'), // default password
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'status' => 'active',
            'marketing_emails_enabled' => true,
            'sms_notifications_enabled' => true,
            'push_notifications_enabled' => true,
            'preferences' => ['halal', 'no_nuts'],
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
        ]);

        Customer::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '+966559876543',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'status' => 'active',
            'marketing_emails_enabled' => false,
            'sms_notifications_enabled' => true,
            'push_notifications_enabled' => false,
            'preferences' => ['vegetarian'],
            'date_of_birth' => '1985-05-20',
            'gender' => 'female',
        ]);

        Customer::factory()->count(10)->create(); // Create 10 more random customers

        $this->command->info('Successfully seeded Customer data.');
    }
}
