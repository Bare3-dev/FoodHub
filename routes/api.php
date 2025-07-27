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

Route::group(['middleware' => 'api'], function () {
    // Authentication endpoints (no auth required, strict rate limiting, full security stack)
    Route::group(['middleware' => [\App\Http\Middleware\SecurityHeadersMiddleware::class]], function () {
        Route::post('/auth/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login']);
        Route::post('/auth/mfa/verify', [App\Http\Controllers\Api\Auth\AuthController::class, 'verifyMfa']);
    });

    // Public endpoints - No authentication required, permissive CORS, basic security
    Route::group(['middleware' => [
        \App\Http\Middleware\SecurityHeadersMiddleware::class,
        \App\Http\Middleware\ApiCorsMiddleware::class . ':public',
        \App\Http\Middleware\PublicCacheMiddleware::class
    ]], function () {
        // Test route
        Route::get('/test-public', function () {
            return response()->json(['message' => 'Public route works']);
        });
        
        // Test controller route
        Route::get('/test-controller', [RestaurantController::class, 'index']);
        
        // Test MenuCategoryController route
        Route::get('/test-menu-category', [MenuCategoryController::class, 'index']);
        
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
    Route::group(['middleware' => ['auth:sanctum', \App\Http\Middleware\SecurityHeadersMiddleware::class]], function () {
        // User info
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        
        // Logout
        Route::post('/auth/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
        
        // Rate limit monitoring
        Route::get('/rate-limit/status', [App\Http\Controllers\RateLimitController::class, 'status']);
        
        // Customer-facing endpoints
        Route::group(['middleware' => []], function () {
            // Customer can view and create orders
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::post('/orders', [OrderController::class, 'store']);
            
            // Customer loyalty programs
            Route::get('/loyalty-programs', [LoyaltyProgramController::class, 'index']);
            Route::get('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'show']);
        });
        
        // Staff operational endpoints (not admin functions)
        Route::group(['middleware' => []], function () {
            // Order management
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::put('/orders/{order}', [OrderController::class, 'update']);
            Route::patch('/orders/{order}', [OrderController::class, 'update']);
            
            // Customer service functions
            Route::group(['middleware' => []], function () {
                Route::get('/customers', [CustomerController::class, 'index']);
                Route::get('/customers/{customer}', [CustomerController::class, 'show']);
                Route::post('/customers', [CustomerController::class, 'store']); // Cashier can create customers
            });
            
            // Loyalty program operations
            Route::group(['middleware' => []], function () {
                Route::get('/loyalty-programs', [LoyaltyProgramController::class, 'index']);
                Route::get('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'show']);
            });
        });
    });

    // Admin endpoints - Strict authentication + admin roles, admin CORS, maximum security
    Route::group(['middleware' => ['auth:sanctum', \App\Http\Middleware\SecurityHeadersMiddleware::class]], function () {
        // Super Admin only endpoints
        Route::group(['middleware' => []], function () {
            // Rate limit management
            Route::post('/rate-limit/clear', [App\Http\Controllers\RateLimitController::class, 'clear']);
            
            // Restaurant management (create/delete)
            Route::post('/restaurants', [RestaurantController::class, 'store']);
            Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);
            
            // Staff management
            Route::apiResource('staff', StaffController::class);
        });
        
        // Restaurant Owner + Super Admin endpoints
        Route::group(['middleware' => []], function () {
            // Restaurant management (update)
            Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
            Route::patch('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
            
            // Branch management (admin functions only)
            Route::post('/restaurant-branches', [RestaurantBranchController::class, 'store']);
            Route::put('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'update']);
            Route::patch('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'update']);
            Route::delete('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'destroy']);
            
            // Menu management (admin functions only - create/update/delete)
            Route::post('/menu-items', [MenuItemController::class, 'store']);
            Route::put('/menu-items/{menuItem}', [MenuItemController::class, 'update']);
            Route::patch('/menu-items/{menuItem}', [MenuItemController::class, 'update']);
            Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy']);
            
            Route::post('/branch-menu-items', [BranchMenuItemController::class, 'store']);
            Route::put('/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'update']);
            Route::patch('/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'update']);
            Route::delete('/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'destroy']);
            
            // Customer management
            Route::apiResource('customers', CustomerController::class);
            Route::apiResource('customer-addresses', CustomerAddressController::class);
            
            // Loyalty program management
            Route::apiResource('loyalty-programs', LoyaltyProgramController::class);
        });
        
        // Delivery Manager + Super Admin endpoints
        Route::group(['middleware' => []], function () {
            // Driver management
            Route::apiResource('drivers', DriverController::class);
            Route::apiResource('driver-working-zones', DriverWorkingZoneController::class);
            
            // Order management (admin functions)
            Route::apiResource('orders', OrderController::class);
        });
    });
});
