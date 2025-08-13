<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\ApiVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VersionAnalyticsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        // Continue with the request
        $response = $next($request);
        
        // Get version info from request (set by ApiVersionMiddleware)
        $apiVersion = $request->get('api_version');
        if (!$apiVersion || !($apiVersion instanceof ApiVersion)) {
            return $response;
        }
        
        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        // Track analytics data
        $this->trackVersionUsage($request, $apiVersion, $response, $responseTime);
        
        return $response;
    }
    
    /**
     * Track comprehensive version usage analytics
     */
    private function trackVersionUsage(Request $request, ApiVersion $apiVersion, Response $response, float $responseTime): void
    {
        $analyticsData = [
            'version' => $apiVersion->version,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'restaurant_id' => $this->extractRestaurantId($request),
            'response_time_ms' => round($responseTime, 2),
            'response_status' => $response->getStatusCode(),
            'is_error' => $response->getStatusCode() >= 400,
            'timestamp' => now()->toISOString(),
            'request_size' => $this->getRequestSize($request),
            'response_size' => $this->getResponseSize($response),
            'user_id' => $this->extractUserId($request),
            'rate_limit_remaining' => $this->getRateLimitInfo($request),
        ];
        
        // Store in cache for batch processing
        $this->storeAnalyticsData($analyticsData);
        
        // Log for debugging
        Log::info('API version analytics', $analyticsData);
        
        // Update real-time metrics
        $this->updateRealTimeMetrics($apiVersion, $analyticsData);
    }
    
    /**
     * Store analytics data in cache for batch processing
     */
    private function storeAnalyticsData(array $data): void
    {
        $key = 'api.analytics.' . date('Y-m-d-H');
        $analytics = Cache::get($key, []);
        $analytics[] = $data;
        
        // Limit cache size to prevent memory issues
        if (count($analytics) > 1000) {
            array_shift($analytics);
        }
        
        Cache::put($key, $analytics, 3600);
    }
    
    /**
     * Update real-time metrics for the version
     */
    private function updateRealTimeMetrics(ApiVersion $apiVersion, array $data): void
    {
        $versionKey = "api.metrics.{$apiVersion->version}";
        $metrics = Cache::get($versionKey, [
            'total_requests' => 0,
            'error_count' => 0,
            'total_response_time' => 0,
            'unique_clients' => [],
            'endpoint_usage' => [],
            'last_updated' => now()->toISOString()
        ]);
        
        // Update metrics
        $metrics['total_requests']++;
        $metrics['total_response_time'] += $data['response_time_ms'];
        
        if ($data['is_error']) {
            $metrics['error_count']++;
        }
        
        // Track unique clients
        if ($data['client_ip']) {
            $metrics['unique_clients'][$data['client_ip']] = now()->toISOString();
        }
        
        // Track endpoint usage
        $endpoint = $data['endpoint'];
        if (!isset($metrics['endpoint_usage'][$endpoint])) {
            $metrics['endpoint_usage'][$endpoint] = 0;
        }
        $metrics['endpoint_usage'][$endpoint]++;
        
        // Clean up old client data (older than 24 hours)
        $metrics['unique_clients'] = array_filter(
            $metrics['unique_clients'],
            fn($timestamp) => now()->diffInHours($timestamp) < 24
        );
        
        $metrics['last_updated'] = now()->toISOString();
        
        // Store updated metrics
        Cache::put($versionKey, $metrics, 3600);
    }
    
    /**
     * Extract restaurant ID from request
     */
    private function extractRestaurantId(Request $request): ?int
    {
        // Try to get from route parameters
        $restaurant = $request->route('restaurant');
        if ($restaurant) {
            return is_numeric($restaurant) ? (int) $restaurant : $restaurant->id ?? null;
        }
        
        // Try to get from query parameters
        $restaurantId = $request->query('restaurant_id');
        if ($restaurantId) {
            return (int) $restaurantId;
        }
        
        // Try to get from authenticated user
        if ($request->user()) {
            return $request->user()->restaurant_id ?? null;
        }
        
        return null;
    }
    
    /**
     * Extract user ID from request
     */
    private function extractUserId(Request $request): ?int
    {
        if ($request->user()) {
            return $request->user()->id;
        }
        
        return null;
    }
    
    /**
     * Get request size in bytes
     */
    private function getRequestSize(Request $request): int
    {
        $size = 0;
        
        // Add URL length
        $size += strlen($request->fullUrl());
        
        // Add headers size
        foreach ($request->headers->all() as $name => $values) {
            $size += strlen($name) + strlen(implode(', ', $values));
        }
        
        // Add content size
        $size += strlen($request->getContent());
        
        return $size;
    }
    
    /**
     * Get response size in bytes
     */
    private function getResponseSize(Response $response): int
    {
        return strlen($response->getContent());
    }
    
    /**
     * Get rate limit information
     */
    private function getRateLimitInfo(Request $request): ?array
    {
        // This would integrate with your rate limiting system
        // For now, return null
        return null;
    }
    
    /**
     * Get analytics summary for a version
     */
    public static function getVersionAnalytics(string $version): array
    {
        $versionKey = "api.metrics.{$version}";
        $metrics = Cache::get($versionKey, []);
        
        if (empty($metrics)) {
            return [
                'total_requests' => 0,
                'error_rate' => 0.0,
                'avg_response_time' => 0.0,
                'unique_clients' => 0,
                'popular_endpoints' => [],
                'last_updated' => null
            ];
        }
        
        $totalRequests = $metrics['total_requests'];
        $errorRate = $totalRequests > 0 ? ($metrics['error_count'] / $totalRequests) * 100 : 0;
        $avgResponseTime = $totalRequests > 0 ? $metrics['total_response_time'] / $totalRequests : 0;
        
        // Sort endpoints by usage
        $endpoints = $metrics['endpoint_usage'] ?? [];
        arsort($endpoints);
        $popularEndpoints = array_slice($endpoints, 0, 10, true);
        
        return [
            'total_requests' => $totalRequests,
            'error_rate' => round($errorRate, 2),
            'avg_response_time' => round($avgResponseTime, 2),
            'unique_clients' => count($metrics['unique_clients'] ?? []),
            'popular_endpoints' => $popularEndpoints,
            'last_updated' => $metrics['last_updated'] ?? null
        ];
    }
    
    /**
     * Get migration progress analytics
     */
    public static function getMigrationProgress(): array
    {
        $v1Metrics = self::getVersionAnalytics('v1');
        $v2Metrics = self::getVersionAnalytics('v2');
        
        $totalRequests = $v1Metrics['total_requests'] + $v2Metrics['total_requests'];
        
        if ($totalRequests === 0) {
            return [
                'v1_usage_percentage' => 0,
                'v2_usage_percentage' => 0,
                'migration_progress' => 0,
                'total_requests' => 0
            ];
        }
        
        $v1Percentage = ($v1Metrics['total_requests'] / $totalRequests) * 100;
        $v2Percentage = ($v2Metrics['total_requests'] / $totalRequests) * 100;
        
        // Migration progress is inverse of v1 usage (as v1 gets deprecated)
        $migrationProgress = 100 - $v1Percentage;
        
        return [
            'v1_usage_percentage' => round($v1Percentage, 2),
            'v2_usage_percentage' => round($v2Percentage, 2),
            'migration_progress' => round($migrationProgress, 2),
            'total_requests' => $totalRequests
        ];
    }
}
