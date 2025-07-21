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
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && !is_null($user->restaurant_id)) ||
               $user->hasRole('CUSTOMER'); // Customers can see available loyalty programs
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LoyaltyProgram $loyaltyProgram): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) ||
               ($user->hasRole('CUSTOMER') && $loyaltyProgram->is_active); // Customers can view active programs
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->hasRole('RESTAURANT_OWNER');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LoyaltyProgram $loyaltyProgram): bool
    {
        return $user->isSuperAdmin() || 
               ($user->hasRole('RESTAURANT_OWNER') && $user->restaurant_id === $loyaltyProgram->restaurant_id) ||
               ($user->hasRole('CUSTOMER_SERVICE') && $user->hasPermission('loyalty-program:adjust-points')) ||
               ($user->hasRole('CASHIER') && $user->hasPermission('loyalty-program:apply-points'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LoyaltyProgram $loyaltyProgram): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LoyaltyProgram $loyaltyProgram): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LoyaltyProgram $loyaltyProgram): bool
    {
        return $user->isSuperAdmin();
    }
}
