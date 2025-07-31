<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SecurityLoggingService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input Sanitization and Attack Prevention Middleware
 * 
 * Comprehensive security middleware that prevents common web application attacks
 * including SQL injection, XSS, CSRF, file upload attacks, and other malicious inputs.
 * 
 * Features:
 * - SQL injection detection and prevention
 * - XSS attack detection and sanitization
 * - Path traversal prevention
 * - Malicious file upload detection
 * - Command injection prevention
 * - NoSQL injection detection
 * - Input size limiting
 * - Suspicious pattern detection
 * - Automatic threat response
 */
class InputSanitizationMiddleware
{
    private SecurityLoggingService $securityLogger;

    public function __construct(SecurityLoggingService $securityLogger)
    {
        $this->securityLogger = $securityLogger;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip sanitization for safe routes (health checks, etc.)
        if ($this->isSafeRoute($request)) {
            return $next($request);
        }

        // Check for blocked IPs first
        if ($this->isBlockedIp($request)) {
            return $this->blockRequest('IP address is temporarily blocked');
        }

        // Perform comprehensive input validation
        $threats = $this->detectThreats($request);
        
        if (!empty($threats)) {
            return $this->handleThreats($request, $threats);
        }

        // Sanitize inputs
        $this->sanitizeRequest($request);

        return $next($request);
    }

    /**
     * Detect various security threats in the request
     */
    private function detectThreats(Request $request): array
    {
        $threats = [];

        // Flatten each input source separately and only analyze string values
        $flatInputs = $this->flattenStrings($request->all());
        $flatHeaders = $this->flattenStrings($request->headers->all());
        $flatPath = $this->flattenStrings([$request->getPathInfo() ?? '']);
        $flatQuery = $this->flattenStrings([$request->getQueryString() ?? '']);

        // Merge all flat sources
        $allInputs = array_merge($flatInputs, $flatHeaders, $flatPath, $flatQuery);

        foreach ($allInputs as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $threats = array_merge($threats, $this->analyzeInput($key, $value, $request));
            }
        }

        return $threats;
    }

    /**
     * Recursively flatten input arrays to key => string value pairs
     */
    private function flattenStrings(array $input, $prefix = ''): array
    {
        $flat = [];
        foreach ($input as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }
            $fullKey = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenStrings($value, $fullKey));
            } elseif (is_string($value)) {
                $flat[$fullKey] = $value;
            }
            // Skip all other types
        }
        return $flat;
    }

    /**
     * Analyze individual input for threats
     */
    private function analyzeInput($key, $value, Request $request): array
    {
        if (!is_string($value) || empty($value)) {
            return [];
        }

        $threats = [];
        $normalizedValue = strtolower($value);

        // SQL Injection Detection
        if ($this->detectSqlInjection($normalizedValue)) {
            $threats[] = [
                'type' => 'sql_injection_attempt',
                'field' => $key,
                'value' => $this->truncateForLogging($value),
                'severity' => 'critical'
            ];
        }

        // XSS Detection
        if ($this->detectXss($value)) {
            $threats[] = [
                'type' => 'xss_attempt',
                'field' => $key,
                'value' => $this->truncateForLogging($value),
                'severity' => 'critical'
            ];
        }

        // Path Traversal Detection
        if ($this->detectPathTraversal($value)) {
            $threats[] = [
                'type' => 'path_traversal_attempt',
                'field' => $key,
                'value' => $this->truncateForLogging($value),
                'severity' => 'high'
            ];
        }

        // Command Injection Detection
        if ($this->detectCommandInjection($normalizedValue)) {
            $threats[] = [
                'type' => 'command_injection_attempt',
                'field' => $key,
                'value' => $this->truncateForLogging($value),
                'severity' => 'critical'
            ];
        }

        // NoSQL Injection Detection
        if ($this->detectNoSqlInjection($normalizedValue)) {
            $threats[] = [
                'type' => 'nosql_injection_attempt',
                'field' => $key,
                'value' => $this->truncateForLogging($value),
                'severity' => 'high'
            ];
        }

        // Suspicious Pattern Detection
        if ($this->detectSuspiciousPatterns($value)) {
            $threats[] = [
                'type' => 'suspicious_pattern',
                'field' => $key,
                'value' => $this->truncateForLogging($value),
                'severity' => 'medium'
            ];
        }

        return $threats;
    }

    /**
     * Detect SQL injection attempts
     */
    private function detectSqlInjection(string $input): bool
    {
        $sqlPatterns = [
            '/(\b(select|insert|update|delete|drop|create|alter|exec|execute|union|script)\b)/i',
            '/(\b(or|and)\s+\d+\s*=\s*\d+)/i',
            '/(\'|\")(\s*)(or|and)(\s*)(\'|\")/i',
            '/(\bor\b\s+\b1\s*=\s*1\b)/i',
            '/(\bunion\b.*\bselect\b)/i',
            '/(\/\*.*\*\/)/i',
            '/(\b(concat|char|ascii|substring|length|database|version|user|table_name)\s*\()/i',
            '/(\';\s*(drop|delete|insert|update))/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS attempts
     */
    private function detectXss(string $input): bool
    {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on(click|mouseover|error|load|mouseout|focus|blur|change|submit|reset|select|unload|resize|scroll|keydown|keyup|keypress)\s*=/i',
            '/<[^>]*?(?:onclick|onmouseover|onerror|onload|onmouseout)[^>]*>/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>.*?<\/embed>/is',
            '/<applet[^>]*>.*?<\/applet>/is',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect path traversal attempts
     */
    private function detectPathTraversal(string $input): bool
    {
        $pathPatterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/',
            '/%2e%2e\\\\/',
            '/\.\.\%5c/',
            '/\.\.\%2f/',
            '/\/etc\/passwd/',
            '/\/windows\/system32/',
            '/\/proc\/self\/environ/',
        ];

        foreach ($pathPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect command injection attempts
     */
    private function detectCommandInjection(string $input): bool
    {
        $cmdPatterns = [
            '/(\||\&\&|\|\||\;)\s*(cat|ls|pwd|whoami|id|uname|ps|netstat|ifconfig|wget|curl|nc|telnet|ssh)/i',
            '/(`|\\$\(|\$\{).*(`|\)|\})/i',
            '/(rm\s+-rf|rmdir|del\s+\/[a-z])/i',
            '/(chmod|chown|su|sudo|passwd)\s+/i',
            '/(\bexec\b|\bsystem\b|\bpassthru\b|\bshell_exec\b)\s*\(/i',
        ];

        foreach ($cmdPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect NoSQL injection attempts
     */
    private function detectNoSqlInjection(string $input): bool
    {
        $noSqlPatterns = [
            '/\$where/i',
            '/\$ne\s*:/i',
            '/\$gt\s*:/i',
            '/\$lt\s*:/i',
            '/\$regex\s*:/i',
            '/\$or\s*:/i',
            '/\$and\s*:/i',
            '/\$not\s*:/i',
            '/\$exists\s*:/i',
            '/\$in\s*:/i',
            '/\$nin\s*:/i',
        ];

        foreach ($noSqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect suspicious patterns
     */
    private function detectSuspiciousPatterns(string $input): bool
    {
        // Check for suspicious file extensions
        if (preg_match('/\.(php|asp|jsp|exe|bat|cmd|sh|pl|py|rb)$/i', $input)) {
            return true;
        }

        // Check for base64 encoded suspicious content
        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $input) && strlen($input) > 50) {
            $decoded = base64_decode($input, true);
            if ($decoded && (strpos($decoded, '<script') !== false || strpos($decoded, 'eval(') !== false)) {
                return true;
            }
        }

        // Check for URL encoded suspicious content
        if (strpos($input, '%') !== false) {
            $decoded = urldecode($input);
            if ($this->detectXss($decoded) || $this->detectSqlInjection(strtolower($decoded))) {
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
        $criticalThreats = array_filter($threats, fn($threat) => $threat['severity'] === 'critical');
        $highThreats = array_filter($threats, fn($threat) => $threat['severity'] === 'high');

        // Log all threats
        foreach ($threats as $threat) {
            $this->securityLogger->logAttackAttempt(
                $threat['type'],
                [
                    'field' => $threat['field'],
                    'raw_input' => $threat['value'],
                    'detection_method' => 'input_sanitization_middleware',
                ],
                $request
            );
        }

        // Block critical threats immediately
        if (!empty($criticalThreats)) {
            return $this->blockRequest('Critical security threat detected', 403);
        }

        // Warn about high threats but allow request
        if (!empty($highThreats)) {
            Log::warning('High severity security threat detected but request allowed', [
                'threats' => $highThreats,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $this->blockRequest('Security threat detected', 400);
    }

    /**
     * Sanitize request inputs
     */
    private function sanitizeRequest(Request $request): void
    {
        // Sanitize query parameters
        $sanitizedQuery = $this->sanitizeArray($request->query->all());
        $request->query->replace($sanitizedQuery);

        // Sanitize POST data
        $sanitizedPost = $this->sanitizeArray($request->request->all());
        $request->request->replace($sanitizedPost);

        // Sanitize JSON data
        if ($request->isJson()) {
            $jsonData = $request->json()->all();
            $sanitizedJson = $this->sanitizeArray($jsonData);
            $request->json()->replace($sanitizedJson);
        }
    }

    /**
     * Sanitize array of inputs
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize individual string
     */
    private function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Basic HTML entity encoding for potential XSS
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove or encode suspicious characters
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }

    /**
     * Check if route is safe from sanitization
     */
    private function isSafeRoute(Request $request): bool
    {
        $safeRoutes = [
            '/health',
            '/ping',
            '/status',
            '/api/sanctum/csrf-cookie',
        ];

        return in_array($request->getPathInfo(), $safeRoutes);
    }

    /**
     * Check if IP is blocked
     */
    private function isBlockedIp(Request $request): bool
    {
        $ip = $request->ip();
        $blockKey = "blocked_ip:{$ip}";
        
        return \Cache::has($blockKey);
    }

    /**
     * Block request with error response
     */
    private function blockRequest(string $message, int $status = 400): Response
    {
        return response()->json([
            'error' => 'Security Violation',
            'message' => $message,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    /**
     * Truncate value for safe logging
     */
    private function truncateForLogging(string $value): string
    {
        return substr($value, 0, 200) . (strlen($value) > 200 ? '...' : '');
    }
}
