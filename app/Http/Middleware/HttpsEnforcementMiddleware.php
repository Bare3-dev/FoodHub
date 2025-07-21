<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS Enforcement and Security Headers Middleware
 * 
 * Features:
 * - Forces HTTPS redirects in production
 * - Implements comprehensive security headers
 * - HSTS (HTTP Strict Transport Security)
 * - Content Security Policy (CSP)
 * - XSS Protection and MIME type sniffing prevention
 * - Clickjacking protection
 * - Security incident logging
 * 
 * Usage:
 * Applied globally to all API routes for maximum security
 */
class HttpsEnforcementMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Enforce HTTPS in production
        if ($this->shouldEnforceHttps($request)) {
            $secureUrl = $this->getSecureUrl($request);
            
            Log::warning('HTTP request redirected to HTTPS', [
                'original_url' => $request->fullUrl(),
                'secure_url' => $secureUrl,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return redirect($secureUrl, 301);
        }

        $response = $next($request);
        
        // Add comprehensive security headers
        return $this->addSecurityHeaders($response, $request);
    }

    /**
     * Determine if HTTPS should be enforced
     */
    private function shouldEnforceHttps(Request $request): bool
    {
        // Skip HTTPS enforcement in local development
        if (app()->environment('local', 'testing')) {
            return false;
        }

        // Skip if already using HTTPS
        if ($request->isSecure()) {
            return false;
        }

        // Skip if explicitly disabled
        if (config('app.force_https', true) === false) {
            return false;
        }

        // Skip for health check endpoints (load balancers)
        if (in_array($request->path(), ['health', 'ping', 'status'])) {
            return false;
        }

        return true;
    }

    /**
     * Get the secure URL for redirect
     */
    private function getSecureUrl(Request $request): string
    {
        return 'https://' . $request->getHost() . $request->getRequestUri();
    }

    /**
     * Add comprehensive security headers to response
     */
    private function addSecurityHeaders(Response $response, Request $request): Response
    {
        $headers = [
            // HTTPS Strict Transport Security
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            
            // Prevent clickjacking
            'X-Frame-Options' => 'DENY',
            
            // XSS Protection
            'X-XSS-Protection' => '1; mode=block',
            
            // Prevent MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',
            
            // Referrer Policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            
            // Permissions Policy (formerly Feature Policy)
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            
            // Content Security Policy for API
            'Content-Security-Policy' => $this->getContentSecurityPolicy(),
            
            // Server information hiding
            'X-Powered-By' => 'FoodHub-API',
            'Server' => 'FoodHub-Secure',
            
            // Cache control for sensitive data
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        // Apply environment-specific headers
        if (app()->environment('production')) {
            $headers = array_merge($headers, [
                // Additional production security
                'Expect-CT' => 'max-age=86400, enforce',
            ]);
        }

        // Add all headers to response
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Log security header application for monitoring
        $this->logSecurityHeaders($request, $response);

        return $response;
    }

    /**
     * Get Content Security Policy for API endpoints
     */
    private function getContentSecurityPolicy(): string
    {
        $policies = [
            "default-src 'none'",
            "script-src 'none'",
            "style-src 'none'",
            "img-src 'none'",
            "font-src 'none'",
            "connect-src 'self'",
            "media-src 'none'",
            "object-src 'none'",
            "child-src 'none'",
            "frame-src 'none'",
            "worker-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'none'",
            "base-uri 'none'",
            "manifest-src 'none'",
        ];

        return implode('; ', $policies);
    }

    /**
     * Log security headers for monitoring
     */
    private function logSecurityHeaders(Request $request, Response $response): void
    {
        // Only log in production or when security logging is enabled
        if (!app()->environment('production') && !config('app.security_logging', false)) {
            return;
        }

        Log::info('Security headers applied', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'response_status' => $response->getStatusCode(),
            'security_headers' => [
                'hsts' => $response->headers->get('Strict-Transport-Security') ? 'enabled' : 'disabled',
                'xss_protection' => $response->headers->get('X-XSS-Protection') ? 'enabled' : 'disabled',
                'content_type_options' => $response->headers->get('X-Content-Type-Options') ? 'enabled' : 'disabled',
                'frame_options' => $response->headers->get('X-Frame-Options') ? 'enabled' : 'disabled',
                'csp' => $response->headers->get('Content-Security-Policy') ? 'enabled' : 'disabled',
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
