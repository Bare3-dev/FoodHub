<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\RestaurantResource;

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
            'points_per_currency' => $this->points_per_currency,
            'currency_name' => $this->currency_name,
            'minimum_points_redemption' => $this->minimum_points_redemption,
            'redemption_rate' => $this->redemption_rate,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
        ];
    }
}
