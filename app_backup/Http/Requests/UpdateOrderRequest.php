<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
        // Get the order ID from the route parameters for unique validation.
        $orderId = $this->route('order') ? $this->route('order')->id : null;

        return [
            'order_number' => 'string|max:255|unique:orders,order_number,' . $orderId,
            'customer_id' => 'exists:customers,id',
            'restaurant_id' => 'exists:restaurants,id',
            'restaurant_branch_id' => 'exists:restaurant_branches,id',
            'customer_address_id' => 'exists:customer_addresses,id',
            'status' => 'string|in:pending,confirmed,preparing,out_for_delivery,delivered,completed,cancelled',
            'type' => 'string|in:delivery,pickup',
            'payment_status' => 'string|in:pending,paid,refunded',
            'payment_method' => 'string|in:cash,card,wallet',
            'subtotal' => 'numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'delivery_instructions' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
        ];
    }
}
