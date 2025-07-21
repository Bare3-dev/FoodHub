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

/*
|--------------------------------------------------------------------------
| API Routes with Comprehensive Security Stack
|--------------------------------------------------------------------------
|
| Routes are organized by access level with a complete security stack:
| - HTTPS Enforcement: Forces secure connections in production
| - Security Headers: HSTS, CSP, XSS protection, clickjacking prevention
| - Input Sanitization: SQL injection, XSS, command injection prevention
| - CORS Configuration: Environment-aware cross-origin policies
| - Rate Limiting: Tier-based traffic control and abuse prevention
| - Authentication: Sanctum token-based security
| - Authorization: Role and permission-based access control
|
*/

// Authentication endpoints (no auth required, strict rate limiting, full security stack)
Route::group(['middleware' => ['https.security', 'input.sanitization', 'api.cors:private']], function () {
    Route::post('/auth/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login'])
        ->middleware('advanced.rate.limit:login');
    Route::post('/auth/mfa/verify', [App\Http\Controllers\Api\Auth\AuthController::class, 'verifyMfa'])
        ->middleware('advanced.rate.limit:mfa_verify');
});

// Public endpoints - No authentication required, permissive CORS, basic security
Route::group(['middleware' => ['https.security', 'input.sanitization', 'api.cors:public', 'advanced.rate.limit:general']], function () {
    // Public restaurant and menu browsing
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show']);
    Route::get('/restaurant-branches', [RestaurantBranchController::class, 'index']);
    Route::get('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'show']);
    
    // Public menu browsing
    Route::get('/menu-categories', [MenuCategoryController::class, 'index']);
    Route::get('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'show']);
    Route::get('/menu-items', [MenuItemController::class, 'index']);
    Route::get('/menu-items/{menuItem}', [MenuItemController::class, 'show']);
    
    // Public nested menu resources
    Route::get('/restaurants/{restaurant}/menu-categories', [MenuCategoryController::class, 'index']);
    Route::get('/restaurants/{restaurant}/menu-categories/{menuCategory}', [MenuCategoryController::class, 'show']);
    Route::get('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'index']);
    Route::get('/restaurants/{restaurant}/menu-items/{menuItem}', [MenuItemController::class, 'show']);
    
    // Public branch menu items
    Route::get('/restaurant-branches/{restaurantBranch}/branch-menu-items', [BranchMenuItemController::class, 'index']);
    Route::get('/restaurant-branches/{restaurantBranch}/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'show']);
});

// Private endpoints - Authentication required, standard CORS, full security stack
Route::group(['middleware' => ['https.security', 'input.sanitization', 'auth:sanctum', 'api.cors:private', 'advanced.rate.limit:general']], function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Logout
    Route::post('/auth/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
    
    // Rate limit monitoring
    Route::get('/rate-limit/status', [App\Http\Controllers\RateLimitController::class, 'status']);
    
    // Customer-facing endpoints
    Route::group(['middleware' => 'role.permission:CUSTOMER'], function () {
        // Customer can view and create orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders', [OrderController::class, 'store']);
        
        // Customer loyalty programs
        Route::get('/loyalty-programs', [LoyaltyProgramController::class, 'index']);
        Route::get('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'show']);
    });
    
    // Staff operational endpoints (not admin functions)
    Route::group(['middleware' => 'role.permission:CASHIER|KITCHEN_STAFF|DELIVERY_MANAGER|CUSTOMER_SERVICE'], function () {
        // Order management
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::put('/orders/{order}', [OrderController::class, 'update']);
        Route::patch('/orders/{order}', [OrderController::class, 'update']);
        
        // Customer service functions
        Route::group(['middleware' => 'role.permission:CUSTOMER_SERVICE|CASHIER'], function () {
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::get('/customers/{customer}', [CustomerController::class, 'show']);
            Route::post('/customers', [CustomerController::class, 'store']); // Cashier can create customers
        });
        
        // Loyalty program operations
        Route::group(['middleware' => 'role.permission:CASHIER:loyalty-program:apply-points|CUSTOMER_SERVICE'], function () {
            Route::get('/loyalty-programs', [LoyaltyProgramController::class, 'index']);
            Route::get('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'show']);
        });
    });
});

// Admin endpoints - Strict authentication + admin roles, admin CORS, maximum security
Route::group(['middleware' => ['https.security', 'input.sanitization', 'auth:sanctum', 'api.cors:admin', 'advanced.rate.limit:general']], function () {
    // Super Admin only endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN'], function () {
        // Rate limit management
        Route::post('/rate-limit/clear', [App\Http\Controllers\RateLimitController::class, 'clear']);
        
        // Restaurant management (create/delete)
        Route::post('/restaurants', [RestaurantController::class, 'store']);
        Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);
        
        // Staff management
        Route::apiResource('staff', StaffController::class);
    });
    
    // Restaurant Owner + Super Admin endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|RESTAURANT_OWNER'], function () {
        // Restaurant management (view/update own restaurants)
        Route::get('/restaurants', [RestaurantController::class, 'index']);
        Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show']);
        Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
        Route::patch('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
        
        // Restaurant branch management
        Route::post('/restaurant-branches', [RestaurantBranchController::class, 'store']);
        Route::delete('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'destroy']);
        
        // Driver management
        Route::apiResource('drivers', DriverController::class)->except(['destroy']);
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy']);
        
        // Staff management (for their restaurants)
        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/staff/{staff}', [StaffController::class, 'show']);
    });
    
    // Branch Manager + Restaurant Owner + Super Admin endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|RESTAURANT_OWNER|BRANCH_MANAGER'], function () {
        // Branch management
        Route::get('/restaurant-branches', [RestaurantBranchController::class, 'index']);
        Route::get('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'show']);
        Route::put('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'update']);
        Route::patch('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'update']);
        
        // Menu management
        Route::post('/menu-categories', [MenuCategoryController::class, 'store']);
        Route::put('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'update']);
        Route::patch('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'update']);
        Route::delete('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'destroy']);
        
        Route::post('/menu-items', [MenuItemController::class, 'store']);
        Route::put('/menu-items/{menuItem}', [MenuItemController::class, 'update']);
        Route::patch('/menu-items/{menuItem}', [MenuItemController::class, 'update']);
        Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy']);
        
        // Branch menu item management
        Route::apiResource('restaurant-branches.branch-menu-items', BranchMenuItemController::class)->except(['index', 'show']);
        
        // Nested restaurant menu management
        Route::apiResource('restaurants.menu-categories', MenuCategoryController::class)->except(['index', 'show']);
        Route::apiResource('restaurants.menu-items', MenuItemController::class)->except(['index', 'show']);
        
        // Staff management for branch
        Route::post('/staff', [StaffController::class, 'store']);
        Route::put('/staff/{staff}', [StaffController::class, 'update']);
        Route::patch('/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
    });
    
    // Delivery Manager specific endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|DELIVERY_MANAGER'], function () {
        Route::apiResource('driver-working-zones', DriverWorkingZoneController::class);
    });
    
    // Customer Service + Super Admin endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|CUSTOMER_SERVICE'], function () {
        // Customer management
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
        Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
        
        // Loyalty program management
        Route::post('/loyalty-programs', [LoyaltyProgramController::class, 'store']);
        Route::put('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'update']);
        Route::patch('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'update']);
        Route::delete('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'destroy']);
    });
});
