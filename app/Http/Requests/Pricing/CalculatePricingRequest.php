<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

final class CalculatePricingRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'address_id' => 'required|exists:customer_addresses,id',
            'coupons' => 'array',
            'coupons.*' => 'string|max:255',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required',
            'order_id.exists' => 'Order not found',
            'address_id.required' => 'Address ID is required',
            'address_id.exists' => 'Address not found',
            'coupons.array' => 'Coupons must be an array',
            'coupons.*.string' => 'Coupon codes must be strings',
            'coupons.*.max' => 'Coupon codes cannot exceed 255 characters',
        ];
    }
} 