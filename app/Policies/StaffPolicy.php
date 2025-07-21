<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class StaffPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // SUPER_ADMIN, RESTAURANT_OWNER, BRANCH_MANAGER can view staff members
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && !is_null($user->restaurant_id)) ||
               ($user->hasRole('BRANCH_MANAGER') && !is_null($user->restaurant_branch_id));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // SUPER_ADMIN can view any staff member
        // RESTAURANT_OWNER can view staff in their restaurant
        // BRANCH_MANAGER can view staff in their branch
        return $user->isSuperAdmin() ||
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $model->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $model->restaurant_branch_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // SUPER_ADMIN, RESTAURANT_OWNER, BRANCH_MANAGER can create staff members
        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // SUPER_ADMIN can update any staff member
        // RESTAURANT_OWNER can update staff in their restaurant
        // BRANCH_MANAGER can update staff in their branch
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $model->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $model->restaurant_branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // SUPER_ADMIN can delete any staff member
        // RESTAURANT_OWNER can delete staff in their restaurant
        // BRANCH_MANAGER can delete staff in their branch
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $model->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $model->restaurant_branch_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
}
