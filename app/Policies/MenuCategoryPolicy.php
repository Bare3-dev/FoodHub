<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MenuCategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && !is_null($user->restaurant_id)) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->branch && !is_null($user->branch->restaurant_id));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $menuCategory->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->branch && $user->branch->restaurant_id === $menuCategory->restaurant_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('RESTAURANT_OWNER') || ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id !== null && $user->restaurant_id !== null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $menuCategory->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->branch && $user->branch->restaurant_id === $menuCategory->restaurant_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $menuCategory->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->branch && $user->branch->restaurant_id === $menuCategory->restaurant_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isSuperAdmin();
    }
}
