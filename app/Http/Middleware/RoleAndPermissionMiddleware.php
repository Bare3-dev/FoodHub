<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

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

        // Always allow SUPER_ADMIN to bypass all checks
        if ($user->role === 'SUPER_ADMIN') {
            \Log::info('RoleAndPermissionMiddleware SUPER_ADMIN bypass', ['uri' => $request->getRequestUri()]);
            return $next($request);
        }

        // Handle Super Admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $requiredRoles = $roles ? explode('|', $roles) : [];
        $requiredPermissions = $permissions ? explode('|', $permissions) : [];

        $hasRole = empty($requiredRoles) || collect($requiredRoles)->contains(fn ($role) => $user->hasRole($role));
        $hasPermission = empty($requiredPermissions) || collect($requiredPermissions)->every(fn ($permission) => $user->hasPermission($permission));

        if ($hasRole && $hasPermission) {
            return $next($request);
        }
        
        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }
}
