<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaginationRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:50'],
            'sort_direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.min' => 'Page number must be at least 1.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page cannot exceed 100.',
            'search.max' => 'Search term cannot exceed 255 characters.',
            'sort_by.max' => 'Sort field name cannot exceed 50 characters.',
            'sort_direction.in' => 'Sort direction must be either "asc" or "desc".',
        ];
    }

    /**
     * Get the validated pagination parameters.
     */
    public function getPaginationParams(): array
    {
        $validated = $this->validated();
        
        return [
            'page' => $validated['page'] ?? 1,
            'per_page' => min($validated['per_page'] ?? 15, 100),
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_direction' => $validated['sort_direction'] ?? 'asc',
        ];
    }
} 