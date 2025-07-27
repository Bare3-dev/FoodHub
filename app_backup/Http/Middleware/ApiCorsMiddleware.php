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
        if ($this->isOriginAllowed($origin, $allowedOrigins)) {
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
                // More permissive for public endpoints
                return array_merge($baseOrigins, [
                    '*', // Allow all origins for public data
                ]);

            case 'admin':
                // Strict origins for admin endpoints
                return array_filter([
                    env('ADMIN_URL'),
                    env('FRONTEND_URL'), // In case admin is part of main app
                    ...$this->getDevelopmentOrigins(),
                ]);

            case 'private':
            default:
                // Standard origins for authenticated endpoints
                return $baseOrigins;
        }
    }

    /**
     * Get base allowed origins from environment
     */
    private function getBaseAllowedOrigins(): array
    {
        if (app()->environment('production')) {
            return array_filter([
                env('FRONTEND_URL'),
                env('ADMIN_URL'),
                ...array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
            ]);
        }

        return array_merge(
            $this->getDevelopmentOrigins(),
            array_filter([
                env('FRONTEND_URL', 'http://localhost:3000'),
                env('ADMIN_URL'),
                ...array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
            ])
        );
    }

    /**
     * Get development origins
     */
    private function getDevelopmentOrigins(): array
    {
        return [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://localhost:4200',
            'http://localhost:8080',
            'http://localhost:8100',
            'http://localhost:3001',
            'http://localhost:8000',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:4200',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:8100',
        ];
    }

    /**
     * Get allowed headers based on CORS type
     */
    private function getAllowedHeaders(string $corsType): array
    {
        $baseHeaders = [
            'Accept',
            'Content-Type',
            'Origin',
            'Cache-Control',
            'Pragma',
        ];

        if ($corsType !== 'public') {
            $baseHeaders = array_merge($baseHeaders, [
                'Authorization',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-XSRF-TOKEN',
            ]);
        }

        return $baseHeaders;
    }

    /**
     * Get allowed methods based on CORS type
     */
    private function getAllowedMethods(string $corsType): array
    {
        switch ($corsType) {
            case 'public':
                return ['GET', 'HEAD', 'OPTIONS'];

            case 'admin':
            case 'private':
            default:
                return ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
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

        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        if (in_array($origin, $allowedOrigins)) {
            return true;
        }

        // Check against patterns (for development)
        if (!app()->environment('production')) {
            $patterns = [
                '/^http:\/\/localhost:\d+$/',
                '/^http:\/\/127\.0\.0\.1:\d+$/',
                '/^https?:\/\/.*\.ngrok\.io$/',
                '/^https?:\/\/.*\.ngrok-free\.app$/',
                '/^https?:\/\/.*\.vercel\.app$/',
                '/^https?:\/\/.*\.netlify\.app$/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}
