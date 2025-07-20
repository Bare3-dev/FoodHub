<?php

use App\Http\Controllers\Api\BranchMenuItemController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverWorkingZoneController;
use App\Http\Controllers\Api\LoyaltyProgramController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RestaurantBranchController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResources([
    'restaurants' => RestaurantController::class,
    'restaurant-branches' => RestaurantBranchController::class,
    'customers' => CustomerController::class,
    'customer-addresses' => CustomerAddressController::class,
    'drivers' => DriverController::class,
    'driver-working-zones' => DriverWorkingZoneController::class,
    'menu-categories' => MenuCategoryController::class,
    'menu-items' => MenuItemController::class,
    'orders' => OrderController::class,
    'loyalty-programs' => LoyaltyProgramController::class,
    'staff' => StaffController::class,
]);

// Nested resources for menu items under restaurants
Route::apiResource('restaurants.menu-categories', MenuCategoryController::class)->except(['index']);
Route::apiResource('restaurants.menu-items', MenuItemController::class)->except(['index']);

// Nested resources for branch menu items under restaurant branches
Route::apiResource('restaurant-branches.branch-menu-items', BranchMenuItemController::class)->except(['index']);

// Add any specific custom routes below if needed

// Example custom route for customer authentication
// Route::post('/customers/login', [AuthController::class, 'login']);
