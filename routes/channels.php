<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Customer channels - customers can only access their own channels
Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
    // Check if user is a customer accessing their own channel
    if ($user && $user->id == $customerId) {
        return true;
    }
    
    // Check if user is admin
    if ($user && $user->role === 'SUPER_ADMIN') {
        return true;
    }
    
    return false;
});

// Restaurant channels - restaurant staff can only access their restaurant's channels
Broadcast::channel('restaurant.{restaurantBranchId}', function ($user, $restaurantBranchId) {
    // Check if user is admin
    if ($user && $user->role === 'SUPER_ADMIN') {
        return true;
    }
    
    // Check if user is staff of this restaurant branch
    return $user && $user->restaurant_branch_id == $restaurantBranchId;
});

// Driver channels - drivers can only access their own channels
Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    // Check if user is accessing their own driver channel
    if ($user && $user->id == $driverId) {
        return true;
    }
    
    // Check if user is admin
    if ($user && $user->role === 'SUPER_ADMIN') {
        return true;
    }
    
    return false;
});

// Kitchen display channels - kitchen staff can access their restaurant's kitchen channel
Broadcast::channel('kitchen.{restaurantBranchId}', function ($user, $restaurantBranchId) {
    // Check if user is admin
    if ($user && $user->role === 'SUPER_ADMIN') {
        return true;
    }
    
    // Check if user is kitchen staff of this restaurant
    return $user && 
           $user->restaurant_branch_id == $restaurantBranchId && 
           in_array($user->role, ['KITCHEN_STAFF', 'RESTAURANT_OWNER', 'BRANCH_MANAGER']);
});

// Order-specific channels for real-time updates
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = \App\Models\Order::find($orderId);
    
    if (!$order) {
        return false;
    }
    
    // Admin can access all orders
    if ($user && $user->role === 'SUPER_ADMIN') {
        return true;
    }
    
    // Customer can access their own orders (if they're authenticated)
    if ($user && $user->id == $order->customer_id) {
        return true;
    }
    
    // Restaurant staff can access orders from their restaurant
    if ($user && $user->restaurant_branch_id == $order->restaurant_branch_id) {
        return true;
    }
    
    // Driver can access orders assigned to them
    if ($user && $order->driver_id == $user->id) {
        return true;
    }
    
    return false;
});
