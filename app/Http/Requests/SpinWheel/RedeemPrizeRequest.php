<?php

declare(strict_types=1);

namespace App\Http\Requests\SpinWheel;

use Illuminate\Foundation\Http\FormRequest;

final class RedeemPrizeRequest extends FormRequest
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
            'spin_result_id' => 'required|integer|exists:spin_results,id',
            'order_id' => 'nullable|integer|exists:orders,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'spin_result_id.required' => 'Spin result ID is required.',
            'spin_result_id.integer' => 'Spin result ID must be a whole number.',
            'spin_result_id.exists' => 'Spin result not found.',
            'order_id.integer' => 'Order ID must be a whole number.',
            'order_id.exists' => 'Order not found.',
        ];
    }
} 