<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Middleware\AdvancedRateLimitMiddleware;
use App\Http\Middleware\RoleAndPermissionMiddleware;
use Illuminate\Support\Facades\Route;

// General registration (for basic staff roles)
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

// Role-specific registration routes
Route::post('/register/staff', [RegisteredUserController::class, 'storeStaff'])
    ->middleware(['auth:sanctum', RoleAndPermissionMiddleware::class.':SUPER_ADMIN|RESTAURANT_OWNER|BRANCH_MANAGER'])
    ->name('register.staff');

Route::post('/register/restaurant-owner', [RegisteredUserController::class, 'storeRestaurantOwner'])
    ->middleware(['auth:sanctum', RoleAndPermissionMiddleware::class.':SUPER_ADMIN'])
    ->name('register.restaurant-owner');

Route::post('/register/super-admin', [RegisteredUserController::class, 'storeSuperAdmin'])
    ->middleware(['auth:sanctum', RoleAndPermissionMiddleware::class.':SUPER_ADMIN'])
    ->name('register.super-admin');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', AdvancedRateLimitMiddleware::class.':login'])
    ->name('login');

Route::post('/mfa/verify', [AuthenticatedSessionController::class, 'verifyMfa'])
    ->middleware(['guest', AdvancedRateLimitMiddleware::class.':mfa_verify'])
    ->name('mfa.verify');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest', AdvancedRateLimitMiddleware::class.':password_reset'])
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware(['guest', AdvancedRateLimitMiddleware::class.':password_reset'])
    ->name('password.store');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
