<?php

namespace App\Events;

use App\Models\Driver;
use App\Models\OrderAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $driver;
    public $location;
    public $orderAssignment;
    public $timestamp;
    public $eta;

    /**
     * Create a new event instance.
     */
    public function __construct(Driver $driver, array $location, ?OrderAssignment $orderAssignment = null, ?string $eta = null)
    {
        $this->driver = $driver;
        $this->location = $location;
        $this->orderAssignment = $orderAssignment;
        $this->timestamp = now()->toISOString();
        $this->eta = $eta;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Private channel for the driver
            new PrivateChannel('driver.' . $this->driver->id),
        ];

        // If there's an active order assignment, broadcast to relevant parties
        if ($this->orderAssignment) {
            $channels[] = new PrivateChannel('customer.' . $this->orderAssignment->order->customer_id);
            $channels[] = new PrivateChannel('restaurant.' . $this->orderAssignment->order->restaurant_branch_id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'driver_id' => $this->driver->id,
            'driver_name' => $this->driver->name,
            'location' => [
                'latitude' => $this->location['latitude'],
                'longitude' => $this->location['longitude'],
                'accuracy' => $this->location['accuracy'] ?? null,
                'speed' => $this->location['speed'] ?? null,
                'heading' => $this->location['heading'] ?? null,
            ],
            'timestamp' => $this->timestamp,
            'version' => '1.0',
        ];

        if ($this->orderAssignment) {
            $data['order_id'] = $this->orderAssignment->order_id;
            $data['order_number'] = $this->orderAssignment->order->order_number;
        }

        if ($this->eta) {
            $data['eta'] = $this->eta;
        }

        return $data;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }
}
