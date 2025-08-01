<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can('create', \App\Models\User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $allowedRoles = $this->getAllowedRoles($user);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'restaurant_id' => ['nullable', 'exists:restaurants,id'],
            'restaurant_branch_id' => ['nullable', 'exists:restaurant_branches,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Get the allowed roles based on the current user's role
     */
    private function getAllowedRoles($user): array
    {
        if ($user->isSuperAdmin()) {
            return ['SUPER_ADMIN', 'RESTAURANT_OWNER', 'BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE'];
        }

        if ($user->hasRole('RESTAURANT_OWNER')) {
            return ['BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE'];
        }

        if ($user->hasRole('BRANCH_MANAGER')) {
            return ['CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE'];
        }

        return [];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = Auth::user();

        // Ensure that if a role is provided, the associated IDs are also correctly handled.
        // For Super Admin, ensure restaurant_id and restaurant_branch_id are null.
        if ($this->input('role') === 'SUPER_ADMIN') {
            $this->merge([
                'restaurant_id' => null,
                'restaurant_branch_id' => null,
            ]);
        }

        // For Restaurant Owner, ensure branch_id is null
        if ($this->input('role') === 'RESTAURANT_OWNER') {
            $this->merge([
                'restaurant_branch_id' => null,
            ]);
        }

        // For non-super admins, automatically set restaurant/branch based on their role
        if (!$user->isSuperAdmin()) {
            if ($user->hasRole('RESTAURANT_OWNER')) {
                $this->merge([
                    'restaurant_id' => $user->restaurant_id,
                ]);
            } elseif ($user->hasRole('BRANCH_MANAGER')) {
                $this->merge([
                    'restaurant_id' => $user->restaurant_id,
                    'restaurant_branch_id' => $user->restaurant_branch_id,
                ]);
            }
        }
    }

    /**
     * Get custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'role.in' => 'You do not have permission to create users with this role.',
            'restaurant_id.required_if' => 'Restaurant ID is required for restaurant owners.',
            'restaurant_branch_id.required_if' => 'Branch ID is required for branch-specific roles.',
        ];
    }
}
