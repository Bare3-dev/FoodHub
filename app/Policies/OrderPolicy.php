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

        // Super admin can view any order
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can view orders in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id) {
            return true;
        }

        // Branch managers can view orders in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $order->restaurant_branch_id) {
            return true;
        }

        // Staff can view orders in their branches
        if (in_array($user->role, ['CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE']) 
            && $user->restaurant_branch_id === $order->restaurant_branch_id) {
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
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER') ||
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

        // Super admin can update any order
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can update orders in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id) {
            return true;
        }

        // Branch managers can update orders in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $order->restaurant_branch_id) {
            return true;
        }

        // Staff can update orders in their branches
        if (in_array($user->role, ['CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE']) 
            && $user->restaurant_branch_id === $order->restaurant_branch_id) {
            return true;
        }

        return false;
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

        // Super admin can delete any order
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can delete orders in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id) {
            return true;
        }

        // Branch managers can delete orders in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $order->restaurant_branch_id) {
            return true;
        }

        // Customer service can delete any order
        if ($user->hasRole('CUSTOMER_SERVICE')) {
            return true;
        }

        return false;
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

        // Super admin can restore any order
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can restore orders in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id) {
            return true;
        }

        // Branch managers can restore orders in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $order->restaurant_branch_id) {
            return true;
        }

        // Customer service can restore any order
        if ($user->hasRole('CUSTOMER_SERVICE')) {
            return true;
        }

        return false;
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

        // Super admin can permanently delete any order
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can permanently delete orders in their restaurants
        if ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id) {
            return true;
        }

        // Branch managers can permanently delete orders in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $order->restaurant_branch_id) {
            return true;
        }

        // Customer service can permanently delete any order
        if ($user->hasRole('CUSTOMER_SERVICE')) {
            return true;
        }

        return false;
    }
}
