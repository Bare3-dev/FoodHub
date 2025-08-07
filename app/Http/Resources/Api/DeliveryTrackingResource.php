<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryTrackingResource extends JsonResource
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
            'order_assignment_id' => $this->order_assignment_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'altitude' => $this->altitude,
            'timestamp' => $this->timestamp?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'driver' => $this->whenLoaded('driver', function () {
                return new DriverResource($this->driver);
            }),
            'order_assignment' => $this->whenLoaded('orderAssignment', function () {
                return new OrderAssignmentResource($this->orderAssignment);
            }),
        ];
    }
} 