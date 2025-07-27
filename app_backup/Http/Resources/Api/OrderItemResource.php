<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'order_id' => $this->order_id,
            'menu_item_id' => $this->menu_item_id,
            'item_name' => $this->item_name,
            'item_description' => $this->item_description,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'total_price' => $this->total_price,
            'customizations' => $this->customizations,
            'special_instructions' => $this->special_instructions,
            'nutritional_snapshot' => $this->nutritional_snapshot,
            'allergens_snapshot' => $this->allergens_snapshot,
            'sku' => $this->sku,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'menu_item' => new MenuItemResource($this->whenLoaded('menuItem')),
        ];
    }
} 