<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API CORS Middleware
 * 
 * Provides granular CORS control for different API endpoint types:
 * - 'public': Permissive CORS for public endpoints (menus, restaurants)
 * - 'private': Strict CORS for authenticated endpoints (orders, payments)
 * - 'admin': Admin-specific CORS for management endpoints
 * 
 * Usage:
 * Route::group(['middleware' => 'api.cors:public'], function () { ... });
 * Route::group(['middleware' => 'api.cors:private'], function () { ... });
 * Route::group(['middleware' => 'api.cors:admin'], function () { ... });
 */
class ApiCorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $corsType
     */
    public function handle(Request $request, Closure $next, string $corsType = 'private'): Response
    {
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request, $corsType);
        }

        $response = $next($request);
        
        return $this->addCorsHeaders($response, $request, $corsType);
    }

    /**
     * Handle preflight OPTIONS requests
     */
    private function handlePreflightRequest(Request $request, string $corsType): Response
    {
        $response = response('', 200);
        return $this->addCorsHeaders($response, $request, $corsType);
    }

    /**
     * Add appropriate CORS headers based on type
     */
    private function addCorsHeaders(Response $response, Request $request, string $corsType): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = $this->getAllowedOrigins($corsType);
        $allowedHeaders = $this->getAllowedHeaders($corsType);
        $allowedMethods = $this->getAllowedMethods($corsType);

        // Check if origin is allowed
        if ($allowedOrigins === ['*']) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } elseif ($this->isOriginAllowed($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        
        if ($corsType !== 'public') {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours

        // Add exposed headers for API responses
        $response->headers->set('Access-Control-Expose-Headers', implode(', ', [
            'X-Pagination-Total',
            'X-Pagination-Per-Page', 
            'X-Pagination-Current-Page',
            'X-Pagination-Last-Page',
            'X-Rate-Limit-Limit',
            'X-Rate-Limit-Remaining',
            'X-Rate-Limit-Reset',
            'Retry-After',
        ]));

        return $response;
    }

    /**
     * Get allowed origins based on CORS type
     */
    private function getAllowedOrigins(string $corsType): array
    {
        $baseOrigins = $this->getBaseAllowedOrigins();

        switch ($corsType) {
            case 'public':
                // More permissive for public endpoints - allow all origins
                return ['*'];
            
            case 'private':
                // Strict for authenticated endpoints
                return array_merge($baseOrigins, [
                    'https://app.foodhub.com',
                    'https://admin.foodhub.com',
                ]);
            
            case 'admin':
                // Admin only
                return array_merge($baseOrigins, [
                    'https://admin.foodhub.com',
                ]);
            
            default:
                return $baseOrigins;
        }
    }

    /**
     * Get base allowed origins (development and testing)
     */
    private function getBaseAllowedOrigins(): array
    {
        $origins = [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
        ];

        // Add development origins
        if (app()->environment('local', 'development')) {
            $origins = array_merge($origins, $this->getDevelopmentOrigins());
        }

        return $origins;
    }

    /**
     * Get development origins
     */
    private function getDevelopmentOrigins(): array
    {
        return [
            'http://localhost:3001',
            'http://localhost:3002',
            'http://localhost:5173', // Vite dev server
            'http://localhost:4173', // Vite preview
        ];
    }

    /**
     * Get allowed headers based on CORS type
     */
    private function getAllowedHeaders(string $corsType): array
    {
        $baseHeaders = [
            'Content-Type',
            'Accept',
            'Authorization',
            'X-Requested-With',
        ];

        switch ($corsType) {
            case 'public':
                return $baseHeaders;
            
            case 'private':
                return array_merge($baseHeaders, [
                    'X-CSRF-TOKEN',
                    'X-API-Key',
                    'X-Client-Version',
                ]);
            
            case 'admin':
                return array_merge($baseHeaders, [
                    'X-CSRF-TOKEN',
                    'X-API-Key',
                    'X-Client-Version',
                    'X-Admin-Token',
                ]);
            
            default:
                return $baseHeaders;
        }
    }

    /**
     * Get allowed methods based on CORS type
     */
    private function getAllowedMethods(string $corsType): array
    {
        switch ($corsType) {
            case 'public':
                return ['GET', 'HEAD', 'OPTIONS'];
            
            case 'private':
                return ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
            
            case 'admin':
                return ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
            
            default:
                return ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'];
        }
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(?string $origin, array $allowedOrigins): bool
    {
        if (!$origin) {
            return false;
        }

        return in_array($origin, $allowedOrigins);
    }
}
