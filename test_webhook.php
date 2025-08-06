<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;

echo "Creating test order and payment...\n";

try {
    // Create a test customer first
    $customer = Customer::firstOrCreate(
        ['email' => 'test@example.com'],
        [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+966501234567',
            'password' => bcrypt('password123'),
            'status' => 'active'
        ]
    );

    echo "Customer created/found with ID: " . $customer->id . "\n";

    // Create a test restaurant
    $restaurant = Restaurant::firstOrCreate(
        ['slug' => 'test-restaurant'],
        [
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'description' => 'A test restaurant for webhook testing',
            'cuisine_type' => 'International',
            'business_hours' => ['monday' => '09:00-22:00'],
            'status' => 'active'
        ]
    );

    echo "Restaurant created/found with ID: " . $restaurant->id . "\n";

    // Create a test restaurant branch
    $branch = RestaurantBranch::firstOrCreate(
        ['slug' => 'test-branch'],
        [
            'restaurant_id' => $restaurant->id,
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'address' => 'Test Address',
            'city' => 'Riyadh',
            'state' => 'Riyadh',
            'postal_code' => '12345',
            'country' => 'SA',
            'phone' => '+966501234567',
            'operating_hours' => ['monday' => '09:00-22:00'],
            'delivery_zones' => [],
            'status' => 'active'
        ]
    );

    echo "Restaurant Branch created/found with ID: " . $branch->id . "\n";

    // Create a test order
    $order = Order::create([
        'order_number' => 'ORD-' . time(),
        'customer_id' => $customer->id,
        'restaurant_id' => $restaurant->id,
        'restaurant_branch_id' => $branch->id,
        'status' => 'pending',
        'type' => 'delivery',
        'payment_status' => 'pending',
        'subtotal' => 100.00,
        'tax_amount' => 0.00,
        'delivery_fee' => 0.00,
        'service_fee' => 0.00,
        'discount_amount' => 0.00,
        'total_amount' => 100.00,
        'currency' => 'SAR'
    ]);

    echo "Order created with ID: " . $order->id . "\n";

    // Create a test payment
    $payment = Payment::create([
        'order_id' => $order->id,
        'transaction_id' => 'TXN_TEST_' . time(),
        'gateway' => 'mada',
        'status' => 'pending',
        'amount' => 100.00,
        'currency' => 'SAR'
    ]);

    echo "Payment created with ID: " . $payment->id . "\n";
    echo "Transaction ID: " . $payment->transaction_id . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 