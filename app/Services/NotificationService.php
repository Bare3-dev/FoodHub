<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\BranchMenuItem;
use App\Models\Restaurant;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Notification Service
 * 
 * Handles in-app notifications for inventory management,
 * low stock alerts, and system events.
 */
class NotificationService
{
    /**
     * Create a low stock notification
     */
    public function createLowStockNotification(BranchMenuItem $branchMenuItem, User $user): Notification
    {
        $urgency = $this->determineLowStockUrgency($branchMenuItem);
        
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'low_stock',
            'title' => 'Low Stock Alert',
            'message' => "Item '{$branchMenuItem->menuItem->name}' at {$branchMenuItem->branch->name} is running low on stock. Current quantity: {$branchMenuItem->stock_quantity}",
            'data' => [
                'branch_menu_item_id' => $branchMenuItem->id,
                'item_name' => $branchMenuItem->menuItem->name,
                'branch_name' => $branchMenuItem->branch->name,
                'current_stock' => $branchMenuItem->stock_quantity,
                'min_threshold' => $branchMenuItem->min_stock_threshold,
                'reorder_suggestion' => $branchMenuItem->getReorderSuggestion(),
                'urgency' => $urgency,
            ],
            'priority' => $this->getPriorityFromUrgency($urgency),
            'category' => 'inventory',
            'action_url' => "/inventory/restock/{$branchMenuItem->id}",
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Create out of stock notification
     */
    public function createOutOfStockNotification(BranchMenuItem $branchMenuItem, User $user): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'out_of_stock',
            'title' => 'Out of Stock Alert',
            'message' => "Item '{$branchMenuItem->menuItem->name}' at {$branchMenuItem->branch->name} is completely out of stock.",
            'data' => [
                'branch_menu_item_id' => $branchMenuItem->id,
                'item_name' => $branchMenuItem->menuItem->name,
                'branch_name' => $branchMenuItem->branch->name,
                'reorder_suggestion' => $branchMenuItem->getReorderSuggestion(),
            ],
            'priority' => 'critical',
            'category' => 'inventory',
            'action_url' => "/inventory/restock/{$branchMenuItem->id}",
            'expires_at' => now()->addDays(3),
        ]);
    }

    /**
     * Create inventory sync notification
     */
    public function createInventorySyncNotification(Restaurant $restaurant, User $user, string $status, ?string $error = null): Notification
    {
        $title = $status === 'success' ? 'Inventory Sync Completed' : 'Inventory Sync Failed';
        $message = $status === 'success' 
            ? "Inventory synchronization completed successfully for {$restaurant->name}"
            : "Inventory synchronization failed for {$restaurant->name}: {$error}";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'inventory_sync',
            'title' => $title,
            'message' => $message,
            'data' => [
                'restaurant_id' => $restaurant->id,
                'restaurant_name' => $restaurant->name,
                'status' => $status,
                'error' => $error,
            ],
            'priority' => $status === 'success' ? 'low' : 'high',
            'category' => 'inventory',
            'action_url' => "/inventory/sync/{$restaurant->id}",
            'expires_at' => now()->addDays(1),
        ]);
    }

    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications(User $user, int $limit = 50): Collection
    {
        return Notification::where('user_id', $user->id)
            ->unread()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Delete expired notifications
     */
    public function deleteExpiredNotifications(): int
    {
        return Notification::where('expires_at', '<', now())->delete();
    }

    /**
     * Get notification count for user
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->unread()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    /**
     * Determine urgency level for low stock
     */
    private function determineLowStockUrgency(BranchMenuItem $branchMenuItem): string
    {
        $percentage = ($branchMenuItem->stock_quantity / $branchMenuItem->min_stock_threshold) * 100;
        
        if ($percentage <= 25) {
            return 'critical';
        } elseif ($percentage <= 50) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Get priority from urgency level
     */
    private function getPriorityFromUrgency(string $urgency): string
    {
        return match($urgency) {
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            default => 'low',
        };
    }

    /**
     * Bulk create low stock notifications for restaurant
     */
    public function createBulkLowStockNotifications(Restaurant $restaurant, User $user): int
    {
        $lowStockItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->where('track_inventory', true)
        ->where('stock_quantity', '>', 0)
        ->where('stock_quantity', '<=', DB::raw('min_stock_threshold'))
        ->with(['menuItem', 'branch'])
        ->get();

        $count = 0;
        foreach ($lowStockItems as $item) {
            $this->createLowStockNotification($item, $user);
            $count++;
        }

        return $count;
    }

    /**
     * Send webhook failure alert notification
     */
    public function sendWebhookFailureAlert(string $service, string $event, array $payload, string $errorMessage): void
    {
        // Log the webhook failure for monitoring
        Log::error('Webhook failure alert', [
            'service' => $service,
            'event' => $event,
            'payload' => $payload,
            'error' => $errorMessage,
            'timestamp' => now()->toISOString(),
        ]);

        // Find admin users to notify
        $adminUsers = User::where('role', 'SUPER_ADMIN')->get();

        foreach ($adminUsers as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'webhook_failure',
                'title' => 'Webhook Processing Failed',
                'message' => "Webhook processing failed for {$service} service. Event: {$event}. Error: {$errorMessage}",
                'data' => [
                    'service' => $service,
                    'event' => $event,
                    'payload' => $payload,
                    'error' => $errorMessage,
                    'timestamp' => now()->toISOString(),
                ],
                'priority' => 'critical',
                'category' => 'system',
                'action_url' => "/admin/webhooks/logs",
                'expires_at' => now()->addDays(1),
            ]);
        }
    }

    /**
     * Send payment confirmation notification
     */
    public function sendPaymentConfirmation(Order $order): void
    {
        // Log payment confirmation
        Log::info('Payment confirmation sent', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'amount' => $order->total_amount
        ]);

        // Create notification for customer
        Notification::create([
            'type' => 'payment_confirmation',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $order->customer_id,
            'data' => [
                'title' => 'Payment Confirmed',
                'message' => "Your payment of {$order->currency} {$order->total_amount} has been confirmed for order #{$order->order_number}",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'priority' => 'high',
                'category' => 'payment',
                'action_url' => "/orders/{$order->id}",
            ],
        ]);
    }

    /**
     * Send order confirmation notification
     */
    public function sendOrderConfirmation(Order $order): void
    {
        // Log order confirmation
        Log::info('Order confirmation sent', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id
        ]);

        // Create notification for customer
        Notification::create([
            'type' => 'order_confirmation',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $order->customer_id,
            'data' => [
                'title' => 'Order Confirmed',
                'message' => "Your order #{$order->order_number} has been confirmed and is being prepared",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'priority' => 'high',
                'category' => 'order',
                'action_url' => "/orders/{$order->id}",
            ],
        ]);
    }

    /**
     * Send payment failure notification
     */
    public function sendPaymentFailureNotification(Order $order, string $errorMessage): void
    {
        // Log payment failure
        Log::warning('Payment failure notification sent', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'error' => $errorMessage
        ]);

        // Create notification for customer
        Notification::create([
            'type' => 'payment_failure',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $order->customer_id,
            'data' => [
                'title' => 'Payment Failed',
                'message' => "Payment failed for order #{$order->order_number}. Please try again or contact support.",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $errorMessage,
                'priority' => 'critical',
                'category' => 'payment',
                'action_url' => "/orders/{$order->id}/payment",
            ],
        ]);
    }

    /**
     * Send refund notification
     */
    public function sendRefundNotification(Order $order, float $refundAmount): void
    {
        // Log refund notification
        Log::info('Refund notification sent', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'refund_amount' => $refundAmount
        ]);

        // Create notification for customer
        Notification::create([
            'type' => 'refund',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $order->customer_id,
            'data' => [
                'title' => 'Refund Processed',
                'message' => "A refund of {$order->currency} {$refundAmount} has been processed for order #{$order->order_number}",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'refund_amount' => $refundAmount,
                'currency' => $order->currency,
                'priority' => 'medium',
                'category' => 'payment',
                'action_url' => "/orders/{$order->id}",
            ],
        ]);
    }
} 