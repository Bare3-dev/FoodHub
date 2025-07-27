<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverWorkingZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For now, allow all requests. Authorization logic will be fully implemented in Sprint 5.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the driver working zone ID from the route parameters for unique validation (if applicable).
        // $driverWorkingZoneId = $this->route('driver_working_zone') ? $this->route('driver_working_zone')->id : null;

        return [
            'driver_id' => 'exists:drivers,id',
            'zone_name' => 'string|max:255',
            'latitude' => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
            'radius_km' => 'numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
