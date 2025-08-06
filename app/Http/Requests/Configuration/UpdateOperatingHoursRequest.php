<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOperatingHoursRequest extends FormRequest
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
            'operating_hours' => ['required', 'array'],
            'operating_hours.*' => ['array'],
            'operating_hours.*.open' => ['required', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'operating_hours.*.close' => ['required', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'operating_hours.required' => 'Operating hours are required',
            'operating_hours.array' => 'Operating hours must be an array',
            'operating_hours.*.array' => 'Each day must have open and close times',
            'operating_hours.*.open.required' => 'Opening time is required for each day',
            'operating_hours.*.close.required' => 'Closing time is required for each day',
            'operating_hours.*.open.regex' => 'Opening time must be in HH:MM format',
            'operating_hours.*.close.regex' => 'Closing time must be in HH:MM format',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'operating_hours' => 'operating hours',
            'operating_hours.*.open' => 'opening time',
            'operating_hours.*.close' => 'closing time',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hours = $this->input('operating_hours', []);
            $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($hours as $day => $times) {
                if (!in_array($day, $validDays)) {
                    $validator->errors()->add('operating_hours', "Invalid day: $day");
                }
            }
        });
    }
} 