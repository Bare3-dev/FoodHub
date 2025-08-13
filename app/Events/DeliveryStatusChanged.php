<?php

namespace App\Events;

use App\Models\OrderAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderAssignment;
    public $previousStatus;
    public $newStatus;
    public $timestamp;
    public $estimatedDeliveryTime;

    /**
     * Create a new event instance.
     */
    public function __construct(OrderAssignment $orderAssignment, string $previousStatus, string $newStatus, ?string $estimatedDeliveryTime = null)
    {
        $this->orderAssignment = $orderAssignment;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->timestamp = now()->toISOString();
        $this->estimatedDeliveryTime = $estimatedDeliveryTime;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Private channel for the customer
            new PrivateChannel('customer.' . $this->orderAssignment->order->customer_id),
            
            // Private channel for the restaurant
            new PrivateChannel('restaurant.' . $this->orderAssignment->order->restaurant_branch_id),
            
            // Private channel for the driver
            new PrivateChannel('driver.' . $this->orderAssignment->driver_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'order_id' => $this->orderAssignment->order_id,
            'order_number' => $this->orderAssignment->order->order_number,
            'driver_id' => $this->orderAssignment->driver_id,
            'driver_name' => $this->orderAssignment->driver->name,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'customer_id' => $this->orderAssignment->order->customer_id,
            'restaurant_branch_id' => $this->orderAssignment->order->restaurant_branch_id,
            'timestamp' => $this->timestamp,
            'version' => '1.0',
        ];

        if ($this->estimatedDeliveryTime) {
            $data['estimated_delivery_time'] = $this->estimatedDeliveryTime;
        }

        // Add status-specific data
        switch ($this->newStatus) {
            case 'picked_up':
                $data['pickup_time'] = now()->toISOString();
                break;
            case 'out_for_delivery':
                $data['out_for_delivery_time'] = now()->toISOString();
                break;
            case 'delivered':
                $data['delivery_time'] = now()->toISOString();
                break;
        }

        return $data;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'delivery.status.changed';
    }
}
