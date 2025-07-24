<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Restaurant::class => \App\Policies\RestaurantPolicy::class,
        \App\Models\RestaurantBranch::class => \App\Policies\RestaurantBranchPolicy::class,
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Driver::class => \App\Policies\DriverPolicy::class,
        \App\Models\MenuCategory::class => \App\Policies\MenuCategoryPolicy::class,
        \App\Models\MenuItem::class => \App\Policies\MenuItemPolicy::class,
        \App\Models\BranchMenuItem::class => \App\Policies\BranchMenuItemPolicy::class,
        \App\Models\Order::class => \App\Policies\OrderPolicy::class,
        \App\Models\LoyaltyProgram::class => \App\Policies\LoyaltyProgramPolicy::class,
        \App\Models\User::class => \App\Policies\StaffPolicy::class, // For staff management
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        \Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Policies\StaffPolicy::class);
    }
} 