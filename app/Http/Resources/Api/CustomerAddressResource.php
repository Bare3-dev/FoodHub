<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'address_line_1' => $this->street_address,
            'address_line_2' => $this->apartment_number,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->is_default,
            'address_type' => $this->label,
            'notes' => $this->delivery_notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
