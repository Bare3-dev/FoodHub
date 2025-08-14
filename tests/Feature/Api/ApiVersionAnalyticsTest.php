<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ApiVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApiVersionAnalyticsTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the API versions
        $this->seed(\Database\Seeders\ApiVersionSeeder::class);
        
        // Clear cache before each test
        Cache::flush();
        
        // Seed analytics data for testing
        $this->seedAnalyticsData();
    }

    /**
     * Seed analytics data for testing purposes
     */
    private function seedAnalyticsData(): void
    {
        // Seed v1 analytics data
        $v1Metrics = [
            'total_requests' => 25,
            'error_count' => 2,
            'total_response_time' => 1250,
            'unique_clients' => ['192.168.1.1' => now()->toISOString(), '192.168.1.2' => now()->toISOString()],
            'endpoint_usage' => [
                '/api/v1/restaurants' => 10,
                '/api/v1/menu-items' => 8,
                '/api/v1/menu-categories' => 7
            ],
            'last_updated' => now()->toISOString()
        ];
        Cache::put('api.metrics.v1', $v1Metrics, 3600);

        // Seed v2 analytics data
        $v2Metrics = [
            'total_requests' => 15,
            'error_count' => 1,
            'total_response_time' => 800,
            'unique_clients' => ['192.168.1.3' => now()->toISOString()],
            'endpoint_usage' => [
                '/api/v2/migration/check' => 10,
                '/api/v2/restaurants' => 5
            ],
            'last_updated' => now()->toISOString()
        ];
        Cache::put('api.metrics.v2', $v2Metrics, 3600);
    }

    /** @test */
    public function it_returns_comprehensive_analytics_for_all_versions()
    {
        $response = $this->getJson('/api/v1/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'overview' => [
                    'total_requests',
                    'overall_error_rate',
                    'active_versions',
                    'total_versions',
                    'last_24_hours'
                ],
                'versions',
                'migration_progress',
                'deprecation_notifications',
                'performance_metrics',
                'error_analysis',
                'last_updated'
            ]);
    }

    /** @test */
    public function it_returns_analytics_for_specific_version()
    {
        $response = $this->getJson('/api/v1/analytics/v1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version_info' => [
                    'version',
                    'status',
                    'release_date',
                    'sunset_date',
                    'is_default'
                ],
                'usage_analytics' => [
                    'total_requests',
                    'error_rate',
                    'avg_response_time',
                    'unique_clients',
                    'popular_endpoints',
                    'last_updated'
                ],
                'deprecation_status',
                'performance_trends',
                'error_patterns',
                'popular_endpoints',
                'client_distribution'
            ]);
    }

    /** @test */
    public function it_returns_migration_progress_analytics()
    {
        $response = $this->getJson('/api/v1/analytics/migration/progress');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'migration_progress' => [
                    'v1_usage_percentage',
                    'v2_usage_percentage',
                    'migration_progress',
                    'total_requests'
                ],
                'recommendations',
                'timeline_analysis',
                'risk_assessment'
            ]);
    }

    /** @test */
    public function it_returns_real_time_monitoring_data()
    {
        $response = $this->getJson('/api/v1/analytics/monitoring/realtime');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_requests',
                'error_rate_trends',
                'response_time_monitoring',
                'active_versions',
                'system_health',
                'timestamp'
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_version_analytics()
    {
        $response = $this->getJson('/api/v1/analytics/v999');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Version not found']);
    }

    /** @test */
    public function it_tracks_version_usage_analytics()
    {
        // Analytics data is seeded in setUp()
        // Check analytics for v1
        $response = $this->getJson('/api/v1/analytics/v1');
        $response->assertStatus(200);
        
        $analytics = $response->json('usage_analytics');
        $this->assertGreaterThan(0, $analytics['total_requests']);
        $this->assertArrayHasKey('popular_endpoints', $analytics);
    }

    /** @test */
    public function it_tracks_migration_progress_correctly()
    {
        // Analytics data is seeded in setUp()
        $response = $this->getJson('/api/v1/analytics/migration/progress');
        $response->assertStatus(200);
        
        $progress = $response->json('migration_progress');
        $this->assertGreaterThan(0, $progress['total_requests']);
        $this->assertGreaterThan(0, $progress['v1_usage_percentage']);
        $this->assertGreaterThan(0, $progress['v2_usage_percentage']);
    }

    /** @test */
    public function it_provides_migration_recommendations()
    {
        $response = $this->getJson('/api/v1/analytics/migration/progress');
        $response->assertStatus(200);
        
        $recommendations = $response->json('recommendations');
        $this->assertIsArray($recommendations);
    }

    /** @test */
    public function it_assesses_migration_risk()
    {
        $response = $this->getJson('/api/v1/analytics/migration/progress');
        $response->assertStatus(200);
        
        $riskAssessment = $response->json('risk_assessment');
        $this->assertArrayHasKey('risk_level', $riskAssessment);
        $this->assertArrayHasKey('risk_factors', $riskAssessment);
        $this->assertArrayHasKey('mitigation_strategies', $riskAssessment);
    }

    /** @test */
    public function it_tracks_performance_metrics()
    {
        $response = $this->getJson('/api/v1/analytics');
        $response->assertStatus(200);
        
        $performanceMetrics = $response->json('performance_metrics');
        $this->assertArrayHasKey('v1', $performanceMetrics);
        $this->assertArrayHasKey('v2', $performanceMetrics);
        
        foreach ($performanceMetrics as $version => $metrics) {
            $this->assertArrayHasKey('avg_response_time', $metrics);
            $this->assertArrayHasKey('total_requests', $metrics);
            $this->assertArrayHasKey('unique_clients', $metrics);
            $this->assertArrayHasKey('popular_endpoints', $metrics);
        }
    }

    /** @test */
    public function it_tracks_error_analysis()
    {
        $response = $this->getJson('/api/v1/analytics');
        $response->assertStatus(200);
        
        $errorAnalysis = $response->json('error_analysis');
        $this->assertArrayHasKey('v1', $errorAnalysis);
        $this->assertArrayHasKey('v2', $errorAnalysis);
        
        foreach ($errorAnalysis as $version => $errors) {
            $this->assertArrayHasKey('error_rate', $errors);
            $this->assertArrayHasKey('total_errors', $errors);
            $this->assertArrayHasKey('error_trend', $errors);
        }
    }

    /** @test */
    public function it_provides_system_health_status()
    {
        $response = $this->getJson('/api/v1/analytics/monitoring/realtime');
        $response->assertStatus(200);
        
        $systemHealth = $response->json('system_health');
        $this->assertArrayHasKey('overall_health', $systemHealth);
        $this->assertArrayHasKey('version_health', $systemHealth);
        $this->assertArrayHasKey('performance_health', $systemHealth);
        $this->assertArrayHasKey('error_health', $systemHealth);
    }

    /** @test */
    public function it_tracks_popular_endpoints()
    {
        // Analytics data is seeded in setUp()
        $response = $this->getJson('/api/v1/analytics/v1');
        $response->assertStatus(200);
        
        $popularEndpoints = $response->json('popular_endpoints');
        $this->assertIsArray($popularEndpoints);
        $this->assertGreaterThan(0, count($popularEndpoints));
    }

    /** @test */
    public function it_handles_analytics_with_no_data()
    {
        // Clear any existing analytics data
        Cache::flush();
        
        $response = $this->getJson('/api/v1/analytics');
        $response->assertStatus(200);
        
        $overview = $response->json('overview');
        $this->assertEquals(0, $overview['total_requests']);
        $this->assertEquals(0, $overview['overall_error_rate']);
    }

    /** @test */
    public function it_provides_deprecation_status_for_deprecated_versions()
    {
        // Create a deprecated version for testing
        $deprecatedVersion = ApiVersion::create([
            'version' => 'v0',
            'status' => ApiVersion::STATUS_DEPRECATED,
            'release_date' => now()->subMonths(6),
            'sunset_date' => now()->addDays(30),
            'is_default' => false
        ]);
        
        $response = $this->getJson("/api/v1/analytics/{$deprecatedVersion->version}");
        $response->assertStatus(200);
        
        $deprecationStatus = $response->json('deprecation_status');
        $this->assertEquals('deprecated', $deprecationStatus['status']);
        $this->assertArrayHasKey('urgency', $deprecationStatus);
        $this->assertArrayHasKey('days_until_sunset', $deprecationStatus);
        $this->assertArrayHasKey('warning', $deprecationStatus);
    }

    /** @test */
    public function it_works_with_v2_endpoints()
    {
        $response = $this->getJson('/api/v2/analytics');
        $response->assertStatus(200);
        
        $response = $this->getJson('/api/v2/analytics/v2');
        $response->assertStatus(200);
        
        $response = $this->getJson('/api/v2/analytics/migration/progress');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_provides_consistent_analytics_structure_across_versions()
    {
        $v1Response = $this->getJson('/api/v1/analytics');
        $v2Response = $this->getJson('/api/v2/analytics');
        
        $v1Response->assertStatus(200);
        $v2Response->assertStatus(200);
        
        // Both should have the same structure
        $this->assertEquals(
            array_keys($v1Response->json()),
            array_keys($v2Response->json())
        );
    }
}
