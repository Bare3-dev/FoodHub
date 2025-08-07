<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class LocationUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:100',
            'speed' => 'nullable|numeric|min:0|max:200',
            'heading' => 'nullable|numeric|min:0|max:360',
            'altitude' => 'nullable|numeric|min:-1000|max:10000',
            'timestamp' => 'nullable|date',
            'metadata' => 'nullable|array',
            'metadata.battery_level' => 'nullable|numeric|min:0|max:100',
            'metadata.signal_strength' => 'nullable|numeric|min:0|max:100',
            'metadata.device_id' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'accuracy.min' => 'Accuracy cannot be negative',
            'accuracy.max' => 'Accuracy cannot exceed 100',
            'speed.min' => 'Speed cannot be negative',
            'speed.max' => 'Speed cannot exceed 200 km/h',
            'heading.min' => 'Heading must be between 0 and 360 degrees',
            'heading.max' => 'Heading must be between 0 and 360 degrees',
            'altitude.min' => 'Altitude must be between -1000 and 10000 meters',
            'altitude.max' => 'Altitude must be between -1000 and 10000 meters',
            'metadata.battery_level.min' => 'Battery level cannot be negative',
            'metadata.battery_level.max' => 'Battery level cannot exceed 100%',
            'metadata.signal_strength.min' => 'Signal strength cannot be negative',
            'metadata.signal_strength.max' => 'Signal strength cannot exceed 100%',
        ];
    }
} 