<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Auth\Access\Response;

class OrderPolicy
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
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('CASHIER') ||
               $user->hasRole('KITCHEN_STAFF') ||
               $user->hasRole('DELIVERY_MANAGER') ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, ?Order $order): bool
    {
        // Handle null values
        if (!$user || !$order) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() ||
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('CASHIER') ||
               $user->hasRole('KITCHEN_STAFF') ||
               $user->hasRole('DELIVERY_MANAGER') ||
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
               $user->hasRole('CASHIER') ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, ?Order $order): bool
    {
        // Handle null values
        if (!$user || !$order) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
               $user->hasRole('CASHIER') ||
               $user->hasRole('KITCHEN_STAFF') ||
               $user->hasRole('DELIVERY_MANAGER') ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, ?Order $order): bool
    {
        // Handle null values
        if (!$user || !$order) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin() ||
               $user->hasRole('CUSTOMER_SERVICE');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, ?Order $order): bool
    {
        // Handle null values
        if (!$user || !$order) {
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
    public function forceDelete(?User $user, ?Order $order): bool
    {
        // Handle null values
        if (!$user || !$order) {
            return false;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
