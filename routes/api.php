<?php

use App\Http\Controllers\Api\BranchMenuItemController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerLoyaltyPointsController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverWorkingZoneController;
use App\Http\Controllers\Api\LoyaltyProgramController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderSpecialRequestController;
use App\Http\Controllers\Api\RestaurantBranchController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\SpinWheelController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StampCardController;
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
});

// MFA verification - accessible without authentication (part of login flow)
Route::post('/auth/mfa/verify', [App\Http\Controllers\Api\Auth\AuthController::class, 'verifyMfa'])
    ->middleware(['https.security', 'input.sanitization', 'api.cors:private', 'advanced.rate.limit:mfa_verify']);

// Public endpoints - No authentication required, permissive CORS, basic security
Route::group(['middleware' => ['https.security', 'input.sanitization', 'api.cors:public', 'advanced.rate.limit:general']], function () {
    // Public restaurant and menu browsing
    Route::get('/restaurants', [RestaurantController::class, 'index'])->middleware('cache.headers:restaurants');
    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->middleware('cache.headers:restaurants');
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
    
    // Customer feedback endpoint - accessible by customers and staff
    Route::post('/customer/feedback', [App\Http\Controllers\Api\CustomerFeedbackController::class, 'store']);
    
    // Customer support tickets - accessible by customers and staff
    Route::post('/customer/support-tickets', [CustomerController::class, 'storeSupportTicket']);
});

// Private endpoints - Authentication required, standard CORS, full security stack
Route::group(['middleware' => ['https.security', 'input.sanitization', 'auth:sanctum', \App\Http\Middleware\UserStatusMiddleware::class, 'api.cors:private', 'advanced.rate.limit:general']], function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Logout
    Route::post('/auth/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
    
    // Rate limit monitoring
    Route::get('/rate-limit/status', [App\Http\Controllers\RateLimitController::class, 'status']);
    
    // Spin wheel management - accessible by customers and staff
    Route::group(['middleware' => 'role.permission:CUSTOMER|CASHIER|CUSTOMER_SERVICE'], function () {
        Route::get('/spin-wheel/status', [SpinWheelController::class, 'getStatus']);
        Route::post('/spin-wheel/spin', [SpinWheelController::class, 'spin']);
        Route::post('/spin-wheel/buy-spins', [SpinWheelController::class, 'buySpins']);
        Route::get('/spin-wheel/redeemable-prizes', [SpinWheelController::class, 'getRedeemablePrizes']);
        Route::post('/spin-wheel/redeem-prize', [SpinWheelController::class, 'redeemPrize']);
        Route::get('/spin-wheel/configuration', [SpinWheelController::class, 'getConfiguration']);
    });
    
            // Order management - accessible by staff (customers use separate Customer model/auth)
        Route::group(['middleware' => 'role.permission:CASHIER|KITCHEN_STAFF|DELIVERY_MANAGER|CUSTOMER_SERVICE'], function () {
            // Order viewing and creation (all roles)
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            
            // Order creation (customers and staff)
            Route::post('/orders', [OrderController::class, 'store']);
            
            // Order updates (staff only) - will be controlled by policy/controller logic
            Route::put('/orders/{order}', [OrderController::class, 'update']);
            Route::patch('/orders/{order}', [OrderController::class, 'update']);
            
            // Special requests for orders
            Route::post('/orders/special-requests', [OrderSpecialRequestController::class, 'store']);
        });
    
    // Customer loyalty programs - NOTE: Real customers use separate Customer model
    // This endpoint is for staff testing/admin purposes only
    // (Moved to operational endpoints section below to avoid duplication)
    
    // Staff operational endpoints (not admin functions)
    Route::group(['middleware' => 'role.permission:CASHIER|KITCHEN_STAFF|DELIVERY_MANAGER|CUSTOMER_SERVICE'], function () {
        
        // Customer service functions
        Route::group(['middleware' => 'role.permission:CUSTOMER_SERVICE|CASHIER'], function () {
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::get('/customers/{customer}', [CustomerController::class, 'show']);
            Route::post('/customers', [CustomerController::class, 'store']); // Cashier can create customers
            Route::put('/customers/{customer}/preferences', [CustomerController::class, 'updatePreferences']);
            // Add nested resource for customer addresses
            Route::apiResource('customers.addresses', CustomerAddressController::class);
        });
        
        // Loyalty program operations - both CASHIER and CUSTOMER_SERVICE can access
        // (Specific permission checks handled in controller/policy)
        Route::group(['middleware' => 'role.permission:CASHIER|CUSTOMER_SERVICE'], function () {
            Route::get('/loyalty-programs', [LoyaltyProgramController::class, 'index']);
            Route::get('/loyalty-programs/{loyaltyProgram}', [LoyaltyProgramController::class, 'show']);
            
            // Customer loyalty points management
            Route::get('/customer-loyalty-points/summary', [CustomerLoyaltyPointsController::class, 'summary']);
            Route::post('/customer-loyalty-points/earn-points', [CustomerLoyaltyPointsController::class, 'earnPoints']);
            Route::post('/customer-loyalty-points/redeem-points', [CustomerLoyaltyPointsController::class, 'redeemPoints']);
            Route::post('/customer-loyalty-points/process-expiration', [CustomerLoyaltyPointsController::class, 'processExpiration']);
            Route::apiResource('customer-loyalty-points', CustomerLoyaltyPointsController::class);
            
            // Stamp card management
            Route::get('/stamp-cards', [StampCardController::class, 'index']);
            Route::get('/stamp-cards/{stampCard}', [StampCardController::class, 'show']);
            Route::post('/stamp-cards', [StampCardController::class, 'store']);
            Route::get('/stamp-cards/types', [StampCardController::class, 'getCardTypes']);
            Route::get('/stamp-cards/statistics', [StampCardController::class, 'statistics']);
        });

        // Customer service endpoints - staff only
        Route::group(['middleware' => 'role.permission:CUSTOMER_SERVICE'], function () {
            Route::post('/customer-service/complaints', [App\Http\Controllers\Api\CustomerServiceController::class, 'storeComplaint']);
            Route::post('/customer-service/interactions', [App\Http\Controllers\Api\CustomerServiceController::class, 'storeInteraction']);
            Route::post('/customer-service/refunds', [App\Http\Controllers\Api\CustomerServiceController::class, 'storeRefund']);
            Route::post('/customer-service/compensations', [App\Http\Controllers\Api\CustomerServiceController::class, 'storeCompensation']);
            Route::post('/customer-service/activities', [App\Http\Controllers\Api\CustomerServiceController::class, 'storeActivity']);
            Route::get('/customer-service/satisfaction-metrics', [App\Http\Controllers\Api\CustomerServiceController::class, 'getSatisfactionMetrics']);
        });
    });
});

// Admin endpoints - Strict authentication + admin roles, admin CORS, maximum security
Route::group(['middleware' => ['auth:sanctum', \App\Http\Middleware\UserStatusMiddleware::class, 'https.security']], function () {
    // Super Admin only endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN'], function () {
        // Rate limit management
        Route::post('/rate-limit/clear', [App\Http\Controllers\RateLimitController::class, 'clear']);
        
        // Restaurant management (create/delete)
        Route::post('/restaurants', [RestaurantController::class, 'store']);
        Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);
        
        // Order management (delete only)
        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
    });
    
    // Staff management - accessible by Super Admin, Restaurant Owners, and Branch Managers
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|RESTAURANT_OWNER|BRANCH_MANAGER'], function () {
        Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'user']);
    });
    
    // Restaurant Owner + Super Admin endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|RESTAURANT_OWNER'], function () {
        // Restaurant management (view/update own restaurants)
        // Note: GET /restaurants is handled by the public route with proper access control in controller
        Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
        Route::patch('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
        
        // Restaurant branch management
        Route::post('/restaurant-branches', [RestaurantBranchController::class, 'store']);
        Route::delete('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'destroy']);
        
        // Staff management (for their restaurants) - Note: Full CRUD handled by SUPER_ADMIN apiResource above
    });
    
    // Driver management - accessible by Super Admin, Restaurant Owners, and Delivery Managers
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|RESTAURANT_OWNER|DELIVERY_MANAGER'], function () {
        Route::apiResource('drivers', DriverController::class)->except(['destroy']);
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy']);
    });
    
    // Branch Manager + Restaurant Owner + Super Admin endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|RESTAURANT_OWNER|BRANCH_MANAGER'], function () {
        // Branch management
        // Route::get('/restaurant-branches', [RestaurantBranchController::class, 'index']); // Duplicate - public route exists
        // Route::get('/restaurant-branches/{restaurantBranch}', [RestaurantBranchController::class, 'show']); // Duplicate - public route exists
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
        Route::post('/restaurant-branches/{restaurantBranch}/branch-menu-items', [BranchMenuItemController::class, 'store']);
        Route::put('/restaurant-branches/{restaurantBranch}/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'update']);
        Route::patch('/restaurant-branches/{restaurantBranch}/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'update']);
        Route::delete('/restaurant-branches/{restaurantBranch}/branch-menu-items/{branchMenuItem}', [BranchMenuItemController::class, 'destroy']);
        
        // Nested restaurant menu management
        Route::apiResource('restaurants.menu-categories', MenuCategoryController::class)->except(['index', 'show']);
        Route::apiResource('restaurants.menu-items', MenuItemController::class)->except(['index', 'show']);
        
        // Staff management for branch - Note: Full CRUD handled by SUPER_ADMIN apiResource above
    });
    
    // Delivery Manager specific endpoints
    Route::group(['middleware' => 'role.permission:SUPER_ADMIN|DELIVERY_MANAGER'], function () {
        Route::apiResource('driver-working-zones', DriverWorkingZoneController::class);
        
        // Additional driver working zone functionality
        Route::post('/driver-working-zones/optimize-route', [DriverWorkingZoneController::class, 'optimizeRoute']);
        Route::post('/driver-working-zones/assign-driver', [DriverWorkingZoneController::class, 'assignDriver']);
        Route::post('/driver-working-zones/calculate-delivery-time', [DriverWorkingZoneController::class, 'calculateDeliveryTime']);
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

// Minimal test route for cache header debugging
Route::get('/cache-test', function() {
    $response = response()->json(['test' => true, 'time' => now()]);
    $response->headers->set('Cache-Control', 'public, max-age=3600');
    return $response;
});


