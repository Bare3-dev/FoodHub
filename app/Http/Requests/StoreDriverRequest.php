<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
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
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:drivers',
            'phone' => 'required|string|max:20|unique:drivers',
            'password' => 'required|string|min:8',
            'date_of_birth' => 'required|date',
            'national_id' => 'required|string|max:255|unique:drivers',
            'driver_license_number' => 'required|string|max:255|unique:drivers',
            'license_expiry_date' => 'required|date',
            'profile_image_url' => 'nullable|string|max:255',
            'license_image_url' => 'nullable|string|max:255',
            'vehicle_type' => 'required|string|max:255',
            'vehicle_make' => 'nullable|string|max:255',
            'vehicle_model' => 'nullable|string|max:255',
            'vehicle_year' => 'nullable|string|max:4',
            'vehicle_color' => 'nullable|string|max:255',
            'vehicle_plate_number' => 'required|string|max:255|unique:drivers',
            'vehicle_image_url' => 'nullable|string|max:255',
            'status' => 'required|string|in:pending_verification,active,inactive,suspended,blocked',
            'is_online' => 'boolean',
            'is_available' => 'boolean',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
            'last_location_update' => 'nullable|date',
            'rating' => 'nullable|numeric|min:0|max:5',
            'total_deliveries' => 'nullable|integer|min:0',
            'completed_deliveries' => 'nullable|integer|min:0',
            'cancelled_deliveries' => 'nullable|integer|min:0',
            'total_earnings' => 'nullable|numeric|min:0',
            'verified_at' => 'nullable|date',
            'last_active_at' => 'nullable|date',
            'documents' => 'nullable|json',
            'banking_info' => 'nullable|json',
        ];
    }
}
