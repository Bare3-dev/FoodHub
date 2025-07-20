<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
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
            'menu_category_id' => $this->menu_category_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'prep_time' => $this->prep_time,
            'calories' => $this->calories,
            'ingredients' => $this->ingredients,
            'allergens' => $this->allergens,
            'dietary_tags' => $this->dietary_tags,
            'image_url' => $this->image_url,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'nutritional_info' => $this->nutritional_info,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'category' => new MenuCategoryResource($this->whenLoaded('menuCategory')),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
        ];
    }
}
