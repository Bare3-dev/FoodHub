<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 15, 150);
        $taxAmount = $subtotal * 0.15; // 15% tax
        $deliveryFee = $this->faker->randomFloat(2, 0, 8);
        $serviceFee = $this->faker->randomFloat(2, 0, 5);
        $discountAmount = $this->faker->randomFloat(2, 0, 10);
        $totalAmount = $subtotal + $taxAmount + $deliveryFee + $serviceFee - $discountAmount;

        return [
            'order_number' => 'ORD-' . $this->faker->unique()->numberBetween(100000, 999999),
            'customer_id' => Customer::factory(),
            'restaurant_id' => Restaurant::factory(),
            'restaurant_branch_id' => RestaurantBranch::factory(),
            'customer_address_id' => CustomerAddress::factory(),
            'status' => $this->faker->randomElement([
                'pending', 'confirmed', 'preparing', 'ready_for_pickup', 
                'out_for_delivery', 'delivered', 'completed', 'cancelled'
            ]),
            'type' => $this->faker->randomElement(['delivery', 'pickup', 'dine_in']),
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'wallet', 'apple_pay', 'google_pay']),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'service_fee' => $serviceFee,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'currency' => 'SAR',
            'confirmed_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'prepared_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 week', 'now'),
            'picked_up_at' => $this->faker->optional(0.4)->dateTimeBetween('-1 week', 'now'),
            'delivered_at' => $this->faker->optional(0.3)->dateTimeBetween('-1 week', 'now'),
            'cancelled_at' => $this->faker->optional(0.1)->dateTimeBetween('-1 week', 'now'),
            'estimated_preparation_time' => $this->faker->numberBetween(15, 45),
            'estimated_delivery_time' => $this->faker->numberBetween(20, 60),
            'customer_name' => $this->faker->optional()->name(),
            'customer_phone' => $this->faker->optional()->phoneNumber(),
            'delivery_address' => $this->faker->optional()->address(),
            'delivery_notes' => $this->faker->optional()->sentence(),
            'special_instructions' => $this->faker->optional()->sentence(),
            'payment_transaction_id' => $this->faker->optional()->uuid(),
            'payment_data' => [],
            'promo_code' => $this->faker->optional(0.2)->lexify('PROMO???'),
            'loyalty_points_earned' => $this->faker->randomFloat(2, 0, 20),
            'loyalty_points_used' => $this->faker->randomFloat(2, 0, 10),
            'pos_data' => [],
            'cancellation_reason' => $this->faker->optional(0.1)->sentence(),
            'refund_amount' => $this->faker->optional(0.05)->randomFloat(2, 10, 100),
            'refunded_at' => $this->faker->optional(0.05)->dateTimeBetween('-1 week', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
        ];
    }

    /**
     * Indicate that the order is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'payment_status' => 'paid',
            'confirmed_at' => now()->subHours(2),
            'prepared_at' => now()->subHour(),
            'delivered_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Indicate that the order is for delivery.
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'delivery',
            'delivery_fee' => $this->faker->randomFloat(2, 2, 8),
        ]);
    }

    /**
     * Indicate that the order is for pickup.
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pickup',
            'delivery_fee' => 0,
        ]);
    }
} 