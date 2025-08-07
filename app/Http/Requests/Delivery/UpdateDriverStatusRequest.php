<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDriverStatusRequest extends FormRequest
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
            'status' => 'nullable|string|in:online,offline,on_break,busy,unavailable',
            'is_online' => 'nullable|boolean',
            'is_available' => 'nullable|boolean',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
            'max_orders' => 'nullable|integer|min:1|max:10',
            'working_hours' => 'nullable|array',
            'working_hours.start_time' => 'nullable|date_format:H:i',
            'working_hours.end_time' => 'nullable|date_format:H:i|after:working_hours.start_time',
            'shift_info' => 'nullable|array',
            'shift_info.shift_type' => 'nullable|string|in:morning,afternoon,evening,night',
            'shift_info.start_time' => 'nullable|date_format:H:i',
            'shift_info.end_time' => 'nullable|date_format:H:i',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: online, offline, on_break, busy, unavailable',
            'current_latitude.between' => 'Latitude must be between -90 and 90',
            'current_longitude.between' => 'Longitude must be between -180 and 180',
            'max_orders.min' => 'Maximum orders must be at least 1',
            'max_orders.max' => 'Maximum orders cannot exceed 10',
            'working_hours.end_time.after' => 'End time must be after start time',
        ];
    }
} 