<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        // Get the user ID from the route parameters for unique validation.
        $userId = $this->route('staff') ? $this->route('staff')->id : null;

        return [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $userId,
            'password' => 'nullable|string|min:8',
        ];
    }
}
