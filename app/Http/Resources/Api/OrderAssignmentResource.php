<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'driver_response' => $this->driver_response,
            'response_time' => $this->response_time?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'estimated_pickup_time' => $this->estimated_pickup_time?->toISOString(),
            'estimated_delivery_time' => $this->estimated_delivery_time?->toISOString(),
            'actual_pickup_time' => $this->actual_pickup_time?->toISOString(),
            'actual_delivery_time' => $this->actual_delivery_time?->toISOString(),
            'delivery_notes' => $this->delivery_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'driver' => $this->whenLoaded('driver', function () {
                return new DriverResource($this->driver);
            }),
            'order' => $this->whenLoaded('order', function () {
                return new OrderResource($this->order);
            }),
        ];
    }
} 