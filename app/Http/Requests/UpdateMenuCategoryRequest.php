<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuCategoryRequest extends FormRequest
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
        // Get the menu category ID from the route parameters for unique validation.
        $menuCategoryId = $this->route('menu_category') ? $this->route('menu_category')->id : null;

        return [
            'name' => 'string|max:255|unique:menu_categories,name,' . $menuCategoryId,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'image_url' => 'nullable|string|max:255',
        ];
    }
}
