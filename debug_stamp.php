<?php

require_once 'vendor/autoload.php';

// Set environment variables
putenv('DB_CONNECTION=pgsql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=5432');
putenv('DB_DATABASE=foodhub');
putenv('DB_USERNAME=postgres');
putenv('DB_PASSWORD=12345');

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\StampCard;
use App\Services\StampCardService;

try {
    echo "Testing Stamp Card Service...\n";
    
    // Create test data
    $customer = Customer::first();
    $loyaltyProgram = LoyaltyProgram::first();
    $restaurant = Restaurant::first();
    
    if (!$customer || !$loyaltyProgram || !$restaurant) {
        echo "No customer, loyalty program, or restaurant found. Creating test data...\n";
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $restaurant = Restaurant::factory()->create();
    }
    
    echo "Customer ID: " . $customer->id . "\n";
    echo "Loyalty Program ID: " . $loyaltyProgram->id . "\n";
    echo "Restaurant ID: " . $restaurant->id . "\n";
    
    // Create menu category and item
    $category = MenuCategory::create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Beverages',
        'description' => 'Drinks and beverages',
        'is_active' => true,
    ]);
    
    $menuItem = MenuItem::create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Coffee',
        'slug' => 'coffee-' . time(),
        'description' => 'Hot coffee',
        'price' => 5.00,
        'is_available' => true,
    ]);
    
    echo "Created category ID: " . $category->id . "\n";
    echo "Created menu item ID: " . $menuItem->id . "\n";
    
    // Create a stamp card
    $stampCard = StampCard::create([
        'customer_id' => $customer->id,
        'loyalty_program_id' => $loyaltyProgram->id,
        'card_type' => 'general',
        'stamps_required' => 10,
        'stamps_earned' => 0,
        'is_completed' => false,
        'is_active' => true,
        'reward_description' => 'Free item',
        'reward_value' => 10.00,
    ]);
    
    echo "Created stamp card ID: " . $stampCard->id . "\n";
    
    // Create an order
    $order = Order::create([
        'order_number' => 'TEST-' . time(),
        'customer_id' => $customer->id,
        'restaurant_id' => $restaurant->id,
        'restaurant_branch_id' => 1,
        'status' => 'completed',
        'type' => 'delivery',
        'payment_status' => 'paid',
        'subtotal' => 25.00,
        'total_amount' => 25.00,
    ]);
    
    echo "Created order ID: " . $order->id . "\n";
    
    // Create order items
    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'menu_item_id' => $menuItem->id,
        'item_name' => $menuItem->name,
        'quantity' => 2,
        'unit_price' => 5.00,
        'total_price' => 10.00,
        'notes' => 'Test order item',
    ]);
    
    echo "Created order item ID: " . $orderItem->id . "\n";
    
    // Test the service
    $stampCardService = new StampCardService();
    
    echo "Active stamp cards before: " . StampCard::where('customer_id', $customer->id)->where('is_active', true)->where('is_completed', false)->count() . "\n";
    echo "Order items count: " . $order->items()->count() . "\n";
    
    $stampCardService->addStampToCard($order);
    
    $stampCard->refresh();
    echo "Stamps earned after: " . $stampCard->stamps_earned . "\n";
    echo "Is completed: " . ($stampCard->is_completed ? 'Yes' : 'No') . "\n";
    
    echo "Test completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 