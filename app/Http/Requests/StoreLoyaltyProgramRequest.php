<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoyaltyProgramRequest extends FormRequest
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
            'restaurant_id' => 'required|exists:restaurants,id',
            'name' => 'required|string|max:255|unique:loyalty_programs,name,NULL,id,restaurant_id,' . $this->input('restaurant_id'),
            'description' => 'nullable|string',
            'type' => 'required|string|in:points,stamps,tiers,challenges',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'terms_and_conditions' => 'nullable|string',
            'rewards_info' => 'nullable|json',
            // Add validation for fields that tests expect
            'currency_name' => 'required|string|max:50|regex:/^[a-zA-Z0-9\s]+$/',
            'points_per_currency' => 'required|numeric|min:0.01|max:1000',
            'minimum_points_redemption' => 'required|integer|min:1',
            'redemption_rate' => 'required|numeric|min:0.001|max:1',
        ];
    }


}
