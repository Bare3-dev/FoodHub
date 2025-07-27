<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantRequest extends FormRequest
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
        // Get the restaurant ID from the route parameters for unique validation.
        $restaurantId = $this->route('restaurant') ? $this->route('restaurant')->id : null;

        return [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'cuisine_type' => 'string|max:255',
            'phone' => 'string|max:20',
            'email' => 'string|email|max:255|unique:restaurants,email,' . $restaurantId,
            'website' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:255',
            'banner_url' => 'nullable|string|max:255',
            'average_rating' => 'nullable|numeric|min:0|max:5',
            'total_reviews' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'delivery_fee' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'estimated_delivery_time' => 'nullable|integer|min:0',
            'settings' => 'nullable|json',
        ];
    }
}
