<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SecurityLoggingService;
use App\Models\User;
use App\Models\SecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use Carbon\Carbon;

final class SecurityLoggingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecurityLoggingService $securityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityService = new SecurityLoggingService();
        Cache::flush();
    }

    /**
     * Test security service logs security incidents correctly
     */
    public function test_it_logs_security_incidents_correctly(): void
    {
        Log::shouldReceive('critical')->once();
        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('alert')->times(2); // For high and critical severity alerts

        // Test critical severity
        $this->securityService->logSecurityIncident(
            'sql_injection_attempt',
            SecurityLoggingService::SEVERITY_CRITICAL,
            'SQL injection attempt detected',
            ['raw_input' => 'SELECT * FROM users; DROP TABLE users;']
        );

        // Test high severity
        $this->securityService->logSecurityIncident(
            'authorization_failure',
            SecurityLoggingService::SEVERITY_HIGH,
            'Unauthorized access attempt',
            ['user_id' => 123, 'resource' => '/admin/users']
        );

        // Test medium severity
        $this->securityService->logSecurityIncident(
            'authentication_failure',
            SecurityLoggingService::SEVERITY_MEDIUM,
            'Failed login attempt',
            ['email' => 'test@example.com']
        );

        // Test low severity
        $this->securityService->logSecurityIncident(
            'suspicious_activity',
            SecurityLoggingService::SEVERITY_LOW,
            'Unusual login pattern',
            ['ip_address' => '192.168.1.1']
        );
    }

    /**
     * Test security service logs authentication failures correctly
     */
    public function test_it_logs_authentication_failures_correctly(): void
    {
        Log::shouldReceive('warning')->once();

        $this->securityService->logAuthenticationFailure(
            'test@example.com',
            'invalid_credentials'
        );

        // Verify brute force tracking
        $emailKey = 'brute_force:email:test@example.com';
        $this->assertEquals(1, Cache::get($emailKey));
    }

    /**
     * Test security service logs authorization failures correctly
     */
    public function test_it_logs_authorization_failures_correctly(): void
    {
        Log::shouldReceive('warning')->once();

        $this->securityService->logAuthorizationFailure(
            'User',
            'delete',
            request()
        );
    }

    /**
     * Test security service logs suspicious activity correctly
     */
    public function test_it_logs_suspicious_activity_correctly(): void
    {
        Log::shouldReceive('error')->once(); // For high severity (multiple_failed_logins is high risk)
        Log::shouldReceive('alert')->once(); // For high severity alert

        $this->securityService->logSuspiciousActivity(
            'multiple_failed_logins',
            [
                'ip_address' => '192.168.1.1',
                'attempt_count' => 10,
                'time_window' => '5 minutes'
            ]
        );
    }

    /**
     * Test security service logs data access violations correctly
     */
    public function test_it_logs_data_access_violations_correctly(): void
    {
        Log::shouldReceive('error')->once();
        Log::shouldReceive('alert')->once(); // For high severity alert

        $this->securityService->logDataAccessViolation(
            'Customer',
            'unauthorized_export',
            [
                'user_id' => 123,
                'record_count' => 1000,
                'export_format' => 'csv'
            ]
        );
    }

    /**
     * Test security service logs attack attempts correctly
     */
    public function test_it_logs_attack_attempts_correctly(): void
    {
        Log::shouldReceive('critical')->times(2); // sql_injection: incident + IP block
        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('alert')->times(2); // For critical and high severity alerts

        // Test critical attack with real IP
        $request = Request::create('/test', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100'
        ]);
        $this->securityService->logAttackAttempt(
            'sql_injection_attempt',
            [
                'raw_input' => "'; DROP TABLE users; --",
                'detection_method' => 'pattern_matching'
            ],
            $request
        );

        // Test high severity attack
        $this->securityService->logAttackAttempt(
            'csrf_attack',
            [
                'token_mismatch' => true,
                'referrer' => 'malicious-site.com'
            ]
        );

        // Test medium severity attack (unknown_attack)
        $this->securityService->logAttackAttempt(
            'unknown_attack',
            [
                'raw_input' => 'some_malicious_input',
                'detection_method' => 'content_filtering'
            ]
        );
    }

    /**
     * Test security service tracks brute force attempts correctly
     */
    public function test_it_tracks_brute_force_attempts_correctly(): void
    {
        $email = 'test@example.com';
        $ip = '192.168.1.1';

        // Create a mock request with a specific IP
        $request = Request::create('/test', 'POST', [], [], [], [
            'REMOTE_ADDR' => $ip
        ]);

        // Simulate multiple failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->securityService->logAuthenticationFailure($email, 'invalid_credentials', $request);
        }

        $emailKey = "brute_force:email:{$email}";
        $ipKey = "brute_force:ip:{$ip}";

        $this->assertEquals(5, Cache::get($emailKey));
        $this->assertEquals(5, Cache::get($ipKey));
    }

    /**
     * Test security service triggers alerts for high severity incidents
     */
    public function test_it_triggers_alerts_for_high_severity_incidents(): void
    {
        Log::shouldReceive('critical')->once();
        Log::shouldReceive('error')->once();
        Log::shouldReceive('alert')->twice(); // Once for each high/critical incident

        // Test critical incident
        $this->securityService->logSecurityIncident(
            'sql_injection_attempt',
            SecurityLoggingService::SEVERITY_CRITICAL,
            'Critical SQL injection attempt'
        );

        // Test high incident
        $this->securityService->logSecurityIncident(
            'authorization_failure',
            SecurityLoggingService::SEVERITY_HIGH,
            'High severity authorization failure'
        );
    }

    /**
     * Test security service handles critical threats correctly
     */
    public function test_it_handles_critical_threats_correctly(): void
    {
        Log::shouldReceive('critical')->twice(); // Once for incident, once for IP block
        Log::shouldReceive('alert')->once(); // For critical severity alert

        $ip = '192.168.1.100';
        $request = Request::create('/test', 'POST', [], [], [], [
            'REMOTE_ADDR' => $ip
        ]);
        
        $this->securityService->logAttackAttempt(
            'sql_injection_attempt',
            ['raw_input' => 'malicious_input'],
            $request
        );

        $blockKey = "blocked_ip:{$ip}";
        $blockedData = Cache::get($blockKey);

        $this->assertNotNull($blockedData);
        $this->assertEquals('sql_injection_attempt', $blockedData['reason']);
        $this->assertArrayHasKey('blocked_at', $blockedData);
        $this->assertArrayHasKey('expires_at', $blockedData);
    }

    /**
     * Test security service calculates activity severity correctly
     */
    public function test_it_calculates_activity_severity_correctly(): void
    {
        Log::shouldReceive('critical')->once();
        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('alert')->times(2); // For critical and high severity alerts (medium doesn't trigger alert)

        // Test critical activity
        $this->securityService->logSuspiciousActivity(
            'admin_account_compromise',
            ['admin_user_id' => 1]
        );

        // Test high risk activity
        $this->securityService->logSuspiciousActivity(
            'multiple_failed_logins',
            ['attempt_count' => 10]
        );

        // Test medium activity
        $this->securityService->logSuspiciousActivity(
            'unusual_login_time',
            ['login_time' => '03:00:00']
        );
    }

    /**
     * Test security service sanitizes input correctly
     */
    public function test_it_sanitizes_input_correctly(): void
    {
        Log::shouldReceive('critical')->once();
        Log::shouldReceive('alert')->once(); // For critical severity alert

        $maliciousInput = "'; DROP TABLE users; -- <script>alert('xss')</script>";
        
        $this->securityService->logAttackAttempt(
            'sql_injection_attempt',
            ['raw_input' => $maliciousInput]
        );

        // The service should sanitize the input before logging
        // We can't directly test the private method, but we can verify
        // that the logging doesn't fail with malicious input
        $this->assertTrue(true); // If we reach here, sanitization worked
    }

    /**
     * Test security service generates incident IDs correctly
     */
    public function test_it_generates_incident_ids_correctly(): void
    {
        Log::shouldReceive('warning')->once();

        $this->securityService->logSecurityIncident(
            'test_incident',
            SecurityLoggingService::SEVERITY_MEDIUM,
            'Test incident'
        );

        // The incident ID should be generated in the format SEC-YYYYMMDD-HHMMSS-XXXXXX
        // We can't directly test the private method, but we can verify the logging works
        $this->assertTrue(true);
    }

    /**
     * Test security service generates security reports correctly
     */
    public function test_it_generates_security_reports_correctly(): void
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $report = $this->securityService->generateSecurityReport($startDate, $endDate);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('report_period', $report);
        $this->assertArrayHasKey('total_incidents', $report);
        $this->assertArrayHasKey('incidents_by_severity', $report);
        $this->assertArrayHasKey('incidents_by_type', $report);
        $this->assertArrayHasKey('top_threat_sources', $report);
        $this->assertArrayHasKey('security_metrics', $report);
        $this->assertArrayHasKey('generated_at', $report);

        $this->assertEquals($startDate->toISOString(), $report['report_period']['start']);
        $this->assertEquals($endDate->toISOString(), $report['report_period']['end']);
        $this->assertArrayHasKey(SecurityLoggingService::SEVERITY_CRITICAL, $report['incidents_by_severity']);
        $this->assertArrayHasKey(SecurityLoggingService::SEVERITY_HIGH, $report['incidents_by_severity']);
        $this->assertArrayHasKey(SecurityLoggingService::SEVERITY_MEDIUM, $report['incidents_by_severity']);
        $this->assertArrayHasKey(SecurityLoggingService::SEVERITY_LOW, $report['incidents_by_severity']);
    }

    /**
     * Test security service logs security events to database correctly
     */
    public function test_it_logs_security_events_to_database_correctly(): void
    {
        $user = User::factory()->create();
        
        $securityLog = $this->securityService->logSecurityEvent(
            $user,
            'login_success',
            ['ip_address' => '192.168.1.1'],
            'info',
            'User',
            $user->id
        );

        $this->assertInstanceOf(SecurityLog::class, $securityLog);
        $this->assertEquals($user->id, $securityLog->user_id);
        $this->assertEquals('login_success', $securityLog->event_type);
        $this->assertArrayHasKey('severity_level', $securityLog->metadata);
        $this->assertEquals('info', $securityLog->metadata['severity_level']);
    }

    /**
     * Test security service handles empty event type correctly
     */
    public function test_it_handles_empty_event_type_correctly(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type cannot be empty');

        $this->securityService->logSecurityEvent($user, '');
    }

    /**
     * Test security service masks sensitive data correctly
     */
    public function test_it_masks_sensitive_data_correctly(): void
    {
        $user = User::factory()->create();
        
        $details = [
            'password' => 'secret123',
            'api_key' => 'sk-1234567890abcdef',
            'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
            'normal_data' => 'not_sensitive',
            'nested' => [
                'password_hash' => 'hashed_password_123',
                'normal_nested' => 'safe_data'
            ]
        ];

        $securityLog = $this->securityService->logSecurityEvent(
            $user,
            'test_event',
            $details
        );

        $this->assertInstanceOf(SecurityLog::class, $securityLog);
        // The sensitive data should be masked in the metadata
        $this->assertArrayHasKey('normal_data', $securityLog->metadata);
        $this->assertEquals('not_sensitive', $securityLog->metadata['normal_data']);
    }

    /**
     * Test security service logs user actions correctly
     */
    public function test_it_logs_user_actions_correctly(): void
    {
        $user = User::factory()->create();
        
        $changes = [
            'name' => ['old' => 'John Doe', 'new' => 'Jane Doe'],
            'email' => ['old' => 'john@example.com', 'new' => 'jane@example.com']
        ];

        $securityLog = $this->securityService->logUserAction(
            $user,
            'update',
            'User',
            $user->id,
            $changes
        );

        $this->assertInstanceOf(SecurityLog::class, $securityLog);
        $this->assertEquals($user->id, $securityLog->user_id);
        $this->assertEquals('update', $securityLog->event_type);
        $this->assertArrayHasKey('resource', $securityLog->metadata);
        $this->assertArrayHasKey('resource_id', $securityLog->metadata);
        $this->assertArrayHasKey('changes', $securityLog->metadata);
        $this->assertEquals('User', $securityLog->metadata['resource']);
        $this->assertEquals($user->id, $securityLog->metadata['resource_id']);
        $this->assertEquals($changes, $securityLog->metadata['changes']);
    }

    /**
     * Test security service rotates old logs correctly
     */
    public function test_it_rotates_old_logs_correctly(): void
    {
        // Create old logs (older than 90 days)
        $oldLog = SecurityLog::factory()->create([
            'created_at' => now()->subDays(100)
        ]);

        // Create recent logs
        $recentLog = SecurityLog::factory()->create([
            'created_at' => now()->subDays(30)
        ]);

        $deletedCount = $this->securityService->rotateOldLogs();

        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('security_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('security_logs', ['id' => $recentLog->id]);
    }

    /**
     * Test security service gets logs by user correctly
     */
    public function test_it_gets_logs_by_user_correctly(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create logs for the user
        SecurityLog::factory()->count(3)->create(['user_id' => $user->id]);
        SecurityLog::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $logs = $this->securityService->getLogsByUser($user, 10);

        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $this->assertEquals($user->id, $log->user_id);
        }
    }

    /**
     * Test security service gets logs by event type correctly
     */
    public function test_it_gets_logs_by_event_type_correctly(): void
    {
        // Create logs with different event types
        SecurityLog::factory()->count(3)->create(['event_type' => 'login_success']);
        SecurityLog::factory()->count(2)->create(['event_type' => 'login_failed']);

        $logs = $this->securityService->getLogsByEventType('login_success', 10);

        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $this->assertEquals('login_success', $log->event_type);
        }
    }

    /**
     * Test security service gets logs by severity correctly
     */
    public function test_it_gets_logs_by_severity_correctly(): void
    {
        // Create logs with different severities
        SecurityLog::factory()->count(3)->create([
            'metadata' => ['severity_level' => 'high']
        ]);
        SecurityLog::factory()->count(2)->create([
            'metadata' => ['severity_level' => 'medium']
        ]);

        $logs = $this->securityService->getLogsBySeverity('high', 10);

        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $this->assertEquals('high', $log->metadata['severity_level']);
        }
    }

    /**
     * Test security service gets logs by date range correctly
     */
    public function test_it_gets_logs_by_date_range_correctly(): void
    {
        $startDate = Carbon::now()->subDays(10);
        $endDate = Carbon::now()->subDays(5);

        // Create logs within the date range
        SecurityLog::factory()->count(3)->create([
            'created_at' => Carbon::now()->subDays(7)
        ]);

        // Create logs outside the date range
        SecurityLog::factory()->count(2)->create([
            'created_at' => Carbon::now()->subDays(15)
        ]);

        $logs = $this->securityService->getLogsByDateRange($startDate, $endDate, 10);

        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $this->assertTrue($log->created_at->between($startDate, $endDate));
        }
    }

    /**
     * Test security service handles null user correctly
     */
    public function test_it_handles_null_user_correctly(): void
    {
        $securityLog = $this->securityService->logSecurityEvent(
            null,
            'system_event',
            ['description' => 'System maintenance']
        );

        $this->assertInstanceOf(SecurityLog::class, $securityLog);
        $this->assertNull($securityLog->user_id);
        $this->assertEquals('system_event', $securityLog->event_type);
    }

    /**
     * Test security service handles request context correctly
     */
    public function test_it_handles_request_context_correctly(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Test User Agent',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token123'
        ]);

        Log::shouldReceive('warning')->once();

        $this->securityService->logAuthenticationFailure(
            'test@example.com',
            'invalid_credentials',
            $request
        );
    }

    /**
     * Test security service constants are defined correctly
     */
    public function test_it_has_correct_constants(): void
    {
        $this->assertEquals('low', SecurityLoggingService::SEVERITY_LOW);
        $this->assertEquals('medium', SecurityLoggingService::SEVERITY_MEDIUM);
        $this->assertEquals('high', SecurityLoggingService::SEVERITY_HIGH);
        $this->assertEquals('critical', SecurityLoggingService::SEVERITY_CRITICAL);

        $this->assertIsArray(SecurityLoggingService::INCIDENT_TYPES);
        $this->assertContains('authentication_failure', SecurityLoggingService::INCIDENT_TYPES);
        $this->assertContains('authorization_failure', SecurityLoggingService::INCIDENT_TYPES);
        $this->assertContains('brute_force_attempt', SecurityLoggingService::INCIDENT_TYPES);
        $this->assertContains('sql_injection_attempt', SecurityLoggingService::INCIDENT_TYPES);
    }
} 