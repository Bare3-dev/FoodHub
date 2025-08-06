<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRestaurantConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/'],
            'value' => ['required'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'key.required' => 'Configuration key is required',
            'key.regex' => 'Configuration key can only contain letters, numbers, and underscores',
            'value.required' => 'Configuration value is required',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'key' => 'configuration key',
            'value' => 'configuration value',
        ];
    }
} 