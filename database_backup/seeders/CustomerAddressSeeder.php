<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;

final class CustomerAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch some existing customers
        $customers = Customer::all();

        foreach ($customers as $customer) {
            // Create a default home address for each customer
            CustomerAddress::create([
                'customer_id' => $customer->id,
                'label' => 'Home',
                'street_address' => '123 Main Street',
                'apartment_number' => 'Apt ' . mt_rand(1, 20),
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'postal_code' => '11564',
                'country' => 'SA',
                'latitude' => 24.7136 + (mt_rand(-50, 50) / 10000),
                'longitude' => 46.6753 + (mt_rand(-50, 50) / 10000),
                'delivery_notes' => 'Ring the bell twice.',
                'is_default' => true,
                'is_validated' => true,
                'validated_at' => now(),
            ]);

            // Create an optional work address for some customers
            if (mt_rand(0, 1) === 1) { // 50% chance
                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'label' => 'Work',
                    'street_address' => 'King Fahd Road',
                    'building_name' => 'Kingdom Centre',
                    'floor_number' => 'Level ' . mt_rand(10, 50),
                    'city' => 'Riyadh',
                    'state' => 'Riyadh',
                    'postal_code' => '11564',
                    'country' => 'SA',
                    'latitude' => 24.7118 + (mt_rand(-50, 50) / 10000),
                    'longitude' => 46.6749 + (mt_rand(-50, 50) / 10000),
                    'delivery_notes' => 'Deliver to reception.',
                    'is_default' => false,
                    'is_validated' => true,
                    'validated_at' => now(),
                ]);
            }
        }

        $this->command->info('Successfully seeded Customer Address data.');
    }
}
