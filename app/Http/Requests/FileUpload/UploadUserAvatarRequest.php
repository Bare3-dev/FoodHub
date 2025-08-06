<?php

declare(strict_types=1);

namespace App\Http\Requests\FileUpload;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload User Avatar Request
 * 
 * Validates user avatar upload requests with comprehensive
 * file type, size, and security checks.
 */
final class UploadUserAvatarRequest extends FormRequest
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
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:1024', // 1MB
                'dimensions:min_width=100,min_height=100,max_width=1500,max_height=1500',
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
            'avatar.required' => 'An avatar file is required.',
            'avatar.file' => 'The uploaded file must be a valid file.',
            'avatar.image' => 'The uploaded file must be an image.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, jpg, png, webp.',
            'avatar.max' => 'The avatar may not be greater than 1MB.',
            'avatar.dimensions' => 'The avatar dimensions must be between 100x100 and 1500x1500 pixels.',
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
            'avatar' => 'user avatar',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('avatar');
            
            if ($file && !$this->isValidImageFile($file)) {
                $validator->errors()->add('avatar', 'The uploaded file appears to be corrupted or invalid.');
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