<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && !is_null($user->restaurant_id)) ||
               (($user->hasRole('BRANCH_MANAGER') || $user->hasRole('CASHIER') || $user->hasRole('KITCHEN_STAFF')) && !is_null($user->restaurant_branch_id)) ||
               ($user->hasRole('DELIVERY_MANAGER')) || // Delivery Manager can view all deliveries
               ($user->hasRole('CUSTOMER')); // Customer can view their own orders (handled by view method)
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isSuperAdmin() ||
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id) ||
               (($user->hasRole('BRANCH_MANAGER') || $user->hasRole('CASHIER') || $user->hasRole('KITCHEN_STAFF')) && $user->restaurant_branch_id === $order->restaurant_branch_id) ||
               ($user->hasRole('DELIVERY_MANAGER') && $order->type === 'delivery') || // Delivery Manager views delivery orders
               ($user->hasRole('CUSTOMER') && $user->id === $order->customer_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->hasRole('CUSTOMER') ||
               ($user->hasRole('CASHIER') && $user->hasPermission('order:create-takeaway'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $order->restaurant_id && $user->hasPermission('order:update-status-own-restaurant')) ||
               (($user->hasRole('BRANCH_MANAGER') || $user->hasRole('CASHIER') || $user->hasRole('KITCHEN_STAFF')) && $user->restaurant_branch_id === $order->restaurant_branch_id && $user->hasPermission('order:update-status-own-branch')) ||
               ($user->hasRole('DELIVERY_MANAGER') && $order->type === 'delivery' && $user->hasPermission('order:update-status-delivery')) ||
               ($user->hasRole('DELIVERY_MANAGER') && $user->hasPermission('order:assign-driver')) ||
               ($user->hasRole('CUSTOMER') && $user->id === $order->customer_id && $user->hasPermission('order:cancel-own'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->isSuperAdmin() ||
               ($user->hasRole('CUSTOMER_SERVICE') && $user->hasPermission('order:cancel-customer-request'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->isSuperAdmin();
    }
}
