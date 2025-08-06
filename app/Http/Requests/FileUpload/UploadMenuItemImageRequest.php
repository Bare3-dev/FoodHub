<?php

declare(strict_types=1);

namespace App\Http\Requests\FileUpload;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upload Menu Item Image Request
 * 
 * Validates menu item image upload requests with comprehensive
 * file type, size, and security checks.
 */
final class UploadMenuItemImageRequest extends FormRequest
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
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:5120', // 5MB
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
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
            'image.required' => 'An image file is required.',
            'image.file' => 'The uploaded file must be a valid file.',
            'image.image' => 'The uploaded file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, jpg, png, webp, gif.',
            'image.max' => 'The image may not be greater than 5MB.',
            'image.dimensions' => 'The image dimensions must be between 100x100 and 4000x4000 pixels.',
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
            'image' => 'menu item image',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('image');
            
            if ($file && !$this->isValidImageFile($file)) {
                $validator->errors()->add('image', 'The uploaded file appears to be corrupted or invalid.');
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