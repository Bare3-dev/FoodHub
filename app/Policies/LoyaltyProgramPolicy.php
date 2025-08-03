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

        // Super admin can view any loyalty program
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can view loyalty programs in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER')) {
            return true;
        }

        // Branch managers can view loyalty programs in their restaurant
        if ($user->hasRole('BRANCH_MANAGER')) {
            return true;
        }

        // Customer service can view any loyalty program
        if ($user->hasRole('CUSTOMER_SERVICE')) {
            return true;
        }

        // Cashiers can view loyalty programs in their restaurant
        if ($user->hasRole('CASHIER')) {
            return true;
        }

        return false;
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

        // Super admin can view any loyalty program
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can view loyalty programs in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        // Branch managers can view loyalty programs in their restaurant
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        // Cashiers can view loyalty programs in their restaurant
        if ($user->hasRole('CASHIER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        // Customer service can view any loyalty program
        if ($user->hasRole('CUSTOMER_SERVICE')) {
            return true;
        }

        return false;
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

        // Super admin can update any loyalty program
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can update loyalty programs in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        // Branch managers can update loyalty programs in their restaurant
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        // Customer service can update any loyalty program
        if ($user->hasRole('CUSTOMER_SERVICE')) {
            return true;
        }

        return false;
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

        // Super admin can delete any loyalty program
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can delete loyalty programs in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        return false;
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

        // Super admin can restore any loyalty program
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can restore loyalty programs in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        return false;
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

        // Super admin can permanently delete any loyalty program
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can permanently delete loyalty programs in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) {
            return true;
        }

        return false;
    }
}
