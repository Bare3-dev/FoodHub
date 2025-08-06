<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
final class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'transaction_id' => $this->faker->unique()->uuid,
            'gateway' => $this->faker->randomElement(['mada', 'stc_pay', 'apple_pay', 'google_pay']),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'SAR',
            'paid_amount' => null,
            'paid_at' => null,
            'gateway_response' => [
                'transaction_id' => $this->faker->uuid,
                'status' => 'success',
                'timestamp' => now()->toISOString(),
            ],
            'error_message' => null,
            'payment_method' => $this->faker->randomElement(['card', 'wallet', 'bank_transfer']),
            'metadata' => [
                'customer_ip' => $this->faker->ipv4,
                'user_agent' => $this->faker->userAgent,
                'merchant_reference' => $this->faker->unique()->numerify('REF-#####'),
            ],
        ];
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_amount' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the payment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'paid_amount' => $attributes['amount'],
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_amount' => null,
            'paid_at' => null,
            'error_message' => $this->faker->sentence,
        ]);
    }

    /**
     * Indicate that the payment was refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'paid_amount' => $attributes['amount'],
            'paid_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the payment is from MADA gateway.
     */
    public function mada(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'mada',
            'payment_method' => 'card',
        ]);
    }

    /**
     * Indicate that the payment is from STC Pay gateway.
     */
    public function stcPay(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'stc_pay',
            'payment_method' => 'wallet',
        ]);
    }

    /**
     * Indicate that the payment is from Apple Pay gateway.
     */
    public function applePay(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'apple_pay',
            'payment_method' => 'wallet',
        ]);
    }

    /**
     * Indicate that the payment is from Google Pay gateway.
     */
    public function googlePay(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'google_pay',
            'payment_method' => 'wallet',
        ]);
    }
} 