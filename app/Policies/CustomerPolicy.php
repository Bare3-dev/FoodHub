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
    public function viewAny(?User $user): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN, CUSTOMER_SERVICE, CASHIER, RESTAURANT_OWNER, and BRANCH_MANAGER can view customers
        return $user->isSuperAdmin() || 
               $user->hasRole('CUSTOMER_SERVICE') ||
               $user->hasRole('CASHIER') ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, ?Customer $customer): bool
    {
        // Handle null values
        if (!$user || !$customer) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN and CUSTOMER_SERVICE can view any customer
        // RESTAURANT_OWNER and BRANCH_MANAGER can view customers
        // CASHIER can view customers
        return $user->isSuperAdmin() ||
               $user->hasRole('CUSTOMER_SERVICE') ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('CASHIER');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(?User $user): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN and CUSTOMER_SERVICE can create customers
        return $user->isSuperAdmin() || 
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, ?Customer $customer): bool
    {
        // Handle null values
        if (!$user || !$customer) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN can update any customer
        // CUSTOMER_SERVICE can update basic info of any customer
        // RESTAURANT_OWNER and BRANCH_MANAGER can update customers
        return $user->isSuperAdmin() || 
               $user->hasRole('CUSTOMER_SERVICE') ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, ?Customer $customer): bool
    {
        // Handle null values
        if (!$user || !$customer) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN and CUSTOMER_SERVICE can delete customers
        return $user->isSuperAdmin() || $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, ?Customer $customer): bool
    {
        // Handle null values
        if (!$user || !$customer) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN and CUSTOMER_SERVICE can restore customers
        return $user->isSuperAdmin() || $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, ?Customer $customer): bool
    {
        // Handle null values
        if (!$user || !$customer) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // SUPER_ADMIN and CUSTOMER_SERVICE can permanently delete customers
        return $user->isSuperAdmin() || $user->hasRole('CUSTOMER_SERVICE');
    }
}
