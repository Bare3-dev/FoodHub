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
            'description' => $this->faker->paragraph(3),
            'cuisine_type' => $this->faker->randomElement($cuisineTypes),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'website' => $this->faker->url(),
            'logo_url' => $this->faker->imageUrl(300, 300, 'food'),
            'banner_url' => $this->faker->imageUrl(800, 400, 'restaurant'),
            'average_rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'total_reviews' => $this->faker->numberBetween(10, 1000),
            'is_active' => $this->faker->boolean(90),
            'delivery_fee' => $this->faker->randomFloat(2, 2.99, 9.99),
            'minimum_order' => $this->faker->randomFloat(2, 15.00, 35.00),
            'estimated_delivery_time' => $this->faker->numberBetween(20, 60),
            'settings' => json_encode([
                'accepts_cash' => $this->faker->boolean(80),
                'accepts_card' => true,
                'max_delivery_distance' => $this->faker->numberBetween(5, 15),
                'auto_accept_orders' => $this->faker->boolean(60),
                'peak_hours' => [
                    'lunch' => ['start' => '11:30', 'end' => '14:00'],
                    'dinner' => ['start' => '17:30', 'end' => '21:00']
                ]
            ]),
            'created_at' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
        ];
    }
}
