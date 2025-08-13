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

class NewOrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Private channel for the restaurant
            new PrivateChannel('restaurant.' . $this->order->restaurant_branch_id),
            
            // Private channel for the customer
            new PrivateChannel('customer.' . $this->order->customer_id),
            
            // Kitchen display channel
            new PrivateChannel('kitchen.' . $this->order->restaurant_branch_id),
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
            'customer_id' => $this->order->customer_id,
            'customer_name' => $this->order->customer->name,
            'restaurant_branch_id' => $this->order->restaurant_branch_id,
            'total_amount' => $this->order->total_amount,
            'order_type' => $this->order->order_type,
            'estimated_preparation_time' => $this->order->estimated_preparation_time,
            'items_count' => $this->order->orderItems->count(),
            'special_instructions' => $this->order->special_instructions,
            'timestamp' => $this->timestamp,
            'version' => '1.0',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.new.placed';
    }
}
