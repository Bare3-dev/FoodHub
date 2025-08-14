<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    // Order Status Notifications
    public function notifyOrderStatusUpdate(Order $order, string $status): bool
    {
        $customer = $order->customer;
        if (!$customer) {
            Log::warning("No customer found for order {$order->id}");
            return false;
        }

        $data = [
            'type' => 'order_status_update',
            'order_id' => $order->id,
            'status' => $status,
            'restaurant_name' => $order->restaurantBranch->restaurant->name ?? 'Restaurant',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'Order Update',
            'body' => $this->getOrderStatusMessage($status, $order),
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('customer', $customer->id, $data, $notification);
    }

    public function notifyNewOrder(Order $order): bool
    {
        $restaurant = $order->restaurantBranch->restaurant;
        if (!$restaurant) {
            Log::warning("No restaurant found for order {$order->id}");
            return false;
        }

        $data = [
            'type' => 'new_order',
            'order_id' => $order->id,
            'customer_name' => $order->customer->name ?? 'Customer',
            'total_amount' => $order->total_amount,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'New Order Received',
            'body' => "New order #{$order->id} from " . ($order->customer->name ?? 'Customer'),
            'sound' => 'default',
            'badge' => '1',
        ];

        // Send to restaurant staff (assuming user type 'user' for restaurant staff)
        return $this->fcmService->sendToUserType('user', $restaurant->id, $data, $notification);
    }

    public function notifyOrderReady(Order $order): bool
    {
        $customer = $order->customer;
        if (!$customer) {
            return false;
        }

        $data = [
            'type' => 'order_ready',
            'order_id' => $order->id,
            'restaurant_name' => $order->restaurantBranch->restaurant->name ?? 'Restaurant',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'Order Ready!',
            'body' => "Your order #{$order->id} is ready for pickup",
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('customer', $customer->id, $data, $notification);
    }

    public function notifyOrderOutForDelivery(Order $order): bool
    {
        $customer = $order->customer;
        if (!$customer) {
            return false;
        }

        $data = [
            'type' => 'order_out_for_delivery',
            'order_id' => $order->id,
            'driver_name' => $order->driver ? $order->driver->name : 'Driver',
            'estimated_delivery_time' => now()->addMinutes(30)->toISOString(),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'Order Out for Delivery',
            'body' => "Your order #{$order->id} is on its way!",
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('customer', $customer->id, $data, $notification);
    }

    public function notifyOrderDelivered(Order $order): bool
    {
        $customer = $order->customer;
        if (!$customer) {
            return false;
        }

        $data = [
            'type' => 'order_delivered',
            'order_id' => $order->id,
            'restaurant_name' => $order->restaurantBranch->restaurant->name ?? 'Restaurant',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'Order Delivered',
            'body' => "Your order #{$order->id} has been delivered. Enjoy!",
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('customer', $customer->id, $data, $notification);
    }

    // Driver Notifications
    public function notifyDriverNewAssignment(Order $order): bool
    {
        if (!$order->driver) {
            return false;
        }

        $data = [
            'type' => 'new_delivery_assignment',
            'order_id' => $order->id,
            'pickup_address' => $order->restaurantBranch->address ?? 'Pickup address',
            'delivery_address' => $order->delivery_address ?? 'Delivery address',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'New Delivery Assignment',
            'body' => "You have a new delivery assignment for order #{$order->id}",
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('driver', $order->driver->id, $data, $notification);
    }

    // Kitchen Notifications
    public function notifyKitchenNewOrder(Order $order): bool
    {
        $restaurant = $order->restaurantBranch->restaurant;
        if (!$restaurant) {
            return false;
        }

        $data = [
            'type' => 'kitchen_new_order',
            'order_id' => $order->id,
            'customer_name' => $order->customer->name ?? 'Customer',
            'items_count' => $order->orderItems->count(),
            'priority' => $order->priority ?? 'normal',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'New Kitchen Order',
            'body' => "Order #{$order->id} - {$order->orderItems->count()} items",
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('user', $restaurant->id, $data, $notification);
    }

    // Promotional Notifications
    public function sendPromotionalNotification(string $title, string $body, array $data = []): bool
    {
        $notificationData = array_merge([
            'type' => 'promotional',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], $data);

        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToAllCustomers($notificationData, $notification);
    }

    // Test Notification
    public function sendTestNotification(string $userType, int $userId): bool
    {
        $data = [
            'type' => 'test',
            'timestamp' => now()->toISOString(),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $notification = [
            'title' => 'Test Notification',
            'body' => 'This is a test notification from your app',
            'sound' => 'default',
        ];

        return $this->fcmService->sendToUserType($userType, $userId, $data, $notification);
    }

    public function sendToCustomer(int $customerId, string $title, string $body, array $data = []): bool
    {
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1',
        ];

        return $this->fcmService->sendToUserType('customer', $customerId, $data, $notification);
    }

    private function getOrderStatusMessage(string $status, Order $order): string
    {
        return match ($status) {
            'confirmed' => "Order #{$order->id} has been confirmed",
            'preparing' => "Order #{$order->id} is being prepared",
            'ready' => "Order #{$order->id} is ready for pickup",
            'out_for_delivery' => "Order #{$order->id} is out for delivery",
            'delivered' => "Order #{$order->id} has been delivered",
            'cancelled' => "Order #{$order->id} has been cancelled",
            default => "Order #{$order->id} status updated to {$status}",
        };
    }
}
