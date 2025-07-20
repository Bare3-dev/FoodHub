<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'restaurant_id' => $this->restaurant_id,
            'restaurant_branch_id' => $this->restaurant_branch_id,
            'customer_address_id' => $this->customer_address_id,
            'status' => $this->status,
            'type' => $this->type,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'discount_amount' => $this->discount_amount,
            'coupon_code' => $this->coupon_code,
            'notes' => $this->notes,
            'delivery_instructions' => $this->delivery_instructions,
            'scheduled_at' => $this->whenNotNull($this->scheduled_at ? $this->scheduled_at->toDateTimeString() : null),
            'delivered_at' => $this->whenNotNull($this->delivered_at ? $this->delivered_at->toDateTimeString() : null),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
            'branch' => new RestaurantBranchResource($this->whenLoaded('branch')),
            'address' => new CustomerAddressResource($this->whenLoaded('address')),
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
        ];
    }
}
