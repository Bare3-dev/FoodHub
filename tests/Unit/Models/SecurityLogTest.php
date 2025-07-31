<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    private SecurityLog $securityLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityLog = SecurityLog::factory()->create();
    }

    /**
     * Test security log has correct relationships
     */
    public function test_it_has_correct_relationships(): void
    {
        $user = User::factory()->create();
        $securityLog = SecurityLog::factory()->create([
            'user_id' => $user->id
        ]);

        // Test user relationship
        $this->assertEquals($user->id, $securityLog->user->id);
        $this->assertTrue($user->securityLogs->contains($securityLog));
    }

    /**
     * Test security log validates required fields
     */
    public function test_it_validates_required_fields(): void
    {
        $user = User::factory()->create();
        
        $requiredFields = [
            'user_id' => $user->id,
            'event_type' => 'login_success',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'session_id' => 'session_123456',
            'metadata' => [
                'description' => 'User logged in successfully',
                'login_time' => now()->format('Y-m-d H:i:s')
            ]
        ];

        $securityLog = SecurityLog::create($requiredFields);

        $this->assertDatabaseHas('security_logs', [
            'id' => $securityLog->id,
            'user_id' => $user->id,
            'event_type' => 'login_success',
            'ip_address' => '192.168.1.1'
        ]);
    }

    /**
     * Test security log handles optional fields correctly
     */
    public function test_it_handles_optional_fields_correctly(): void
    {
        $securityLog = SecurityLog::factory()->create([
            'user_id' => null,
            'user_agent' => null,
            'session_id' => null,
            'target_type' => 'User',
            'target_id' => 123,
            'metadata' => [
                'description' => 'System event occurred',
                'severity' => 'high'
            ]
        ]);

        $this->assertNull($securityLog->user_id);
        $this->assertNull($securityLog->user_agent);
        $this->assertNull($securityLog->session_id);
        $this->assertEquals('User', $securityLog->target_type);
        $this->assertEquals(123, $securityLog->target_id);
        $this->assertEquals('System event occurred', $securityLog->metadata['description']);
        $this->assertEquals('high', $securityLog->metadata['severity']);
    }

    /**
     * Test security log handles different event types correctly
     */
    public function test_it_handles_different_event_types_correctly(): void
    {
        $eventTypes = [
            'login_success',
            'login_failed',
            'logout',
            'password_reset_requested',
            'password_reset_completed',
            'permission_denied',
            'role_changed',
            'access_granted',
            'access_revoked',
            'data_export',
            'data_import',
            'data_deletion',
            'data_modification',
            'system_error',
            'database_backup',
            'cache_cleared',
            'maintenance_mode_enabled',
            'maintenance_mode_disabled',
            'error_occurred'
        ];

        foreach ($eventTypes as $eventType) {
            $securityLog = SecurityLog::factory()->create(['event_type' => $eventType]);
            $this->assertEquals($eventType, $securityLog->event_type);
        }
    }

    /**
     * Test security log handles metadata correctly
     */
    public function test_it_handles_metadata_correctly(): void
    {
        $metadata = [
            'description' => 'User attempted to access restricted resource',
            'severity_level' => 'high',
            'requires_attention' => true,
            'ip_location' => 'New York, NY',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'request_method' => 'POST',
            'response_code' => 403,
            'processing_time' => 150,
            'memory_usage' => 51200,
            'database_queries' => 15,
            'cache_hits' => 8,
            'cache_misses' => 2
        ];

        $securityLog = SecurityLog::factory()->create(['metadata' => $metadata]);

        $this->assertEquals($metadata, $securityLog->metadata);
        $this->assertIsArray($securityLog->metadata);
        $this->assertEquals('User attempted to access restricted resource', $securityLog->metadata['description']);
        $this->assertEquals('high', $securityLog->metadata['severity_level']);
        $this->assertTrue($securityLog->metadata['requires_attention']);
        $this->assertEquals(403, $securityLog->metadata['response_code']);
    }

    /**
     * Test security log factory states work correctly
     */
    public function test_it_uses_factory_states_correctly(): void
    {
        // Test login success state
        $loginSuccessLog = SecurityLog::factory()->loginSuccess()->create();
        $this->assertEquals('login_success', $loginSuccessLog->event_type);
        $this->assertArrayHasKey('login_method', $loginSuccessLog->metadata);
        $this->assertArrayHasKey('login_time', $loginSuccessLog->metadata);
        $this->assertArrayHasKey('session_duration', $loginSuccessLog->metadata);

        // Test login failed state
        $loginFailedLog = SecurityLog::factory()->loginFailed()->create();
        $this->assertEquals('login_failed', $loginFailedLog->event_type);
        $this->assertArrayHasKey('attempted_email', $loginFailedLog->metadata);
        $this->assertArrayHasKey('failure_reason', $loginFailedLog->metadata);
        $this->assertArrayHasKey('attempt_count', $loginFailedLog->metadata);

        // Test high severity state
        $highSeverityLog = SecurityLog::factory()->highSeverity()->create();
        $this->assertContains($highSeverityLog->event_type, [
            'permission_denied',
            'data_access_violation',
            'suspicious_activity',
            'brute_force_attempt'
        ]);
        $this->assertEquals('high', $highSeverityLog->metadata['severity_level']);
        $this->assertTrue($highSeverityLog->metadata['requires_attention']);
        $this->assertTrue($highSeverityLog->metadata['alert_sent']);

        // Test critical severity state
        $criticalSeverityLog = SecurityLog::factory()->criticalSeverity()->create();
        $this->assertContains($criticalSeverityLog->event_type, [
            'sql_injection_attempt',
            'xss_attempt',
            'csrf_attack',
            'session_hijacking'
        ]);
        $this->assertEquals('critical', $criticalSeverityLog->metadata['severity_level']);
        $this->assertTrue($criticalSeverityLog->metadata['requires_immediate_attention']);
        $this->assertTrue($criticalSeverityLog->metadata['ip_blocked']);

        // Test system event state
        $systemEventLog = SecurityLog::factory()->systemEvent()->create();
        $this->assertNull($systemEventLog->user_id);
        $this->assertContains($systemEventLog->event_type, [
            'system_error',
            'database_backup',
            'cache_cleared',
            'maintenance_mode_enabled',
            'maintenance_mode_disabled',
            'error_occurred'
        ]);
        $this->assertArrayHasKey('system_component', $systemEventLog->metadata);
        $this->assertArrayHasKey('error_code', $systemEventLog->metadata);
        $this->assertArrayHasKey('affected_services', $systemEventLog->metadata);

        // Test data access state
        $dataAccessLog = SecurityLog::factory()->dataAccess()->create();
        $this->assertContains($dataAccessLog->event_type, [
            'data_export',
            'data_import',
            'data_deletion',
            'data_modification'
        ]);
        $this->assertArrayHasKey('table', $dataAccessLog->metadata);
        $this->assertArrayHasKey('record_count', $dataAccessLog->metadata);
        $this->assertArrayHasKey('format', $dataAccessLog->metadata);
        $this->assertArrayHasKey('source', $dataAccessLog->metadata);
        $this->assertArrayHasKey('reason', $dataAccessLog->metadata);
    }

    /**
     * Test security log handles IP addresses correctly
     */
    public function test_it_handles_ip_addresses_correctly(): void
    {
        $ipAddresses = [
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '127.0.0.1',
            '8.8.8.8',
            '1.1.1.1'
        ];

        foreach ($ipAddresses as $ipAddress) {
            $securityLog = SecurityLog::factory()->create(['ip_address' => $ipAddress]);
            $this->assertEquals($ipAddress, $securityLog->ip_address);
        }
    }

    /**
     * Test security log handles user agents correctly
     */
    public function test_it_handles_user_agents_correctly(): void
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Android 11; Mobile; rv:68.0) Gecko/68.0 Firefox/68.0'
        ];

        foreach ($userAgents as $userAgent) {
            $securityLog = SecurityLog::factory()->create(['user_agent' => $userAgent]);
            $this->assertEquals($userAgent, $securityLog->user_agent);
        }
    }

    /**
     * Test security log handles session IDs correctly
     */
    public function test_it_handles_session_ids_correctly(): void
    {
        $sessionIds = [
            'session_123456789',
            'abc123def456ghi789',
            'xyz987uvw654rst321',
            'session_abcdef123456',
            'session_xyz789uvw456'
        ];

        foreach ($sessionIds as $sessionId) {
            $securityLog = SecurityLog::factory()->create(['session_id' => $sessionId]);
            $this->assertEquals($sessionId, $securityLog->session_id);
        }
    }

    /**
     * Test security log handles target information correctly
     */
    public function test_it_handles_target_information_correctly(): void
    {
        $targetTypes = ['User', 'Order', 'Customer', 'Restaurant', 'MenuItem'];
        $targetIds = [1, 100, 500, 1000, 9999];

        foreach ($targetTypes as $targetType) {
            foreach ($targetIds as $targetId) {
                $securityLog = SecurityLog::factory()->create([
                    'target_type' => $targetType,
                    'target_id' => $targetId
                ]);
                $this->assertEquals($targetType, $securityLog->target_type);
                $this->assertEquals($targetId, $securityLog->target_id);
            }
        }
    }

    /**
     * Test security log handles complex metadata correctly
     */
    public function test_it_handles_complex_metadata_correctly(): void
    {
        $complexMetadata = [
            'description' => 'Complex security event with multiple data points',
            'severity_level' => 'critical',
            'requires_immediate_attention' => true,
            'ip_blocked' => true,
            'alert_sent' => true,
            'technical_details' => [
                'request_headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1'
                ],
                'request_body' => [
                    'username' => 'test@example.com',
                    'password' => '[REDACTED]',
                    'remember' => true
                ],
                'response_headers' => [
                    'Content-Type' => 'application/json',
                    'X-Frame-Options' => 'DENY',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-XSS-Protection' => '1; mode=block'
                ],
                'response_body' => [
                    'error' => 'Invalid credentials',
                    'code' => 'AUTH_001',
                    'timestamp' => now()->toISOString()
                ]
            ],
            'geolocation' => [
                'country' => 'United States',
                'region' => 'New York',
                'city' => 'New York City',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'timezone' => 'America/New_York'
            ],
            'performance_metrics' => [
                'processing_time_ms' => 245,
                'memory_usage_mb' => 45.2,
                'database_queries' => 12,
                'cache_hits' => 8,
                'cache_misses' => 4,
                'network_latency_ms' => 15
            ],
            'security_analysis' => [
                'threat_score' => 85,
                'risk_level' => 'high',
                'suspicious_patterns' => [
                    'rapid_fire_requests',
                    'unusual_user_agent',
                    'geographic_anomaly'
                ],
                'mitigation_actions' => [
                    'ip_temporarily_blocked',
                    'rate_limit_enforced',
                    'alert_sent_to_admin'
                ]
            ]
        ];

        $securityLog = SecurityLog::factory()->create(['metadata' => $complexMetadata]);

        $this->assertEquals($complexMetadata, $securityLog->metadata);
        $this->assertEquals('critical', $securityLog->metadata['severity_level']);
        $this->assertTrue($securityLog->metadata['requires_immediate_attention']);
        $this->assertTrue($securityLog->metadata['ip_blocked']);
        $this->assertArrayHasKey('technical_details', $securityLog->metadata);
        $this->assertArrayHasKey('geolocation', $securityLog->metadata);
        $this->assertArrayHasKey('performance_metrics', $securityLog->metadata);
        $this->assertArrayHasKey('security_analysis', $securityLog->metadata);
        $this->assertEquals(85, $securityLog->metadata['security_analysis']['threat_score']);
    }

    /**
     * Test security log handles null user correctly
     */
    public function test_it_handles_null_user_correctly(): void
    {
        $securityLog = SecurityLog::factory()->create(['user_id' => null]);

        $this->assertNull($securityLog->user_id);
        $this->assertNull($securityLog->user);
    }

    /**
     * Test security log handles edge cases correctly
     */
    public function test_it_handles_edge_cases_correctly(): void
    {
        // Test long event type (within database limits)
        $longEventType = str_repeat('a', 100);
        $securityLog = SecurityLog::factory()->create(['event_type' => $longEventType]);
        $this->assertEquals($longEventType, $securityLog->event_type);

        // Test IPv6 address
        $ipv6Address = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $securityLog = SecurityLog::factory()->create(['ip_address' => $ipv6Address]);
        $this->assertEquals($ipv6Address, $securityLog->ip_address);

        // Test long user agent (within database limits)
        $longUserAgent = str_repeat('Mozilla/5.0 ', 10);
        $securityLog = SecurityLog::factory()->create(['user_agent' => $longUserAgent]);
        $this->assertEquals($longUserAgent, $securityLog->user_agent);

        // Test long session ID (within database limits)
        $longSessionId = str_repeat('session_', 10) . '123456789';
        $securityLog = SecurityLog::factory()->create(['session_id' => $longSessionId]);
        $this->assertEquals($longSessionId, $securityLog->session_id);

        // Test very large target ID
        $largeTargetId = 999999999;
        $securityLog = SecurityLog::factory()->create(['target_id' => $largeTargetId]);
        $this->assertEquals($largeTargetId, $securityLog->target_id);
    }

    /**
     * Test security log handles attribute casting correctly
     */
    public function test_it_casts_attributes_correctly(): void
    {
        $user = User::factory()->create();
        $securityLog = SecurityLog::factory()->create([
            'user_id' => $user->id,
            'target_id' => 456,
            'metadata' => [
                'test_key' => 'test_value',
                'numeric_value' => 42,
                'boolean_value' => true
            ]
        ]);

        // Test integer casting
        $this->assertIsInt($securityLog->user_id);
        $this->assertIsInt($securityLog->target_id);

        // Test array casting
        $this->assertIsArray($securityLog->metadata);
        $this->assertEquals('test_value', $securityLog->metadata['test_key']);
        $this->assertEquals(42, $securityLog->metadata['numeric_value']);
        $this->assertTrue($securityLog->metadata['boolean_value']);
    }

    /**
     * Test security log handles empty metadata correctly
     */
    public function test_it_handles_empty_metadata_correctly(): void
    {
        $securityLog = SecurityLog::factory()->create(['metadata' => []]);

        $this->assertIsArray($securityLog->metadata);
        $this->assertEmpty($securityLog->metadata);
    }

    /**
     * Test security log handles null metadata correctly
     */
    public function test_it_handles_null_metadata_correctly(): void
    {
        $securityLog = SecurityLog::factory()->create(['metadata' => null]);

        $this->assertNull($securityLog->metadata);
    }

    /**
     * Test security log handles logEvent static method correctly
     */
    public function test_it_handles_logEvent_static_method_correctly(): void
    {
        $user = User::factory()->create();
        $metadata = [
            'description' => 'Test security event',
            'severity' => 'medium'
        ];

        $securityLog = SecurityLog::logEvent(
            'test_event',
            $user->id,
            '192.168.1.100',
            'Test User Agent',
            'test_session_123',
            $metadata,
            'User',
            123
        );

        $this->assertInstanceOf(SecurityLog::class, $securityLog);
        $this->assertEquals($user->id, $securityLog->user_id);
        $this->assertEquals('test_event', $securityLog->event_type);
        $this->assertEquals('192.168.1.100', $securityLog->ip_address);
        $this->assertEquals('Test User Agent', $securityLog->user_agent);
        $this->assertEquals('test_session_123', $securityLog->session_id);
        $this->assertEquals('User', $securityLog->target_type);
        $this->assertEquals(123, $securityLog->target_id);
        $this->assertEquals($metadata, $securityLog->metadata);
    }

    /**
     * Test security log handles logEvent with minimal parameters correctly
     */
    public function test_it_handles_logEvent_with_minimal_parameters_correctly(): void
    {
        $securityLog = SecurityLog::logEvent('minimal_event');

        $this->assertInstanceOf(SecurityLog::class, $securityLog);
        $this->assertEquals('minimal_event', $securityLog->event_type);
        $this->assertNull($securityLog->user_id);
        $this->assertNotNull($securityLog->ip_address);
        $this->assertNotNull($securityLog->user_agent);
        $this->assertNotNull($securityLog->session_id);
        $this->assertNull($securityLog->target_type);
        $this->assertNull($securityLog->target_id);
        $this->assertIsArray($securityLog->metadata);
        $this->assertEmpty($securityLog->metadata);
    }
} 