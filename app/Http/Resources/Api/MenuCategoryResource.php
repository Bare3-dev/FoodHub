<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\RestaurantResource;
use App\Http\Resources\Api\MenuItemResource;

class MenuCategoryResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
            'menu_items' => MenuItemResource::collection($this->whenLoaded('menuItems')),
        ];
    }
}
