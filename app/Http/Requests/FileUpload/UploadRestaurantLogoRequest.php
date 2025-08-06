<?php

declare(strict_types=1);

namespace App\Http\Requests\FileUpload;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload Restaurant Logo Request
 * 
 * Validates restaurant logo upload requests with comprehensive
 * file type, size, and security checks.
 */
final class UploadRestaurantLogoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'A logo file is required.',
            'logo.file' => 'The uploaded file must be a valid file.',
            'logo.image' => 'The uploaded file must be an image.',
            'logo.mimes' => 'The logo must be a file of type: jpeg, jpg, png, webp.',
            'logo.max' => 'The logo may not be greater than 2MB.',
            'logo.dimensions' => 'The logo dimensions must be between 100x100 and 2000x2000 pixels.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => 'restaurant logo',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('logo');
            
            if ($file && !$this->isValidImageFile($file)) {
                $validator->errors()->add('logo', 'The uploaded file appears to be corrupted or invalid.');
            }
        });
    }

    /**
     * Check if the uploaded file is a valid image
     */
    private function isValidImageFile($file): bool
    {
        try {
            $imageInfo = getimagesize($file->getRealPath());
            return $imageInfo !== false && $imageInfo[0] > 0 && $imageInfo[1] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
} 