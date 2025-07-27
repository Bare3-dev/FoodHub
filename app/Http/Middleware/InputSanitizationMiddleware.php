<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input Sanitization and Attack Prevention Middleware
 * 
 * Basic security middleware that prevents common web application attacks
 * including SQL injection, XSS, and other malicious inputs.
 */
class InputSanitizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip sanitization for safe routes
        if ($this->isSafeRoute($request)) {
            return $next($request);
        }

        // Perform basic input validation
        $threats = $this->detectThreats($request);
        
        if (!empty($threats)) {
            return $this->handleThreats($request, $threats);
        }

        // Sanitize inputs
        $this->sanitizeRequest($request);

        return $next($request);
    }

    /**
     * Detect basic security threats in the request
     */
    private function detectThreats(Request $request): array
    {
        $threats = [];

        // Check all input sources
        $allInputs = array_merge(
            $request->all(),
            [$request->getPathInfo()],
            [$request->getQueryString()]
        );

        foreach ($allInputs as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if ($this->isSuspicious($subValue)) {
                        $threats[] = "Suspicious input detected in {$key}.{$subKey}";
                    }
                }
            } else {
                if ($this->isSuspicious($value)) {
                    $threats[] = "Suspicious input detected in {$key}";
                }
            }
        }

        return array_unique($threats);
    }

    /**
     * Check if input contains suspicious patterns
     */
    private function isSuspicious($input): bool
    {
        if (!is_string($input) || empty($input)) {
            return false;
        }

        $suspiciousPatterns = [
            // SQL Injection patterns
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b/i',
            '/[\'";]+\s*(union|select|insert|update|delete|drop|create|alter|exec|execute)/i',
            
            // XSS patterns
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            
            // Path traversal
            '/\.\.\//',
            '/\.\.\\\/',
            
            // Command injection
            '/[;&|`$()]/',
            
            // NoSQL injection
            '/\$where/i',
            '/\$ne/i',
            '/\$gt/i',
            '/\$lt/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle detected threats
     */
    private function handleThreats(Request $request, array $threats): Response
    {
        $message = 'Security threat detected: ' . implode(', ', $threats);
        
        Log::warning('Security threat detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'threats' => $threats,
        ]);

        return response()->json([
            'error' => 'Invalid input detected',
            'message' => 'Request blocked for security reasons'
        ], 400);
    }

    /**
     * Sanitize request inputs
     */
    private function sanitizeRequest(Request $request): void
    {
        $inputs = $request->all();
        $sanitized = $this->sanitizeArray($inputs);
        
        // Replace request inputs with sanitized versions
        $request->replace($sanitized);
    }

    /**
     * Sanitize array recursively
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeString($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString($input): string
    {
        if (!is_string($input)) {
            return $input;
        }

        // Basic sanitization
        $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $sanitized = strip_tags($sanitized);
        $sanitized = trim($sanitized);
        
        return $sanitized;
    }

    /**
     * Check if route is safe from sanitization
     */
    private function isSafeRoute(Request $request): bool
    {
        $safeRoutes = [
            'health',
            'ping',
            'status',
            'metrics',
        ];

        return in_array($request->path(), $safeRoutes);
    }
}
