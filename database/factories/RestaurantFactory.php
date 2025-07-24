<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cuisineTypes = ['Italian', 'Chinese', 'Mexican', 'Indian', 'American', 'Thai', 'Japanese', 'Mediterranean'];
        
        return [
            'name' => $this->faker->company() . ' Restaurant',
            'slug' => $this->faker->unique()->slug(3),
            'description' => $this->faker->paragraph(3),
            'cuisine_type' => $this->faker->randomElement($cuisineTypes),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'website' => $this->faker->url(),
            'logo_url' => $this->faker->imageUrl(300, 300, 'food'),
            'cover_image_url' => $this->faker->imageUrl(800, 400, 'restaurant'),
            'business_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00'],
                'wednesday' => ['open' => '09:00', 'close' => '22:00'],
                'thursday' => ['open' => '09:00', 'close' => '22:00'],
                'friday' => ['open' => '09:00', 'close' => '23:00'],
                'saturday' => ['open' => '10:00', 'close' => '23:00'],
                'sunday' => ['open' => '10:00', 'close' => '21:00']
            ],
            'settings' => [
                'accepts_cash' => $this->faker->boolean(80),
                'accepts_card' => true,
                'max_delivery_distance' => $this->faker->numberBetween(5, 15),
                'auto_accept_orders' => $this->faker->boolean(60),
                'peak_hours' => [
                    'lunch' => ['start' => '11:30', 'end' => '14:00'],
                    'dinner' => ['start' => '17:30', 'end' => '21:00']
                ]
            ],
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'commission_rate' => $this->faker->randomFloat(2, 5.00, 20.00),
            'is_featured' => $this->faker->boolean(20),
            'verified_at' => $this->faker->boolean(80) ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
            'created_at' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
        ];
    }
}
