<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DriverPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->hasRole('DELIVERY_MANAGER') ||
               ($user->hasRole('RESTAURANT_OWNER') && !is_null($user->restaurant_id)) ||
               ($user->hasRole('BRANCH_MANAGER') && !is_null($user->restaurant_branch_id));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Driver $driver): bool
    {
        return $user->isSuperAdmin() ||
               $user->hasRole('DELIVERY_MANAGER') ||
               ($user->hasRole('RESTAURANT_OWNER') && $driver->workingZones()->where('restaurant_id', $user->restaurant_id)->exists()) ||
               ($user->hasRole('RESTAURANT_OWNER') && $driver->orderAssignments()->whereHas('order', function ($query) use ($user) { $query->where('restaurant_id', $user->restaurant_id); })->exists()) ||
               ($user->hasRole('BRANCH_MANAGER') && $driver->workingZones()->where('restaurant_branch_id', $user->restaurant_branch_id)->exists()) ||
               ($user->hasRole('BRANCH_MANAGER') && $driver->orderAssignments()->whereHas('order', function ($query) use ($user) { $query->where('restaurant_branch_id', $user->restaurant_branch_id); })->exists());
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER') || 
               $user->hasRole('BRANCH_MANAGER') || 
               $user->hasRole('DELIVERY_MANAGER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Driver $driver): bool
    {
        return $user->isSuperAdmin() || 
               $user->hasRole('DELIVERY_MANAGER') ||
               ($user->hasRole('RESTAURANT_OWNER') && $driver->workingZones()->where('restaurant_id', $user->restaurant_id)->exists()) ||
               ($user->hasRole('RESTAURANT_OWNER') && $driver->orderAssignments()->whereHas('order', function ($query) use ($user) { $query->where('restaurant_id', $user->restaurant_id); })->exists()) ||
               ($user->hasRole('BRANCH_MANAGER') && $driver->workingZones()->where('restaurant_branch_id', $user->restaurant_branch_id)->exists()) ||
               ($user->hasRole('BRANCH_MANAGER') && $driver->orderAssignments()->whereHas('order', function ($query) use ($user) { $query->where('restaurant_branch_id', $user->restaurant_branch_id); })->exists());
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Driver $driver): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $driver->workingZones()->where('restaurant_id', $user->restaurant_id)->exists()) ||
               ($user->hasRole('RESTAURANT_OWNER') && $driver->orderAssignments()->whereHas('order', function ($query) use ($user) { $query->where('restaurant_id', $user->restaurant_id); })->exists()) ||
               ($user->hasRole('BRANCH_MANAGER') && $driver->workingZones()->where('restaurant_branch_id', $user->restaurant_branch_id)->exists()) ||
               ($user->hasRole('BRANCH_MANAGER') && $driver->orderAssignments()->whereHas('order', function ($query) use ($user) { $query->where('restaurant_branch_id', $user->restaurant_branch_id); })->exists());
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Driver $driver): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Driver $driver): bool
    {
        return $user->isSuperAdmin();
    }
}
