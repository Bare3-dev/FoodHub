<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DriverPolicy
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

        return $user->isSuperAdmin() || 
               $user->hasRole('DELIVERY_MANAGER') ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('CASHIER') ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, ?Driver $driver): bool
    {
        // Handle null values
        if (!$user || !$driver) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() ||
               $user->hasRole('DELIVERY_MANAGER') ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('CASHIER') ||
               $user->hasRole('CUSTOMER_SERVICE');
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

        return $user->isSuperAdmin() || 
               $user->hasRole('BRANCH_MANAGER') || 
               $user->hasRole('DELIVERY_MANAGER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, ?Driver $driver): bool
    {
        // Handle null values
        if (!$user || !$driver) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() || 
               $user->hasRole('DELIVERY_MANAGER') ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, ?Driver $driver): bool
    {
        // Handle null values
        if (!$user || !$driver) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() || 
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('DELIVERY_MANAGER');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, ?Driver $driver): bool
    {
        // Handle null values
        if (!$user || !$driver) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() || 
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('DELIVERY_MANAGER');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, ?Driver $driver): bool
    {
        // Handle null values
        if (!$user || !$driver) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() || 
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('DELIVERY_MANAGER');
    }
}
