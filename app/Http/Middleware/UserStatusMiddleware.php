<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class UserStatusMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        // Check if user is active - inactive users cannot access any protected endpoints
        if ($user->status !== 'active') {
            \Log::info('UserStatusMiddleware - inactive user blocked', [
                'user_id' => $user->id,
                'status' => $user->status,
                'uri' => $request->getRequestUri()
            ]);
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        return $next($request);
    }
} 