<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'order_number' => 'required|string|max:255|unique:orders',
            'customer_id' => 'required|exists:customers,id',
            'restaurant_id' => 'required|exists:restaurants,id',
            'restaurant_branch_id' => 'required|exists:restaurant_branches,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'status' => 'required|string|in:pending,confirmed,preparing,out_for_delivery,delivered,completed,cancelled',
            'type' => 'required|string|in:delivery,pickup',
            'payment_status' => 'required|string|in:pending,paid,refunded',
            'payment_method' => 'required|string|in:cash,card,wallet',
            'subtotal' => 'required|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'delivery_instructions' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
        ];
    }
}
