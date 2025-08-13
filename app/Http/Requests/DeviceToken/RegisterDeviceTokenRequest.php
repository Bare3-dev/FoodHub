<?php

namespace App\Http\Requests\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Allow public access for device registration
    }

    public function rules(): array
    {
        return [
            'user_type' => 'required|string|in:customer,driver,user',
            'user_id' => 'required|integer|min:1',
            'token' => 'required|string|min:100|max:500',
            'platform' => 'required|string|in:ios,android',
        ];
    }

    public function messages(): array
    {
        return [
            'user_type.required' => 'User type is required',
            'user_type.in' => 'User type must be customer, driver, or user',
            'user_id.required' => 'User ID is required',
            'user_id.integer' => 'User ID must be a number',
            'user_id.min' => 'User ID must be greater than 0',
            'token.required' => 'Device token is required',
            'token.min' => 'Device token must be at least 100 characters',
            'token.max' => 'Device token must not exceed 500 characters',
            'platform.required' => 'Platform is required',
            'platform.in' => 'Platform must be ios or android',
        ];
    }
}
