<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuItemRequest extends FormRequest
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
        // Get the menu item ID from the route parameters for unique validation (if applicable).
        // In this case, name is not unique globally, but if it were, we'd use this logic:
        // $menuItemId = $this->route('menu_item') ? $this->route('menu_item')->id : null;

        return [
            'menu_category_id' => 'exists:menu_categories,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'prep_time' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|json',
            'allergens' => 'nullable|json',
            'dietary_tags' => 'nullable|json',
            'image_url' => 'nullable|string|max:255',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'nutritional_info' => 'nullable|json',
        ];
    }
}
