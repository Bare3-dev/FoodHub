<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiVersion;
use App\Services\ApiVersionNotificationService;
use App\Http\Middleware\VersionAnalyticsMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiVersionAnalyticsController extends Controller
{
    /**
     * Get comprehensive analytics for all API versions
     */
    public function index(): JsonResponse
    {
        try {
            $analytics = [
                'overview' => $this->getOverviewAnalytics(),
                'versions' => $this->getVersionAnalytics(),
                'migration_progress' => VersionAnalyticsMiddleware::getMigrationProgress(),
                'deprecation_notifications' => ApiVersionNotificationService::getActiveDeprecationNotifications(),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'error_analysis' => $this->getErrorAnalysis(),
                'last_updated' => now()->toISOString()
            ];
            
            return response()->json($analytics);
        } catch (\Exception $e) {
            Log::error('Failed to get API analytics', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve analytics'], 500);
        }
    }
    
    /**
     * Get analytics for a specific version
     */
    public function show(string $version): JsonResponse
    {
        try {
            $apiVersion = ApiVersion::where('version', $version)->first();
            
            if (!$apiVersion) {
                return response()->json(['error' => 'Version not found'], 404);
            }
            
            $analytics = [
                'version_info' => [
                    'version' => $apiVersion->version,
                    'status' => $apiVersion->status,
                    'release_date' => $apiVersion->release_date?->toISOString(),
                    'sunset_date' => $apiVersion->sunset_date?->toISOString(),
                    'is_default' => $apiVersion->is_default
                ],
                'usage_analytics' => VersionAnalyticsMiddleware::getVersionAnalytics($version),
                'deprecation_status' => $this->getDeprecationStatus($apiVersion),
                'performance_trends' => $this->getPerformanceTrends($version),
                'error_patterns' => $this->getErrorPatterns($version),
                'popular_endpoints' => $this->getPopularEndpoints($version),
                'client_distribution' => $this->getClientDistribution($version)
            ];
            
            return response()->json($analytics);
        } catch (\Exception $e) {
            Log::error('Failed to get version analytics', [
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to retrieve version analytics'], 500);
        }
    }
    
    /**
     * Get migration progress analytics
     */
    public function migrationProgress(): JsonResponse
    {
        try {
            $progress = VersionAnalyticsMiddleware::getMigrationProgress();
            
            $analytics = [
                'migration_progress' => $progress,
                'recommendations' => $this->getMigrationRecommendations($progress),
                'timeline_analysis' => $this->getTimelineAnalysis(),
                'risk_assessment' => $this->getRiskAssessment($progress)
            ];
            
            return response()->json($analytics);
        } catch (\Exception $e) {
            Log::error('Failed to get migration progress', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve migration progress'], 500);
        }
    }
    
    /**
     * Get real-time monitoring data
     */
    public function realTimeMonitoring(): JsonResponse
    {
        try {
            $monitoring = [
                'current_requests' => $this->getCurrentRequests(),
                'error_rate_trends' => $this->getErrorRateTrends(),
                'response_time_monitoring' => $this->getResponseTimeMonitoring(),
                'active_versions' => $this->getActiveVersionsStatus(),
                'system_health' => $this->getSystemHealth(),
                'timestamp' => now()->toISOString()
            ];
            
            return response()->json($monitoring);
        } catch (\Exception $e) {
            Log::error('Failed to get real-time monitoring', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve monitoring data'], 500);
        }
    }
    
    /**
     * Get overview analytics
     */
    private function getOverviewAnalytics(): array
    {
        $totalRequests = 0;
        $totalErrors = 0;
        $activeVersions = 0;
        
        $versions = ApiVersion::all();
        foreach ($versions as $version) {
            $analytics = VersionAnalyticsMiddleware::getVersionAnalytics($version->version);
            $totalRequests += $analytics['total_requests'];
            $totalErrors += ($analytics['total_requests'] * $analytics['error_rate'] / 100);
            if ($version->isActive()) {
                $activeVersions++;
            }
        }
        
        $overallErrorRate = $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;
        
        return [
            'total_requests' => $totalRequests,
            'overall_error_rate' => round($overallErrorRate, 2),
            'active_versions' => $activeVersions,
            'total_versions' => $versions->count(),
            'last_24_hours' => $this->getLast24HoursData()
        ];
    }
    
    /**
     * Get analytics for all versions
     */
    private function getVersionAnalytics(): array
    {
        $versions = [];
        $apiVersions = ApiVersion::all();
        
        foreach ($apiVersions as $apiVersion) {
            $analytics = VersionAnalyticsMiddleware::getVersionAnalytics($apiVersion->version);
            $versions[$apiVersion->version] = array_merge($analytics, [
                'status' => $apiVersion->status,
                'release_date' => $apiVersion->release_date?->toISOString(),
                'sunset_date' => $apiVersion->sunset_date?->toISOString(),
                'is_default' => $apiVersion->is_default
            ]);
        }
        
        return $versions;
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $metrics = [];
        $apiVersions = ApiVersion::all();
        
        foreach ($apiVersions as $apiVersion) {
            $analytics = VersionAnalyticsMiddleware::getVersionAnalytics($apiVersion->version);
            $metrics[$apiVersion->version] = [
                'avg_response_time' => $analytics['avg_response_time'],
                'total_requests' => $analytics['total_requests'],
                'unique_clients' => $analytics['unique_clients'],
                'popular_endpoints' => array_slice($analytics['popular_endpoints'], 0, 5, true)
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Get error analysis
     */
    private function getErrorAnalysis(): array
    {
        $errors = [];
        $apiVersions = ApiVersion::all();
        
        foreach ($apiVersions as $apiVersion) {
            $analytics = VersionAnalyticsMiddleware::getVersionAnalytics($apiVersion->version);
            $errors[$apiVersion->version] = [
                'error_rate' => $analytics['error_rate'],
                'total_errors' => ($analytics['total_requests'] * $analytics['error_rate'] / 100),
                'error_trend' => $this->getErrorTrend($apiVersion->version)
            ];
        }
        
        return $errors;
    }
    
    /**
     * Get deprecation status for a version
     */
    private function getDeprecationStatus(ApiVersion $apiVersion): array
    {
        if (!$apiVersion->isDeprecated() && !$apiVersion->isSunset()) {
            return ['status' => 'active', 'warning' => null];
        }
        
        $daysUntilSunset = $apiVersion->getDaysUntilSunset();
        $urgency = $this->getNotificationUrgency($daysUntilSunset);
        
        return [
            'status' => $apiVersion->status,
            'urgency' => $urgency,
            'days_until_sunset' => $daysUntilSunset,
            'sunset_date' => $apiVersion->sunset_date?->toISOString(),
            'warning' => $apiVersion->getDeprecationWarning(),
            'migration_guide' => $apiVersion->getMigrationGuideUrl(),
            'successor_version' => $apiVersion->getSuccessorVersion()?->version
        ];
    }
    
    /**
     * Get notification urgency
     */
    private function getNotificationUrgency(?int $daysUntilSunset): string
    {
        if ($daysUntilSunset === null) {
            return 'info';
        }
        
        if ($daysUntilSunset <= 30) {
            return 'critical';
        } elseif ($daysUntilSunset <= 60) {
            return 'high';
        } elseif ($daysUntilSunset <= 90) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Get performance trends for a version
     */
    private function getPerformanceTrends(string $version): array
    {
        // This would analyze historical data for trends
        // For now, return basic structure
        return [
            'response_time_trend' => 'stable',
            'usage_trend' => 'increasing',
            'error_trend' => 'decreasing'
        ];
    }
    
    /**
     * Get error patterns for a version
     */
    private function getErrorPatterns(string $version): array
    {
        // This would analyze error patterns
        // For now, return basic structure
        return [
            'common_error_codes' => [],
            'error_frequency' => 'low',
            'peak_error_times' => []
        ];
    }
    
    /**
     * Get popular endpoints for a version
     */
    private function getPopularEndpoints(string $version): array
    {
        $analytics = VersionAnalyticsMiddleware::getVersionAnalytics($version);
        return array_slice($analytics['popular_endpoints'], 0, 10, true);
    }
    
    /**
     * Get client distribution for a version
     */
    private function getClientDistribution(string $version): array
    {
        // This would analyze client distribution
        // For now, return basic structure
        return [
            'unique_clients' => 0,
            'client_types' => [],
            'geographic_distribution' => []
        ];
    }
    
    /**
     * Get migration recommendations
     */
    private function getMigrationRecommendations(array $progress): array
    {
        $recommendations = [];
        
        if ($progress['v1_usage_percentage'] > 80) {
            $recommendations[] = 'High v1 usage detected. Consider accelerating migration efforts.';
        }
        
        if ($progress['migration_progress'] < 20) {
            $recommendations[] = 'Migration progress is low. Review migration strategy and timeline.';
        }
        
        if ($progress['total_requests'] === 0) {
            $recommendations[] = 'No API usage detected. Verify analytics collection is working.';
        }
        
        return $recommendations;
    }
    
    /**
     * Get timeline analysis
     */
    private function getTimelineAnalysis(): array
    {
        // This would analyze migration timeline
        return [
            'estimated_completion' => '3 months',
            'critical_milestones' => [],
            'risk_periods' => []
        ];
    }
    
    /**
     * Get risk assessment
     */
    private function getRiskAssessment(array $progress): array
    {
        $risk = 'low';
        
        if ($progress['v1_usage_percentage'] > 90) {
            $risk = 'critical';
        } elseif ($progress['v1_usage_percentage'] > 70) {
            $risk = 'high';
        } elseif ($progress['v1_usage_percentage'] > 50) {
            $risk = 'medium';
        }
        
        return [
            'risk_level' => $risk,
            'risk_factors' => [],
            'mitigation_strategies' => []
        ];
    }
    
    /**
     * Get current requests data
     */
    private function getCurrentRequests(): array
    {
        // This would get real-time request data
        return [
            'active_requests' => 0,
            'requests_per_minute' => 0,
            'peak_usage_time' => null
        ];
    }
    
    /**
     * Get error rate trends
     */
    private function getErrorRateTrends(): array
    {
        // This would analyze error rate trends
        return [
            'current_error_rate' => 0.0,
            'trend' => 'stable',
            'spikes' => []
        ];
    }
    
    /**
     * Get response time monitoring
     */
    private function getResponseTimeMonitoring(): array
    {
        // This would monitor response times
        return [
            'current_avg_response_time' => 0.0,
            'response_time_percentiles' => [],
            'slow_endpoints' => []
        ];
    }
    
    /**
     * Get active versions status
     */
    private function getActiveVersionsStatus(): array
    {
        $activeVersions = ApiVersion::where('status', ApiVersion::STATUS_ACTIVE)->get();
        $status = [];
        
        foreach ($activeVersions as $version) {
            $analytics = VersionAnalyticsMiddleware::getVersionAnalytics($version->version);
            $status[$version->version] = [
                'status' => 'healthy',
                'error_rate' => $analytics['error_rate'],
                'response_time' => $analytics['avg_response_time']
            ];
        }
        
        return $status;
    }
    
    /**
     * Get system health
     */
    private function getSystemHealth(): array
    {
        return [
            'overall_health' => 'healthy',
            'version_health' => 'good',
            'performance_health' => 'good',
            'error_health' => 'good'
        ];
    }
    
    /**
     * Get last 24 hours data
     */
    private function getLast24HoursData(): array
    {
        // This would get last 24 hours analytics
        return [
            'requests' => 0,
            'errors' => 0,
            'unique_clients' => 0
        ];
    }
    
    /**
     * Get error trend for a version
     */
    private function getErrorTrend(string $version): string
    {
        // This would analyze error trends
        return 'stable';
    }
}
