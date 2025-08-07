<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class DeliveryExceptionRequest extends FormRequest
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
            'exception_type' => 'required|string|in:customer_unavailable,address_not_found,order_quality_issue,delivery_delay,traffic_delay,vehicle_breakdown,weather_delay,security_issue',
            'details' => 'nullable|array',
            'details.description' => 'nullable|string|max:1000',
            'details.delay_minutes' => 'nullable|integer|min:0',
            'details.customer_contact_attempts' => 'nullable|integer|min:0',
            'details.alternative_address' => 'nullable|string|max:500',
            'details.issue_category' => 'nullable|string|max:100',
            'details.severity' => 'nullable|string|in:low,medium,high,critical',
            'details.resolution_attempts' => 'nullable|array',
            'details.resolution_attempts.*.attempt_type' => 'nullable|string|max:100',
            'details.resolution_attempts.*.timestamp' => 'nullable|date',
            'details.resolution_attempts.*.outcome' => 'nullable|string|max:200',
            'details.requires_customer_service' => 'nullable|boolean',
            'details.estimated_resolution_time' => 'nullable|date|after:now',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'exception_type.in' => 'Exception type must be one of the valid types',
            'details.description.max' => 'Description cannot exceed 1000 characters',
            'details.delay_minutes.min' => 'Delay minutes cannot be negative',
            'details.customer_contact_attempts.min' => 'Contact attempts cannot be negative',
            'details.alternative_address.max' => 'Alternative address cannot exceed 500 characters',
            'details.issue_category.max' => 'Issue category cannot exceed 100 characters',
            'details.severity.in' => 'Severity must be one of: low, medium, high, critical',
            'details.estimated_resolution_time.after' => 'Estimated resolution time must be in the future',
        ];
    }
} 