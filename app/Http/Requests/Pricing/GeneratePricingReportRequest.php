<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

final class GeneratePricingReportRequest extends FormRequest
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
            'restaurant_id' => 'required|exists:restaurants,id',
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/', // Format: YYYY-MM
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Restaurant ID is required',
            'restaurant_id.exists' => 'Restaurant not found',
            'period.required' => 'Period is required',
            'period.string' => 'Period must be a string',
            'period.regex' => 'Period must be in YYYY-MM format (e.g., 2024-01)',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure period is in correct format
        if ($this->has('period')) {
            $period = $this->input('period');
            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                $this->merge(['period' => $period]);
            }
        }
    }
} 