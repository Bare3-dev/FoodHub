<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'email' => 'required|string|email|max:255|unique:customers',
            'phone' => 'required|string|max:20|unique:customers',
            'password' => 'required|string|min:8',
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
