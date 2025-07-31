<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;

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
        $user = $this->route('staff') ?? $this->route('user');
        $userId = $user ? (is_object($user) ? $user->id : $user) : null;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $userId,
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'password_confirmation' => 'sometimes|nullable|string|min:8',
            'role' => 'sometimes|string|in:SUPER_ADMIN,RESTAURANT_OWNER,BRANCH_MANAGER,CASHIER,KITCHEN_STAFF,DELIVERY_MANAGER,CUSTOMER_SERVICE,DRIVER',
            'restaurant_id' => 'sometimes|nullable|exists:restaurants,id',
            'restaurant_branch_id' => 'sometimes|nullable|exists:restaurant_branches,id',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'phone' => 'sometimes|nullable|string|max:20',
            'profile_image_url' => 'sometimes|nullable|url|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'role.in' => 'The selected role is invalid.',
            'restaurant_id.exists' => 'The selected restaurant does not exist.',
            'restaurant_branch_id.exists' => 'The selected restaurant branch does not exist.',
            'status.in' => 'The status must be active, inactive, or suspended.',
            'permissions.array' => 'Permissions must be an array.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that restaurant_branch_id belongs to the specified restaurant_id
            if ($this->has('restaurant_id') && $this->has('restaurant_branch_id')) {
                $restaurantId = $this->input('restaurant_id');
                $branchId = $this->input('restaurant_branch_id');
                
                if ($restaurantId && $branchId) {
                    $branch = RestaurantBranch::where('id', $branchId)
                        ->where('restaurant_id', $restaurantId)
                        ->first();
                    
                    if (!$branch) {
                        $validator->errors()->add('restaurant_branch_id', 'The selected branch does not belong to the specified restaurant.');
                    }
                }
            }
        });
    }
}
