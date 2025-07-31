<?php

namespace App\Policies;

use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoyaltyProgramPolicy
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

        // Check for specific permissions first
        if ($user->hasPermission('loyalty-program:view') || 
            $user->hasPermission('loyalty-program:manage')) {
            return true;
        }

        // Fall back to role-based checks
        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, ?LoyaltyProgram $loyaltyProgram): bool
    {
        // Handle null values
        if (!$user || !$loyaltyProgram) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        // Check for specific permissions first
        if ($user->hasPermission('loyalty-program:view') || 
            $user->hasPermission('loyalty-program:manage')) {
            return true;
        }

        // Fall back to role-based checks
        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') ||
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
               $user->hasRole('RESTAURANT_OWNER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, ?LoyaltyProgram $loyaltyProgram): bool
    {
        // Handle null values
        if (!$user || !$loyaltyProgram) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, ?LoyaltyProgram $loyaltyProgram): bool
    {
        // Handle null values
        if (!$user || !$loyaltyProgram) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, ?LoyaltyProgram $loyaltyProgram): bool
    {
        // Handle null values
        if (!$user || !$loyaltyProgram) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, ?LoyaltyProgram $loyaltyProgram): bool
    {
        // Handle null values
        if (!$user || !$loyaltyProgram) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
