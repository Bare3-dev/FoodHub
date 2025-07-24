<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // SUPER_ADMIN, CUSTOMER_SERVICE, and CASHIER can view customers
        return $user->isSuperAdmin() || 
               $user->hasRole('CUSTOMER_SERVICE') ||
               $user->hasRole('CASHIER');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        // SUPER_ADMIN and CUSTOMER_SERVICE can view any customer
        // RESTAURANT_OWNER can view customers associated with their restaurant (e.g., through orders)
        // CASHIER can view customers in their branch
        // A customer can view their own profile
        return $user->isSuperAdmin() ||
               $user->hasRole('CUSTOMER_SERVICE') ||
               ($user->hasRole('RESTAURANT_OWNER') && ($customer->orders()->whereNotNull('restaurant_id')->first() && $user->restaurant_id === $customer->orders()->whereNotNull('restaurant_id')->first()->restaurant_id)) ||
               ($user->hasRole('CASHIER') && ($customer->orders()->whereNotNull('restaurant_branch_id')->first() && $user->restaurant_branch_id === $customer->orders()->whereNotNull('restaurant_branch_id')->first()->restaurant_branch_id)) ||
               ($user->id === $customer->id && $user->hasRole('CUSTOMER')); // Assuming customer is also a User model
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // SUPER_ADMIN and CASHIER can create customers
        return $user->isSuperAdmin() || 
               $user->hasRole('CASHIER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        // SUPER_ADMIN can update any customer
        // CUSTOMER_SERVICE can update basic info of any customer
        // A customer can update their own profile
        return $user->isSuperAdmin() || 
               ($user->hasRole('CUSTOMER_SERVICE') && $user->hasPermission('customer:update-basic-info')) || 
               ($user->id === $customer->id && $user->hasRole('CUSTOMER'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // Only SUPER_ADMIN can delete customers
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        // Only SUPER_ADMIN can restore customers
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        // Only SUPER_ADMIN can permanently delete customers
        return $user->isSuperAdmin();
    }
}
