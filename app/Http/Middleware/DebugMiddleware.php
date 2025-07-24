<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DebugMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $stage = 'unknown'): Response
    {
        Log::info("Debug Middleware [{$stage}]: Request received", [
            'uri' => $request->getUri(),
            'method' => $request->getMethod(),
            'user_id' => auth()->id(),
            'user_role' => auth()->user()?->role,
            'is_super_admin' => auth()->user()?->isSuperAdmin(),
        ]);

        $response = $next($request);

        Log::info("Debug Middleware [{$stage}]: Response generated", [
            'status_code' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
