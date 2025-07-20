<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverWorkingZoneResource extends JsonResource
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
            'zone_name' => $this->zone_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_km' => $this->radius_km,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'driver' => new DriverResource($this->whenLoaded('driver')),
        ];
    }
}
