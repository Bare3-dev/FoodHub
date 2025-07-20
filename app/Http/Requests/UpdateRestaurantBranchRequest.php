<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantBranchRequest extends FormRequest
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
        // Get the restaurant branch ID from the route parameters for unique validation.
        $restaurantBranchId = $this->route('restaurant_branch') ? $this->route('restaurant_branch')->id : null;

        return [
            'restaurant_id' => 'exists:restaurants,id',
            'name' => 'string|max:255',
            'address' => 'string|max:255',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'postal_code' => 'string|max:20',
            'country' => 'string|max:255',
            'latitude' => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
            'phone' => 'string|max:20',
            'email' => 'nullable|string|email|max:255|unique:restaurant_branches,email,' . $restaurantBranchId,
            'opening_hours' => 'nullable|json',
            'delivery_zones' => 'nullable|json',
            'is_active' => 'boolean',
            'capacity' => 'nullable|integer|min:0',
            'features' => 'nullable|json',
        ];
    }
}
