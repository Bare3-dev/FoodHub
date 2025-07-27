<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public Cache Middleware
 * 
 * Adds appropriate cache headers for public API endpoints
 * to improve performance and reduce server load.
 */
class PublicCacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add cache headers for successful GET requests
        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            // Add cache headers for public endpoints
            $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
            $response->headers->set('ETag', md5($response->getContent()));
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        }

        return $response;
    }
} 