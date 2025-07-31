<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\SecurityLog;

class RoleAndPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $roles
     * @param  string|null  $permissions
     */
    public function handle(Request $request, Closure $next, ?string $roles = null, ?string $permissions = null): Response
    {
        \Log::info('RoleAndPermissionMiddleware@handle called', ['uri' => $request->getRequestUri()]);
        
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        // Debug logging for testing
        if (app()->environment('testing')) {
            \Log::info('User details', [
                'user_id' => $user->id,
                'role' => $user->role,
                'email' => $user->email,
                'status' => $user->status,
                'is_super_admin' => $user->isSuperAdmin(),
            ]);
        }

        // Always allow SUPER_ADMIN to bypass all checks
        if ($user->isSuperAdmin()) {
            \Log::info('RoleAndPermissionMiddleware SUPER_ADMIN bypass', ['uri' => $request->getRequestUri()]);
            return $next($request);
        }

        $requiredRoles = $roles ? explode('|', $roles) : [];
        $requiredPermissions = $permissions ? explode('|', $permissions) : [];

        // Check if user has access to any of the required roles using role hierarchy
        $hasRole = empty($requiredRoles) || collect($requiredRoles)->contains(function ($role) use ($user) {
            return $user->canAccessRole($role);
        });
        
        // Check if user has all required permissions (if any are specified)
        $hasPermission = empty($requiredPermissions) || collect($requiredPermissions)->every(fn ($permission) => $user->hasPermission($permission));

        // User must have at least one required role AND all required permissions (if any)
        if ($hasRole && $hasPermission) {
            return $next($request);
        }
        
        // Log authorization failure
        SecurityLog::logEvent(
            'authorization_failed',
            $user->id,
            $request->ip(),
            $request->userAgent(),
            session()->getId(),
            [
                'required_roles' => $requiredRoles,
                'required_permissions' => $requiredPermissions,
                'user_role' => $user->role,
                'user_permissions' => $user->permissions,
                'uri' => $request->getRequestUri(),
                'method' => $request->method(),
                'has_role' => $hasRole,
                'has_permission' => $hasPermission,
            ]
        );
        
        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }
}
