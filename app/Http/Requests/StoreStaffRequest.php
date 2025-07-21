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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['SUPER_ADMIN', 'RESTAURANT_OWNER', 'BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE'])],
            'restaurant_id' => ['nullable', 'exists:restaurants,id', Rule::requiredIf($this->input('role') === 'RESTAURANT_OWNER')],
            'restaurant_branch_id' => ['nullable', 'exists:restaurant_branches,id', Rule::requiredIf(in_array($this->input('role'), ['BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE']))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
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
    }
}
