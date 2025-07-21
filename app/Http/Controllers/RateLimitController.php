<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RateLimitController extends Controller
{
    /**
     * Get current rate limit status for the authenticated user
     */
    public function status(Request $request): JsonResponse
    {
        $user = Auth::user();
        $ip = $request->ip();
        
        // Determine user tier
        $tier = $this->getUserTier($user);
        
        // Get current limits for different endpoint types
        $endpointTypes = ['general', 'login', 'password_reset', 'mfa_verify', 'mfa_request'];
        $status = [];
        
        foreach ($endpointTypes as $endpointType) {
            $limits = $this->getEndpointLimits($endpointType, $tier);
            $ipStatus = $this->getRateLimitStatus("ip:{$ip}:{$endpointType}", $limits['ip'] ?? null);
            
            $userStatus = null;
            if ($user && isset($limits['user'])) {
                $userStatus = $this->getRateLimitStatus("user:{$user->id}:{$endpointType}", $limits['user']);
            }
            
            $status[$endpointType] = [
                'ip_limits' => $ipStatus,
                'user_limits' => $userStatus,
            ];
        }
        
        return response()->json([
            'user_tier' => $tier,
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'rate_limits' => $status,
        ]);
    }
    
    /**
     * Clear rate limits for the current user (admin only)
     */
    public function clear(Request $request): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'target_type' => 'required|in:ip,user,email',
            'target_value' => 'required|string',
            'endpoint_type' => 'nullable|string',
        ]);
        
        $targetType = $request->input('target_type');
        $targetValue = $request->input('target_value');
        $endpointType = $request->input('endpoint_type', '*');
        
        $cleared = [];
        
        if ($endpointType === '*') {
            $endpointTypes = ['general', 'login', 'password_reset', 'mfa_verify', 'mfa_request'];
        } else {
            $endpointTypes = [$endpointType];
        }
        
        foreach ($endpointTypes as $type) {
            $key = "{$targetType}:{$targetValue}:{$type}";
            $cacheKey = "rate_limit:{$key}";
            $penaltyKey = "penalty:{$key}";
            $currentPenaltyKey = "current_penalty:{$key}";
            
            Cache::forget($cacheKey);
            Cache::forget($penaltyKey);
            Cache::forget($currentPenaltyKey);
            
            $cleared[] = $key;
        }
        
        return response()->json([
            'message' => 'Rate limits cleared successfully',
            'cleared_keys' => $cleared,
        ]);
    }
    
    /**
     * Get rate limit status for a specific key
     */
    private function getRateLimitStatus(string $key, ?array $limitConfig): ?array
    {
        if (!$limitConfig) {
            return null;
        }
        
        $limit = $limitConfig['limit'];
        $window = $limitConfig['window'];
        $requestsKey = "rate_limit:{$key}";
        $requests = Cache::get($requestsKey, []);
        
        $now = Carbon::now();
        $windowStart = $now->copy()->subSeconds($window);
        
        $requestsInWindow = collect($requests)->filter(function ($timestamp) use ($windowStart) {
            return Carbon::parse($timestamp)->isAfter($windowStart);
        })->count();
        
        $remaining = max(0, $limit - $requestsInWindow);
        $resetTime = null;
        
        if (!empty($requests)) {
            $oldestRequest = Carbon::parse(min($requests));
            $resetTime = $oldestRequest->addSeconds($window);
        }
        
        // Check for active penalties
        $penaltyKey = "current_penalty:{$key}";
        $penaltyEndTime = Cache::get($penaltyKey);
        $penaltyActive = $penaltyEndTime && Carbon::now()->isBefore($penaltyEndTime);
        
        return [
            'limit' => $limit,
            'window_seconds' => $window,
            'requests_used' => $requestsInWindow,
            'requests_remaining' => $remaining,
            'reset_time' => $resetTime?->toISOString(),
            'penalty_active' => $penaltyActive,
            'penalty_end_time' => $penaltyActive ? $penaltyEndTime->toISOString() : null,
        ];
    }
    
    /**
     * Determine user tier (duplicated from middleware for consistency)
     */
    private function getUserTier($user): string
    {
        if (!$user) {
            return 'unauthenticated';
        }
        
        if ($user->isSuperAdmin()) {
            return 'super_admin';
        }
        
        if (in_array($user->role ?? '', [
            'RESTAURANT_OWNER', 'BRANCH_MANAGER', 'CASHIER', 
            'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE'
        ])) {
            return 'internal_staff';
        }
        
        return 'customer';
    }
    
    /**
     * Get rate limits configuration (duplicated from middleware for consistency)
     */
    private function getEndpointLimits(string $endpointType, string $tier): array
    {
        $limits = [
            'general' => [
                'unauthenticated' => ['ip' => ['limit' => 15, 'window' => 60], 'user' => null],
                'customer' => ['ip' => ['limit' => 50, 'window' => 60], 'user' => ['limit' => 400, 'window' => 60]],
                'internal_staff' => ['ip' => ['limit' => 100, 'window' => 60], 'user' => ['limit' => 5000, 'window' => 60]],
                'super_admin' => ['ip' => ['limit' => 200, 'window' => 60], 'user' => ['limit' => 10000, 'window' => 60]],
            ],
            'login' => [
                'unauthenticated' => ['ip' => ['limit' => 5, 'window' => 900], 'user' => null],
                'customer' => ['ip' => ['limit' => 5, 'window' => 900], 'user' => ['limit' => 10, 'window' => 900]],
                'internal_staff' => ['ip' => ['limit' => 10, 'window' => 900], 'user' => ['limit' => 20, 'window' => 900]],
                'super_admin' => ['ip' => ['limit' => 20, 'window' => 900], 'user' => ['limit' => 50, 'window' => 900]],
            ],
            'password_reset' => [
                'unauthenticated' => ['ip' => ['limit' => 3, 'window' => 3600], 'user' => null],
                'customer' => ['ip' => ['limit' => 3, 'window' => 3600], 'user' => ['limit' => 5, 'window' => 3600]],
                'internal_staff' => ['ip' => ['limit' => 5, 'window' => 3600], 'user' => ['limit' => 10, 'window' => 3600]],
                'super_admin' => ['ip' => ['limit' => 10, 'window' => 3600], 'user' => ['limit' => 20, 'window' => 3600]],
            ],
            'mfa_verify' => [
                'unauthenticated' => ['ip' => ['limit' => 3, 'window' => 300], 'user' => null],
                'customer' => ['ip' => ['limit' => 5, 'window' => 300], 'user' => ['limit' => 5, 'window' => 300]],
                'internal_staff' => ['ip' => ['limit' => 10, 'window' => 300], 'user' => ['limit' => 10, 'window' => 300]],
                'super_admin' => ['ip' => ['limit' => 20, 'window' => 300], 'user' => ['limit' => 20, 'window' => 300]],
            ],
            'mfa_request' => [
                'unauthenticated' => ['ip' => ['limit' => 2, 'window' => 60], 'user' => null],
                'customer' => ['ip' => ['limit' => 3, 'window' => 60], 'user' => ['limit' => 5, 'window' => 3600]],
                'internal_staff' => ['ip' => ['limit' => 5, 'window' => 60], 'user' => ['limit' => 10, 'window' => 3600]],
                'super_admin' => ['ip' => ['limit' => 10, 'window' => 60], 'user' => ['limit' => 20, 'window' => 3600]],
            ],
        ];
        
        return $limits[$endpointType][$tier] ?? $limits['general'][$tier];
    }
}
