<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Get the driver working zone ID from the route parameters for unique validation
        $driverWorkingZoneId = $this->route('driver_working_zone') ? $this->route('driver_working_zone')->id : null;

        return [
            'driver_id' => 'exists:drivers,id',
            'zone_name' => [
                'string',
                'max:255',
                Rule::unique('driver_working_zones')->where(function ($query) {
                    return $query->where('driver_id', $this->input('driver_id'));
                })->ignore($driverWorkingZoneId)
            ],
            'zone_description' => 'nullable|string|max:1000',
            'latitude' => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
            'radius_km' => 'numeric|min:0.1|max:100',
            'is_active' => 'boolean',
            'priority_level' => 'nullable|integer|min:1|max:10',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after:start_time',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'zone_name.unique' => 'A zone with this name already exists for this driver.',
            'radius_km.min' => 'Zone radius must be at least 0.1 km.',
            'radius_km.max' => 'Zone radius cannot exceed 100 km.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Add custom validation for coordinates only if they are invalid
            if ($this->has('latitude') && $this->has('longitude')) {
                $lat = $this->input('latitude');
                $lng = $this->input('longitude');
                
                // Check if coordinates are within valid ranges
                if (!is_numeric($lat) || $lat < -90 || $lat > 90 || 
                    !is_numeric($lng) || $lng < -180 || $lng > 180) {
                    $validator->errors()->add('coordinates', 'Invalid coordinates provided.');
                }
            }
        });
    }
}
