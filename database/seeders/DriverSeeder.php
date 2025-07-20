<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Driver::create([
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'email' => 'ahmed.ali@foodhub.com',
            'phone' => '+966501112222',
            'password' => Hash::make('password'),
            'date_of_birth' => '1990-01-01',
            'national_id' => '1012345678',
            'driver_license_number' => 'DRL12345',
            'license_expiry_date' => '2028-12-31',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_number' => 'ABC1234',
            'status' => 'active',
            'is_online' => true,
            'is_available' => true,
            'current_latitude' => 24.7136 + (mt_rand(-50, 50) / 10000),
            'current_longitude' => 46.6753 + (mt_rand(-50, 50) / 10000),
            'verified_at' => now(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        Driver::create([
            'first_name' => 'Fahad',
            'last_name' => 'Al-Qahtani',
            'email' => 'fahad.qahtani@foodhub.com',
            'phone' => '+966503334444',
            'password' => Hash::make('password'),
            'date_of_birth' => '1988-03-10',
            'national_id' => '1098765432',
            'driver_license_number' => 'DRL54321',
            'license_expiry_date' => '2027-06-30',
            'vehicle_type' => 'car',
            'vehicle_plate_number' => 'XYZ5678',
            'status' => 'active',
            'is_online' => true,
            'is_available' => false,
            'current_latitude' => 21.5833 + (mt_rand(-50, 50) / 10000),
            'current_longitude' => 39.1917 + (mt_rand(-50, 50) / 10000),
            'verified_at' => now(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        Driver::factory()->count(5)->create(); // Create 5 more random drivers

        $this->command->info('Successfully seeded Driver data.');
    }
}
