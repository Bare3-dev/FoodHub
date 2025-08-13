<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $updateType;
    public $timestamp;
    public $priority;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, string $updateType, ?int $priority = null)
    {
        $this->order = $order;
        $this->updateType = $updateType;
        $this->timestamp = now()->toISOString();
        $this->priority = $priority ?? $this->calculatePriority($order);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Kitchen display channel for the restaurant
            new PrivateChannel('kitchen.' . $this->order->restaurant_branch_id),
            
            // Restaurant management channel
            new PrivateChannel('restaurant.' . $this->order->restaurant_branch_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer->name,
            'update_type' => $this->updateType,
            'priority' => $this->priority,
            'status' => $this->order->status,
            'order_type' => $this->order->order_type,
            'total_amount' => $this->order->total_amount,
            'estimated_preparation_time' => $this->order->estimated_preparation_time,
            'items' => $this->order->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->menuItem->name,
                    'quantity' => $item->quantity,
                    'special_instructions' => $item->special_instructions,
                    'preparation_status' => $item->preparation_status ?? 'pending'
                ];
            }),
            'special_instructions' => $this->order->special_instructions,
            'created_at' => $this->order->created_at->toISOString(),
            'timestamp' => $this->timestamp,
            'version' => '1.0',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'kitchen.order.updated';
    }

    /**
     * Calculate order priority based on various factors
     */
    private function calculatePriority(Order $order): int
    {
        $priority = 1; // Base priority

        // Higher priority for delivery orders
        if ($order->order_type === 'delivery') {
            $priority += 2;
        }

        // Higher priority for orders with special instructions
        if ($order->special_instructions) {
            $priority += 1;
        }

        // Higher priority for orders with many items
        if ($order->orderItems->count() > 5) {
            $priority += 1;
        }

        // Higher priority for orders placed during peak hours
        $hour = $order->created_at->hour;
        if (($hour >= 11 && $hour <= 14) || ($hour >= 17 && $hour <= 20)) {
            $priority += 1;
        }

        return min($priority, 5); // Cap at priority 5
    }
}
