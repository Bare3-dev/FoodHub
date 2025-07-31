<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverWorkingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverWorkingZone>
 */
class DriverWorkingZoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DriverWorkingZone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate realistic coordinates for major cities
        $cities = [
            ['lat' => 40.7128, 'lng' => -74.0060, 'name' => 'New York'],
            ['lat' => 34.0522, 'lng' => -118.2437, 'name' => 'Los Angeles'],
            ['lat' => 41.8781, 'lng' => -87.6298, 'name' => 'Chicago'],
            ['lat' => 29.7604, 'lng' => -95.3698, 'name' => 'Houston'],
            ['lat' => 33.4484, 'lng' => -112.0740, 'name' => 'Phoenix'],
        ];
        
        $city = $this->faker->randomElement($cities);
        
        // Add some random variation to coordinates
        $latitude = $city['lat'] + ($this->faker->randomFloat(4, -0.1, 0.1));
        $longitude = $city['lng'] + ($this->faker->randomFloat(4, -0.1, 0.1));
        
        return [
            'driver_id' => Driver::factory(),
            'zone_name' => $city['name'] . ' ' . $this->faker->randomElement(['Downtown', 'Uptown', 'West Side', 'East Side', 'Central']) . ' Zone',
            'zone_description' => $this->faker->sentence(),
            'coordinates' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'radius_km' => $this->faker->randomFloat(2, 1.0, 10.0),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'priority_level' => $this->faker->numberBetween(1, 5),
            'start_time' => $this->faker->optional()->time(),
            'end_time' => $this->faker->optional()->time(),
        ];
    }

    /**
     * Indicate that the zone is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the zone is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a zone with high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority_level' => $this->faker->numberBetween(4, 5),
        ]);
    }

    /**
     * Create a zone with specific coordinates.
     */
    public function withCoordinates(float $latitude, float $longitude, float $radius = 5.0): static
    {
        return $this->state(fn (array $attributes) => [
            'coordinates' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'radius_km' => $radius,
        ]);
    }

    /**
     * Create a zone with working hours.
     */
    public function withWorkingHours(string $startTime = '08:00:00', string $endTime = '18:00:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }
} 