<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
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
        // Get the customer address ID from the route parameters for unique validation (if applicable).
        // $customerAddressId = $this->route('customer_address') ? $this->route('customer_address')->id : null;

        return [
            'customer_id' => 'exists:customers,id',
            'address_line_1' => 'string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'postal_code' => 'string|max:20',
            'country' => 'string|max:255',
            'latitude' => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
            'is_default' => 'boolean',
            'address_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ];
    }
}
