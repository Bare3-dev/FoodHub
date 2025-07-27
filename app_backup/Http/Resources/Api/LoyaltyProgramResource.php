<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyProgramResource extends JsonResource
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
            'description' => $this->description,
            'type' => $this->type,
            'starts_at' => $this->whenNotNull($this->starts_at ? $this->starts_at->toDateTimeString() : null),
            'ends_at' => $this->whenNotNull($this->ends_at ? $this->ends_at->toDateTimeString() : null),
            'is_active' => $this->is_active,
            'terms_and_conditions' => $this->terms_and_conditions,
            'rewards_info' => $this->rewards_info,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
            // You might want to include related loyalty tiers, customer loyalty points, etc., here when those resources are created.
        ];
    }
}
