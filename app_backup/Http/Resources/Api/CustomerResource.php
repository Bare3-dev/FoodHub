<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'email_verified_at' => $this->whenNotNull($this->email_verified_at ? $this->email_verified_at->toDateTimeString() : null),
            'phone_verified_at' => $this->whenNotNull($this->phone_verified_at ? $this->phone_verified_at->toDateTimeString() : null),
            'status' => $this->status,
            'marketing_emails_enabled' => $this->marketing_emails_enabled,
            'sms_notifications_enabled' => $this->sms_notifications_enabled,
            'push_notifications_enabled' => $this->push_notifications_enabled,
            'preferences' => $this->preferences,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}
