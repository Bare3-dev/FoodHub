<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class DriverResponseRequest extends FormRequest
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
            'response' => 'required|string|in:accepted,rejected',
            'reason' => 'nullable|string|max:500',
            'estimated_pickup_time' => 'nullable|date|after:now',
            'estimated_delivery_time' => 'nullable|date|after:estimated_pickup_time',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'response.in' => 'Response must be either accepted or rejected',
            'reason.max' => 'Reason cannot exceed 500 characters',
            'estimated_pickup_time.after' => 'Estimated pickup time must be in the future',
            'estimated_delivery_time.after' => 'Estimated delivery time must be after pickup time',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }
} 