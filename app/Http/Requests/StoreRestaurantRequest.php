<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create', \App\Models\Restaurant::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cuisine_type' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:restaurants',
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
