<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'cuisine_type' => $this->cuisine_type,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'logo_url' => $this->logo_url,
            'banner_url' => $this->banner_url,
            'average_rating' => $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'is_active' => $this->is_active,
            'delivery_fee' => $this->delivery_fee,
            'minimum_order' => $this->minimum_order,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'settings' => $this->settings,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'branches' => RestaurantBranchResource::collection($this->whenLoaded('branches')),
            'menu_categories' => MenuCategoryResource::collection($this->whenLoaded('menuCategories')),
            'menu_items' => MenuItemResource::collection($this->whenLoaded('menuItems')),
        ];
    }
}
