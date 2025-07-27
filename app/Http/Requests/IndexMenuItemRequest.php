<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:menu_categories,id',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'dietary_tags' => 'nullable|string',
            'allergens' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'search.max' => 'The search term cannot exceed 255 characters.',
            'category_id.exists' => 'The selected category does not exist.',
            'min_price.numeric' => 'The minimum price must be a number.',
            'max_price.numeric' => 'The maximum price must be a number.',
            'per_page.max' => 'The per page parameter cannot exceed 100.',
        ];
    }
} 