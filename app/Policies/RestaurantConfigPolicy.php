<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RestaurantConfig;
use App\Models\User;

final class RestaurantConfigPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view restaurant configs');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RestaurantConfig $restaurantConfig): bool
    {
        return $user->hasPermissionTo('view restaurant configs') &&
               $user->restaurant_id === $restaurantConfig->restaurant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create restaurant configs');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RestaurantConfig $restaurantConfig): bool
    {
        return $user->hasPermissionTo('update restaurant configs') &&
               $user->restaurant_id === $restaurantConfig->restaurant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RestaurantConfig $restaurantConfig): bool
    {
        return $user->hasPermissionTo('delete restaurant configs') &&
               $user->restaurant_id === $restaurantConfig->restaurant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RestaurantConfig $restaurantConfig): bool
    {
        return $user->hasPermissionTo('restore restaurant configs') &&
               $user->restaurant_id === $restaurantConfig->restaurant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RestaurantConfig $restaurantConfig): bool
    {
        return $user->hasPermissionTo('force delete restaurant configs') &&
               $user->restaurant_id === $restaurantConfig->restaurant_id;
    }
} 