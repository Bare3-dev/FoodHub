<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

final class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();
        $restaurants = Restaurant::all();

        foreach ($customers as $customer) {
            $address = $customer->addresses()->inRandomOrder()->first();
            if (! $address) {
                continue; // Skip if customer has no address
            }

            // Create 3-5 orders per customer
            for ($i = 0; $i < mt_rand(3, 5); $i++) {
                $restaurant = $restaurants->random();
                $branch = $restaurant->branches()->inRandomOrder()->first();
                if (! $branch) {
                    continue; // Skip if restaurant has no branches
                }

                $menuItems = MenuItem::where('restaurant_id', $restaurant->id)->inRandomOrder()->take(mt_rand(1, 3))->get();
                if ($menuItems->isEmpty()) {
                    continue; // Skip if no menu items for this restaurant
                }

                $subtotal = $menuItems->sum(fn ($item) => $item->price);
                $deliveryFee = $branch->delivery_fee;
                $totalAmount = $subtotal + $deliveryFee + ($subtotal * 0.05); // 5% tax

                $order = Order::create([
                    'order_number' => 'ORD-' . Str::upper(Str::random(8)),
                    'customer_id' => $customer->id,
                    'restaurant_id' => $restaurant->id,
                    'restaurant_branch_id' => $branch->id,
                    'customer_address_id' => $address->id,
                    'status' => Arr::random(['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'completed']),
                    'type' => 'delivery',
                    'payment_status' => Arr::random(['pending', 'paid']),
                    'payment_method' => Arr::random(['cash', 'card']),
                    'subtotal' => $subtotal,
                    'tax_amount' => $subtotal * 0.05,
                    'delivery_fee' => $deliveryFee,
                    'service_fee' => 0.00,
                    'discount_amount' => 0.00,
                    'total_amount' => $totalAmount,
                    'currency' => 'SAR',
                    'confirmed_at' => now(),
                    'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                    'customer_phone' => $customer->phone,
                    'delivery_address' => $address->street_address . ', ' . $address->city,
                    'estimated_preparation_time' => mt_rand(15, 30),
                    'estimated_delivery_time' => mt_rand(30, 60),
                ]);

                foreach ($menuItems as $menuItem) {
                    $order->items()->create([
                        'menu_item_id' => $menuItem->id,
                        'item_name' => $menuItem->name,
                        'unit_price' => $menuItem->price,
                        'quantity' => mt_rand(1, 2),
                        'total_price' => $menuItem->price * mt_rand(1, 2),
                        'customizations' => [],
                    ]);
                }
            }
        }

        $this->command->info('Successfully seeded Order and Order Item data.');
    }
}
