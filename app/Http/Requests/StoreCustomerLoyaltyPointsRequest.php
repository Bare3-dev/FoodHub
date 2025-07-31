<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerLoyaltyPointsRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'loyalty_program_id' => [
                'required',
                'integer',
                'exists:loyalty_programs,id',
            ],
            'current_points' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'total_points_earned' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'total_points_redeemed' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'total_points_expired' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'last_points_earned_date' => [
                'nullable',
                'date',
            ],
            'last_points_redeemed_date' => [
                'nullable',
                'date',
            ],
            'points_expiry_date' => [
                'nullable',
                'date',
                'after:now',
            ],
            'is_active' => [
                'boolean',
            ],
            'bonus_multipliers_used' => [
                'nullable',
                'array',
            ],
            'bonus_multipliers_used.happy_hour' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'bonus_multipliers_used.birthday' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'bonus_multipliers_used.first_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'bonus_multipliers_used.referral' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'redemption_history' => [
                'nullable',
                'array',
            ],
            'redemption_history.total_redemptions' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'redemption_history.last_redemption_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'redemption_history.favorite_redemption_type' => [
                'nullable',
                'string',
                'in:discount,free_item,free_delivery',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer ID is required.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'loyalty_program_id.required' => 'Loyalty program ID is required.',
            'loyalty_program_id.exists' => 'The selected loyalty program does not exist.',
            'current_points.numeric' => 'Current points must be a number.',
            'current_points.min' => 'Current points cannot be negative.',
            'current_points.max' => 'Current points cannot exceed 999,999.99.',
            'total_points_earned.numeric' => 'Total points earned must be a number.',
            'total_points_earned.min' => 'Total points earned cannot be negative.',
            'total_points_redeemed.numeric' => 'Total points redeemed must be a number.',
            'total_points_redeemed.min' => 'Total points redeemed cannot be negative.',
            'total_points_expired.numeric' => 'Total points expired must be a number.',
            'total_points_expired.min' => 'Total points expired cannot be negative.',
            'points_expiry_date.after' => 'Points expiry date must be in the future.',
            'bonus_multipliers_used.array' => 'Bonus multipliers must be an array.',
            'redemption_history.array' => 'Redemption history must be an array.',
            'redemption_history.favorite_redemption_type.in' => 'Invalid redemption type.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that total points earned >= current points
            if ($this->has('total_points_earned') && $this->has('current_points')) {
                if ($this->total_points_earned < $this->current_points) {
                    $validator->errors()->add('total_points_earned', 'Total points earned cannot be less than current points.');
                }
            }

            // Validate that total points earned >= total points redeemed
            if ($this->has('total_points_earned') && $this->has('total_points_redeemed')) {
                if ($this->total_points_earned < $this->total_points_redeemed) {
                    $validator->errors()->add('total_points_redeemed', 'Total points redeemed cannot exceed total points earned.');
                }
            }

            // Validate that total points earned >= total points expired
            if ($this->has('total_points_earned') && $this->has('total_points_expired')) {
                if ($this->total_points_earned < $this->total_points_expired) {
                    $validator->errors()->add('total_points_expired', 'Total points expired cannot exceed total points earned.');
                }
            }
        });
    }
} 