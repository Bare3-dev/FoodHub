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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:points,stamps,tiers,challenges',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'terms_and_conditions' => 'nullable|string',
            'rewards_info' => 'nullable|json',
            // Add validation for fields that tests expect
            'currency_name' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9\s]+$/',
            'points_per_currency' => 'nullable|numeric|min:0.01|max:1000',
            'minimum_points_redemption' => 'nullable|integer|min:1',
            'redemption_rate' => 'nullable|numeric|min:0.001|max:1',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Map the test field names to actual database field names
        $this->merge([
            'points_per_dollar' => $this->input('points_per_currency'),
            'dollar_per_point' => $this->input('redemption_rate'),
            'minimum_spend_for_points' => $this->input('minimum_points_redemption'),
            'start_date' => $this->input('starts_at'),
            'end_date' => $this->input('ends_at'),
        ]);
    }
}
