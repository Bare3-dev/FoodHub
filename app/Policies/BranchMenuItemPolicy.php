<?php

namespace App\Policies;

use App\Models\BranchMenuItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BranchMenuItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && !is_null($user->restaurant_id)) ||
               ($user->hasRole('BRANCH_MANAGER') && !is_null($user->restaurant_branch_id));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BranchMenuItem $branchMenuItem): bool
    {
        return $user->isSuperAdmin() ||
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $branchMenuItem->branch->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $branchMenuItem->restaurant_branch_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') ||
               $user->hasRole('BRANCH_MANAGER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BranchMenuItem $branchMenuItem): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $branchMenuItem->branch->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $branchMenuItem->restaurant_branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BranchMenuItem $branchMenuItem): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $branchMenuItem->branch->restaurant_id) ||
               ($user->hasRole('BRANCH_MANAGER') && $user->restaurant_branch_id === $branchMenuItem->restaurant_branch_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BranchMenuItem $branchMenuItem): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BranchMenuItem $branchMenuItem): bool
    {
        return $user->isSuperAdmin();
    }
}
