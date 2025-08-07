<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class AssignOrderRequest extends FormRequest
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
            'order_id' => 'required|exists:orders,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'vehicle_type' => 'nullable|string|in:car,motorcycle,bicycle',
            'max_distance' => 'nullable|numeric|min:0',
            'zone_id' => 'nullable|exists:driver_working_zones,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.exists' => 'The specified order does not exist',
            'driver_id.exists' => 'The specified driver does not exist',
            'priority.in' => 'Priority must be one of: low, normal, high, urgent',
            'vehicle_type.in' => 'Vehicle type must be one of: car, motorcycle, bicycle',
            'max_distance.min' => 'Maximum distance cannot be negative',
            'zone_id.exists' => 'The specified zone does not exist',
        ];
    }
} 