<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
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
            'national_id' => $this->national_id,
            'driver_license_number' => $this->driver_license_number,
            'license_expiry_date' => $this->license_expiry_date,
            'profile_image_url' => $this->profile_image_url,
            'license_image_url' => $this->license_image_url,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_make' => $this->vehicle_make,
            'vehicle_model' => $this->vehicle_model,
            'vehicle_year' => $this->vehicle_year,
            'vehicle_color' => $this->vehicle_color,
            'vehicle_plate_number' => $this->vehicle_plate_number,
            'vehicle_image_url' => $this->vehicle_image_url,
            'status' => $this->status,
            'is_online' => $this->is_online,
            'is_available' => $this->is_available,
            'current_latitude' => $this->current_latitude,
            'current_longitude' => $this->current_longitude,
            'last_location_update' => $this->whenNotNull($this->last_location_update ? $this->last_location_update->toDateTimeString() : null),
            'rating' => $this->rating,
            'total_deliveries' => $this->total_deliveries,
            'completed_deliveries' => $this->completed_deliveries,
            'cancelled_deliveries' => $this->cancelled_deliveries,
            'total_earnings' => $this->total_earnings,
            'documents' => $this->documents,
            'banking_info' => $this->banking_info,
            'email_verified_at' => $this->whenNotNull($this->email_verified_at ? $this->email_verified_at->toDateTimeString() : null),
            'phone_verified_at' => $this->whenNotNull($this->phone_verified_at ? $this->phone_verified_at->toDateTimeString() : null),
            'verified_at' => $this->whenNotNull($this->verified_at ? $this->verified_at->toDateTimeString() : null),
            'last_active_at' => $this->whenNotNull($this->last_active_at ? $this->last_active_at->toDateTimeString() : null),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'working_zones' => DriverWorkingZoneResource::collection($this->whenLoaded('workingZones')),
        ];
    }
}
