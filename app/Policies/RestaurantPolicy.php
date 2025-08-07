<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Models\PosIntegration;

class RestaurantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can upload logo for the model.
     */
    public function upload(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can integrate POS for the restaurant.
     */
    public function integratePOS(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can view POS status for the restaurant.
     */
    public function viewPOSStatus(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can sync menu for the restaurant.
     */
    public function syncMenu(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can sync inventory for the restaurant.
     */
    public function syncInventory(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }

    /**
     * Determine whether the user can update POS integration for the restaurant.
     */
    public function updatePOSIntegration(User $user, PosIntegration $integration): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $integration->restaurant_id);
    }

    /**
     * Determine whether the user can delete POS integration for the restaurant.
     */
    public function deletePOSIntegration(User $user, PosIntegration $integration): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $integration->restaurant_id);
    }

    /**
     * Determine whether the user can validate POS connection for the restaurant.
     */
    public function validatePOSConnection(User $user, Restaurant $restaurant): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $restaurant->id);
    }
}
