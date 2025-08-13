<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        // Register custom channel authorization rules
        $this->registerChannelAuthorization();
    }

    /**
     * Register channel authorization rules
     */
    private function registerChannelAuthorization(): void
    {
        // Customer channels
        Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
            return $user->id == $customerId || $user->hasRole('admin');
        });

        // Restaurant channels
        Broadcast::channel('restaurant.{restaurantBranchId}', function ($user, $restaurantBranchId) {
            if ($user->hasRole('admin')) {
                return true;
            }
            
            return $user->restaurant_branch_id == $restaurantBranchId;
        });

        // Driver channels
        Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
            return $user->id == $driverId || $user->hasRole('admin');
        });

        // Kitchen display channels
        Broadcast::channel('kitchen.{restaurantBranchId}', function ($user, $restaurantBranchId) {
            if ($user->hasRole('admin')) {
                return true;
            }
            
            return $user->restaurant_branch_id == $restaurantBranchId && 
                   ($user->hasRole('kitchen_staff') || $user->hasRole('chef'));
        });

        // Order-specific channels
        Broadcast::channel('order.{orderId}', function ($user, $orderId) {
            $order = \App\Models\Order::find($orderId);
            
            if (!$order) {
                return false;
            }
            
            if ($user->hasRole('admin')) {
                return true;
            }
            
            if ($user->id == $order->customer_id) {
                return true;
            }
            
            if ($user->restaurant_branch_id == $order->restaurant_branch_id) {
                return true;
            }
            
            if ($order->driver_id == $user->id) {
                return true;
            }
            
            return false;
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
