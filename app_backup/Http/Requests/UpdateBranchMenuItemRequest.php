<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchMenuItemRequest extends FormRequest
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
        // No unique validation needed for branch_menu_items based on current fields.

        return [
            'restaurant_branch_id' => 'exists:restaurant_branches,id',
            'menu_item_id' => 'exists:menu_items,id',
            'price' => 'numeric|min:0',
            'is_available' => 'boolean',
            'is_recommended' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0',
        ];
    }
}
