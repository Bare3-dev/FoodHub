<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'date_of_birth' => $this->faker->date('Y-m-d', '-21 years'),
            'password' => Hash::make('password'),
            'national_id' => $this->faker->unique()->numerify('##########'),
            'driver_license_number' => $this->faker->unique()->bothify('??######'),
            'license_expiry_date' => $this->faker->dateTimeBetween('now', '+5 years')->format('Y-m-d'),
            'vehicle_type' => $this->faker->randomElement(['car', 'motorcycle', 'bicycle']),
            'vehicle_make' => $this->faker->randomElement(['Honda', 'Toyota', 'Ford', 'Chevrolet', 'Nissan']),
            'vehicle_model' => $this->faker->word(),
            'vehicle_year' => $this->faker->numberBetween(2010, 2024),
            'vehicle_color' => $this->faker->colorName(),
            'vehicle_plate_number' => $this->faker->unique()->bothify('???-####'),
            'current_latitude' => $this->faker->latitude(25, 49),
            'current_longitude' => $this->faker->longitude(-125, -66),
            'is_available' => $this->faker->boolean(70),
            'is_online' => $this->faker->boolean(60),
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended', 'pending_verification']),
            'rating' => $this->faker->randomFloat(2, 3.5, 5.0),
            'total_deliveries' => $this->faker->numberBetween(0, 500),
            'completed_deliveries' => $this->faker->numberBetween(0, 400),
            'cancelled_deliveries' => $this->faker->numberBetween(0, 50),
            'total_earnings' => $this->faker->randomFloat(2, 0, 5000),
            'documents' => json_encode([
                'id_document' => 'id_' . $this->faker->uuid() . '.jpg',
                'license_document' => 'license_' . $this->faker->uuid() . '.jpg',
                'vehicle_registration' => 'reg_' . $this->faker->uuid() . '.jpg'
            ]),
            'banking_info' => json_encode([
                'account_holder' => $this->faker->name(),
                'account_number' => $this->faker->bankAccountNumber(),
                'routing_number' => $this->faker->numerify('#########'),
                'bank_name' => $this->faker->randomElement(['Chase', 'Bank of America', 'Wells Fargo', 'Citibank'])
            ]),
            'email_verified_at' => $this->faker->boolean(80) ? now() : null,
            'phone_verified_at' => $this->faker->boolean(85) ? now() : null,
            'verified_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween('-6 months', 'now') : null,
            'last_active_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
