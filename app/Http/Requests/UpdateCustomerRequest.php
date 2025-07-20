<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        // Get the customer ID from the route parameters for unique validation.
        $customerId = $this->route('customer') ? $this->route('customer')->id : null;

        return [
            'first_name' => 'string|max:255',
            'last_name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:customers,email,' . $customerId,
            'phone' => 'string|max:20|unique:customers,phone,' . $customerId,
            'password' => 'nullable|string|min:8',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'preferences' => 'nullable|json',
            'marketing_emails_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
        ];
    }
}
