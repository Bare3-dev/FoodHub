<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

/**
 * Advanced Rate Limiting Middleware
 * 
 * Features:
 * - Tier-based rate limiting (Unauthenticated, Customer, Internal Staff, Super Admin)
 * - Sliding window rate limiting for fair traffic distribution
 * - Progressive penalties with increasing lockout times for repeated violations
 * - Both IP-based and user-based limiting
 * - Special handling for security-sensitive endpoints (login, password reset, MFA)
 * - Per-email rate limiting for auth endpoints
 * - Comprehensive logging and monitoring
 * - 429 responses with Retry-After headers
 * 
 * Usage:
 * Route::get('/endpoint')->middleware('advanced.rate.limit:general');
 * Route::post('/login')->middleware('advanced.rate.limit:login');
 * Route::post('/password/reset')->middleware('advanced.rate.limit:password_reset');
 * Route::post('/mfa/verify')->middleware('advanced.rate.limit:mfa_verify');
 */
class AdvancedRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $endpointType
     * @param  int|null  $customLimit
     * @param  int|null  $customWindow
     */
    public function handle(Request $request, Closure $next, string $endpointType = 'general', ?int $customLimit = null, ?int $customWindow = null): Response
    {
        // Skip rate limiting in test environment
        if (app()->environment('testing')) {
            return $next($request);
        }
        
        // Skip rate limiting if disabled or if config is not available
        try {
            if (!config('rate_limiting.enabled', true)) {
                return $next($request);
            }
        } catch (\Exception $e) {
            // If config is not available, skip rate limiting
            return $next($request);
        }

        $user = Auth::user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Determine user tier and limits
        $tier = $this->getUserTier($user);
        $limits = $this->getEndpointLimits($endpointType, $tier, $customLimit, $customWindow);
        
        // Check IP-based limits (always applied)
        $ipResult = $this->checkRateLimit($request, "ip:{$ip}:{$endpointType}", $limits['ip'], $endpointType);
        if ($ipResult !== true) {
            return $ipResult;
        }
        
        // Check user-based limits (for authenticated users)
        if ($user) {
            $userId = $user->id;
            $userResult = $this->checkRateLimit($request, "user:{$userId}:{$endpointType}", $limits['user'], $endpointType);
            if ($userResult !== true) {
                return $userResult;
            }
        }
        
        // Check specific constraints for security-sensitive endpoints
        if (in_array($endpointType, ['login', 'password_reset', 'mfa_verify'])) {
            $specificResult = $this->checkSecurityEndpointLimits($request, $endpointType, $user, $ip);
            if ($specificResult !== true) {
                return $specificResult;
            }
        }
        
        return $next($request);
    }
    
    /**
     * Determine user tier based on authentication status and role
     */
    private function getUserTier($user): string
    {
        if (!$user) {
            return 'unauthenticated';
        }
        
        if ($user->isSuperAdmin()) {
            return 'super_admin';
        }
        
        // All internal staff (RESTAURANT_OWNER, BRANCH_MANAGER, etc.) get Tier 3
        if (in_array($user->role ?? '', [
            'RESTAURANT_OWNER', 'BRANCH_MANAGER', 'CASHIER', 
            'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE'
        ])) {
            return 'internal_staff';
        }
        
        // For any other authenticated users (might be customers accessing via some interface)
        // or users without a defined role, default to customer tier
        return 'customer';
    }
    
    /**
     * Get rate limits based on endpoint type and user tier
     */
    private function getEndpointLimits(string $endpointType, string $tier, ?int $customLimit, ?int $customWindow): array
    {
        $limits = [
            'general' => [
                'unauthenticated' => ['ip' => ['limit' => 15, 'window' => 60], 'user' => null],
                'customer' => ['ip' => ['limit' => 50, 'window' => 60], 'user' => ['limit' => 400, 'window' => 60]],
                'internal_staff' => ['ip' => ['limit' => 100, 'window' => 60], 'user' => ['limit' => 5000, 'window' => 60]],
                'super_admin' => ['ip' => ['limit' => 200, 'window' => 60], 'user' => ['limit' => 10000, 'window' => 60]],
            ],
            'login' => [
                'unauthenticated' => ['ip' => ['limit' => 5, 'window' => 900], 'user' => null], // 5 per 15 min
                'customer' => ['ip' => ['limit' => 5, 'window' => 900], 'user' => ['limit' => 10, 'window' => 900]],
                'internal_staff' => ['ip' => ['limit' => 10, 'window' => 900], 'user' => ['limit' => 20, 'window' => 900]],
                'super_admin' => ['ip' => ['limit' => 20, 'window' => 900], 'user' => ['limit' => 50, 'window' => 900]],
            ],
            'password_reset' => [
                'unauthenticated' => ['ip' => ['limit' => 3, 'window' => 3600], 'user' => null], // 3 per hour
                'customer' => ['ip' => ['limit' => 3, 'window' => 3600], 'user' => ['limit' => 5, 'window' => 3600]],
                'internal_staff' => ['ip' => ['limit' => 5, 'window' => 3600], 'user' => ['limit' => 10, 'window' => 3600]],
                'super_admin' => ['ip' => ['limit' => 10, 'window' => 3600], 'user' => ['limit' => 20, 'window' => 3600]],
            ],
            'mfa_verify' => [
                'unauthenticated' => ['ip' => ['limit' => 3, 'window' => 300], 'user' => null], // 3 per 5 min
                'customer' => ['ip' => ['limit' => 5, 'window' => 300], 'user' => ['limit' => 5, 'window' => 300]],
                'internal_staff' => ['ip' => ['limit' => 10, 'window' => 300], 'user' => ['limit' => 10, 'window' => 300]],
                'super_admin' => ['ip' => ['limit' => 20, 'window' => 300], 'user' => ['limit' => 20, 'window' => 300]],
            ],
            'mfa_request' => [
                'unauthenticated' => ['ip' => ['limit' => 2, 'window' => 60], 'user' => null], // 2 per minute
                'customer' => ['ip' => ['limit' => 3, 'window' => 60], 'user' => ['limit' => 5, 'window' => 3600]], // 5 per hour per user
                'internal_staff' => ['ip' => ['limit' => 5, 'window' => 60], 'user' => ['limit' => 10, 'window' => 3600]],
                'super_admin' => ['ip' => ['limit' => 10, 'window' => 60], 'user' => ['limit' => 20, 'window' => 3600]],
            ],
        ];
        
        $tierLimits = $limits[$endpointType][$tier] ?? $limits['general'][$tier];
        
        // Apply custom limits if provided
        if ($customLimit && $customWindow) {
            if ($tierLimits['ip']) {
                $tierLimits['ip']['limit'] = $customLimit;
                $tierLimits['ip']['window'] = $customWindow;
            }
            if ($tierLimits['user']) {
                $tierLimits['user']['limit'] = $customLimit;
                $tierLimits['user']['window'] = $customWindow;
            }
        }
        
        return $tierLimits;
    }
    
    /**
     * Check rate limit using sliding window
     */
    private function checkRateLimit(Request $request, string $key, ?array $limitConfig, string $endpointType)
    {
        if (!$limitConfig) {
            return true; // No limits for this combination
        }
        
        $limit = $limitConfig['limit'];
        $window = $limitConfig['window'];
        $now = Carbon::now();
        $windowStart = $now->copy()->subSeconds($window);
        
        // Get current requests in the sliding window
        $requestsKey = "rate_limit:{$key}";
        $requests = Cache::get($requestsKey, []);
        
        // Filter requests within the current window (sliding window)
        $requestsInWindow = collect($requests)->filter(function ($timestamp) use ($windowStart) {
            return Carbon::parse($timestamp)->isAfter($windowStart);
        })->values()->toArray();
        
        // Check if limit exceeded
        if (count($requestsInWindow) >= $limit) {
            // Check for progressive penalties
            $penaltyResult = $this->applyProgressivePenalty($request, $key, $endpointType);
            if ($penaltyResult !== true) {
                return $penaltyResult;
            }
            
            // Log rate limit violation
            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'limit' => $limit,
                'window' => $window,
                'requests_count' => count($requestsInWindow),
                'endpoint_type' => $endpointType,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => Auth::id(),
            ]);
            
            $retryAfter = $this->calculateRetryAfter($requestsInWindow, $window);
            
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'limit' => $limit,
                'window' => $window,
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        // Add current request timestamp
        $requestsInWindow[] = $now->toISOString();
        
        // Store updated requests (cache for window duration + buffer)
        Cache::put($requestsKey, $requestsInWindow, $window + 60);
        
        return true;
    }
    
    /**
     * Apply progressive penalties for repeated violations
     */
    private function applyProgressivePenalty(Request $request, string $key, string $endpointType)
    {
        $penaltyKey = "penalty:{$key}";
        $violations = Cache::get($penaltyKey, 0);
        
        // Progressive penalty durations (in seconds)
        $penaltyDurations = [
            1 => 300,    // 5 minutes
            2 => 900,    // 15 minutes  
            3 => 1800,   // 30 minutes
            4 => 3600,   // 1 hour
            5 => 7200,   // 2 hours
        ];
        
        $penaltyDuration = $penaltyDurations[min($violations + 1, 5)] ?? 7200;
        
        // Check if currently under penalty
        $currentPenaltyKey = "current_penalty:{$key}";
        $penaltyEndTime = Cache::get($currentPenaltyKey);
        
        if ($penaltyEndTime && Carbon::now()->isBefore($penaltyEndTime)) {
            $remainingTime = Carbon::now()->diffInSeconds($penaltyEndTime);
            
            Log::warning('Progressive penalty active', [
                'key' => $key,
                'violations' => $violations,
                'penalty_duration' => $penaltyDuration,
                'remaining_time' => $remainingTime,
                'endpoint_type' => $endpointType,
            ]);
            
            return response()->json([
                'message' => 'Account temporarily locked due to repeated violations.',
                'retry_after' => $remainingTime,
                'violations' => $violations,
                'penalty_duration' => $penaltyDuration,
            ], 429)->header('Retry-After', $remainingTime);
        }
        
        // Apply new penalty
        $violations++;
        $penaltyEndTime = Carbon::now()->addSeconds($penaltyDuration);
        
        Cache::put($penaltyKey, $violations, 86400); // Store violations for 24 hours
        Cache::put($currentPenaltyKey, $penaltyEndTime, $penaltyDuration);
        
        Log::warning('Progressive penalty applied', [
            'key' => $key,
            'violations' => $violations,
            'penalty_duration' => $penaltyDuration,
            'penalty_end_time' => $penaltyEndTime,
            'endpoint_type' => $endpointType,
        ]);
        
        return response()->json([
            'message' => 'Too many violations. Account temporarily locked.',
            'retry_after' => $penaltyDuration,
            'violations' => $violations,
            'penalty_duration' => $penaltyDuration,
        ], 429)->header('Retry-After', $penaltyDuration);
    }
    
    /**
     * Check specific limits for security-sensitive endpoints
     */
    private function checkSecurityEndpointLimits(Request $request, string $endpointType, $user, string $ip)
    {
        // Per-email limits for login attempts
        if ($endpointType === 'login' && $request->has('email')) {
            $email = $request->input('email');
            $emailKey = "email:{$email}:login_attempts";
            $emailResult = $this->checkRateLimit($request, $emailKey, ['limit' => 3, 'window' => 300], $endpointType); // 3 per 5 min
            if ($emailResult !== true) {
                return $emailResult;
            }
        }
        
        // Per-email limits for password reset
        if ($endpointType === 'password_reset' && $request->has('email')) {
            $email = $request->input('email');
            $emailKey = "email:{$email}:password_reset";
            $emailResult = $this->checkRateLimit($request, $emailKey, ['limit' => 1, 'window' => 600], $endpointType); // 1 per 10 min
            if ($emailResult !== true) {
                return $emailResult;
            }
        }
        
        return true;
    }
    
    /**
     * Calculate retry after time based on oldest request in window
     */
    private function calculateRetryAfter(array $requests, int $window): int
    {
        if (empty($requests)) {
            return $window;
        }
        
        $oldestRequest = Carbon::parse(min($requests));
        $retryTime = $oldestRequest->addSeconds($window);
        
        return max(1, Carbon::now()->diffInSeconds($retryTime));
    }
}
