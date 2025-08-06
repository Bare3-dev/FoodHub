<?php

namespace Database\Factories;

use App\Models\InventoryReport;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryReport>
 */
class InventoryReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InventoryReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reportTypes = ['daily', 'weekly', 'monthly', 'low_stock', 'turnover'];
        $statuses = ['generated', 'sent', 'archived'];
        
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'report_type' => $this->faker->randomElement($reportTypes),
            'report_date' => $this->faker->date(),
            'report_data' => [
                'restaurant_id' => 1,
                'period' => [
                    'start' => now()->subDay()->toISOString(),
                    'end' => now()->toISOString(),
                ],
                'stock_movements' => [
                    'total_changes' => $this->faker->numberBetween(10, 100),
                    'additions' => $this->faker->numberBetween(5, 50),
                    'reductions' => $this->faker->numberBetween(5, 50),
                ],
                'turnover_rates' => [
                    'item_1' => [
                        'item_name' => 'Sample Item',
                        'turnover_rate' => $this->faker->randomFloat(2, 0.1, 5.0),
                        'consumption' => $this->faker->numberBetween(10, 100),
                        'average_stock' => $this->faker->numberBetween(20, 200),
                    ],
                ],
                'low_stock_alerts' => [
                    [
                        'item_name' => 'Low Stock Item',
                        'branch_name' => 'Main Branch',
                        'current_stock' => $this->faker->numberBetween(1, 5),
                        'min_threshold' => 5,
                        'status' => 'low_stock',
                    ],
                ],
                'out_of_stock_items' => [
                    [
                        'item_name' => 'Out of Stock Item',
                        'branch_name' => 'Main Branch',
                        'status' => 'out_of_stock',
                    ],
                ],
                'summary' => [
                    'total_items' => $this->faker->numberBetween(50, 200),
                    'tracked_items' => $this->faker->numberBetween(30, 150),
                    'low_stock_items' => $this->faker->numberBetween(0, 10),
                    'out_of_stock_items' => $this->faker->numberBetween(0, 5),
                    'tracking_coverage' => $this->faker->randomFloat(2, 50, 100),
                ],
            ],
            'summary' => $this->faker->sentence(),
            'status' => $this->faker->randomElement($statuses),
        ];
    }

    /**
     * Indicate that the report is a daily report.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'daily',
            'report_date' => now()->toDateString(),
        ]);
    }

    /**
     * Indicate that the report is a weekly report.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'weekly',
            'report_date' => now()->startOfWeek()->toDateString(),
        ]);
    }

    /**
     * Indicate that the report is a monthly report.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'monthly',
            'report_date' => now()->startOfMonth()->toDateString(),
        ]);
    }

    /**
     * Indicate that the report is a low stock report.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'low_stock',
        ]);
    }

    /**
     * Indicate that the report is a turnover report.
     */
    public function turnover(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'turnover',
        ]);
    }

    /**
     * Indicate that the report has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
        ]);
    }

    /**
     * Indicate that the report has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }
}
