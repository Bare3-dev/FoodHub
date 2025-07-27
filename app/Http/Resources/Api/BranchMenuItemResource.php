<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchMenuItemResource extends JsonResource
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
            'restaurant_branch_id' => $this->restaurant_branch_id,
            'menu_item_id' => $this->menu_item_id,
            'price_override' => $this->price,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_recommended,
            'stock_quantity' => $this->stock_quantity,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'branch' => new RestaurantBranchResource($this->whenLoaded('branch')),
            'menu_item' => new MenuItemResource($this->whenLoaded('menuItem')),
        ];
    }
}
