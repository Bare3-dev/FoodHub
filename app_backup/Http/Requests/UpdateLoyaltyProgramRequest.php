<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoyaltyProgramRequest extends FormRequest
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
        // Get the loyalty program ID from the route parameters for unique validation (if applicable).
        // $loyaltyProgramId = $this->route('loyalty_program') ? $this->route('loyalty_program')->id : null;

        return [
            'restaurant_id' => 'exists:restaurants,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => 'string|in:points,stamps,tiers',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'terms_and_conditions' => 'nullable|string',
            'rewards_info' => 'nullable|json',
        ];
    }
}
