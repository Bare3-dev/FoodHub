<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantBranchResource extends JsonResource
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
            'restaurant_id' => $this->restaurant_id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'email' => $this->email,
            'opening_hours' => $this->opening_hours,
            'delivery_zones' => $this->delivery_zones,
            'is_active' => $this->is_active,
            'capacity' => $this->capacity,
            'features' => $this->features,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
            'menu_items' => BranchMenuItemResource::collection($this->whenLoaded('menuItems')),
            'branch_menu_items' => BranchMenuItemResource::collection($this->whenLoaded('branchMenuItems')),
        ];
    }
}
