<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

final class ConfigureLoyaltyProgramRequest extends FormRequest
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
            'points_per_currency' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'currency_per_point' => ['nullable', 'numeric', 'min:0.001', 'max:10'],
            'tier_thresholds' => ['nullable', 'array'],
            'tier_thresholds.bronze' => ['nullable', 'integer', 'min:0'],
            'tier_thresholds.silver' => ['nullable', 'integer', 'min:0'],
            'tier_thresholds.gold' => ['nullable', 'integer', 'min:0'],
            'tier_thresholds.platinum' => ['nullable', 'integer', 'min:0'],
            'spin_wheel_probabilities' => ['nullable', 'array'],
            'spin_wheel_probabilities.points_10' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'spin_wheel_probabilities.points_25' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'spin_wheel_probabilities.points_50' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'spin_wheel_probabilities.points_100' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'stamp_card_requirements' => ['nullable', 'array'],
            'stamp_card_requirements.stamps_needed' => ['nullable', 'integer', 'min:1', 'max:50'],
            'stamp_card_requirements.reward_value' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'points_per_currency.numeric' => 'Points per currency must be a number',
            'points_per_currency.min' => 'Points per currency must be at least 0.01',
            'points_per_currency.max' => 'Points per currency cannot exceed 100',
            'currency_per_point.numeric' => 'Currency per point must be a number',
            'currency_per_point.min' => 'Currency per point must be at least 0.001',
            'currency_per_point.max' => 'Currency per point cannot exceed 10',
            'tier_thresholds.array' => 'Tier thresholds must be an array',
            'tier_thresholds.*.integer' => 'Tier threshold must be an integer',
            'tier_thresholds.*.min' => 'Tier threshold must be non-negative',
            'spin_wheel_probabilities.array' => 'Spin wheel probabilities must be an array',
            'spin_wheel_probabilities.*.numeric' => 'Probability must be a number',
            'spin_wheel_probabilities.*.min' => 'Probability must be between 0 and 1',
            'spin_wheel_probabilities.*.max' => 'Probability must be between 0 and 1',
            'stamp_card_requirements.array' => 'Stamp card requirements must be an array',
            'stamp_card_requirements.stamps_needed.integer' => 'Stamps needed must be an integer',
            'stamp_card_requirements.stamps_needed.min' => 'Stamps needed must be at least 1',
            'stamp_card_requirements.stamps_needed.max' => 'Stamps needed cannot exceed 50',
            'stamp_card_requirements.reward_value.numeric' => 'Reward value must be a number',
            'stamp_card_requirements.reward_value.min' => 'Reward value must be at least 0.01',
            'stamp_card_requirements.reward_value.max' => 'Reward value cannot exceed 1000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'points_per_currency' => 'points per currency',
            'currency_per_point' => 'currency per point',
            'tier_thresholds' => 'tier thresholds',
            'spin_wheel_probabilities' => 'spin wheel probabilities',
            'stamp_card_requirements' => 'stamp card requirements',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTierThresholds($validator);
            $this->validateSpinWheelProbabilities($validator);
        });
    }

    /**
     * Validate tier thresholds are in ascending order
     */
    private function validateTierThresholds($validator): void
    {
        $thresholds = $this->input('tier_thresholds', []);
        
        if (empty($thresholds)) {
            return;
        }
        
        $previousThreshold = 0;
        foreach ($thresholds as $tier => $threshold) {
            if ($threshold < $previousThreshold) {
                $validator->errors()->add('tier_thresholds', "Invalid tier threshold for $tier: must be greater than or equal to previous tier");
            }
            $previousThreshold = $threshold;
        }
    }

    /**
     * Validate spin wheel probabilities sum to 1.0
     */
    private function validateSpinWheelProbabilities($validator): void
    {
        $probabilities = $this->input('spin_wheel_probabilities', []);
        
        if (empty($probabilities)) {
            return;
        }
        
        $totalProbability = array_sum($probabilities);
        if (abs($totalProbability - 1.0) > 0.01) {
            $validator->errors()->add('spin_wheel_probabilities', 'Spin wheel probabilities must sum to 1.0');
        }
    }
} 