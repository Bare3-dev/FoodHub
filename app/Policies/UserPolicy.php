<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Super admin can view all staff
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can view staff in their restaurants
        if ($user->isRestaurantOwner()) {
            return true;
        }

        // Branch managers can view staff in their branches
        if ($user->hasRole('BRANCH_MANAGER')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, User $model): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Super admin can view any staff
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can view staff in their restaurants
        if ($user->isRestaurantOwner() && $this->belongsToSameRestaurant($user, $model)) {
            return true;
        }

        // Branch managers can view staff in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $this->belongsToSameBranch($user, $model)) {
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

        // Super admin can create any staff
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can create staff for their restaurants
        if ($user->isRestaurantOwner()) {
            return true;
        }

        // Branch managers can create staff for their branches
        if ($user->hasRole('BRANCH_MANAGER')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create a staff member with a specific role.
     */
    public function createWithRole(?User $user, string $targetRole): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Super admin can create any staff with any role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check role hierarchy - users can only create staff with roles at or below their level
        if (!$user->canAccessRole($targetRole)) {
            return false;
        }

        // Restaurant owners can create staff for their restaurants
        if ($user->isRestaurantOwner()) {
            return true;
        }

        // Branch managers can create staff for their branches
        if ($user->hasRole('BRANCH_MANAGER')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, User $model): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Super admin can update any staff
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can update staff in their restaurants
        if ($user->isRestaurantOwner() && $this->belongsToSameRestaurant($user, $model)) {
            return true;
        }

        // Branch managers can update staff in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $this->belongsToSameBranch($user, $model)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, User $model): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Super admin can delete any staff
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can delete staff in their restaurants
        if ($user->isRestaurantOwner() && $this->belongsToSameRestaurant($user, $model)) {
            return true;
        }

        // Branch managers can delete staff in their branches
        if ($user->hasRole('BRANCH_MANAGER') && $this->belongsToSameBranch($user, $model)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, User $model): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Only super admin can restore deleted staff
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, User $model): bool
    {
        // Handle null user
        if (!$user) {
            return false;
        }

        // Only super admin can permanently delete staff
        return $user->isSuperAdmin();
    }

    /**
     * Check if the target user belongs to the same restaurant as the authenticated user.
     */
    private function belongsToSameRestaurant(User $user, User $target): bool
    {
        // If user has no restaurant, they can't manage staff
        if (!$user->restaurant_id) {
            return false;
        }

        // If target has no restaurant, they can't be managed by restaurant owner
        if (!$target->restaurant_id) {
            return false;
        }

        return $user->restaurant_id === $target->restaurant_id;
    }

    /**
     * Check if the target user belongs to the same branch as the authenticated user.
     */
    private function belongsToSameBranch(User $user, User $target): bool
    {
        // If user has no branch, they can't manage staff
        if (!$user->restaurant_branch_id) {
            return false;
        }

        // If target has no branch, they can't be managed by branch manager
        if (!$target->restaurant_branch_id) {
            return false;
        }

        return $user->restaurant_branch_id === $target->restaurant_branch_id;
    }
}
