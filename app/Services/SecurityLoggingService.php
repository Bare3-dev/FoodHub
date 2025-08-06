<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\SecurityLog;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;

/**
 * Security Incident Logging Service
 * 
 * Centralized security event logging and monitoring system for comprehensive
 * security incident tracking, threat detection, and audit compliance.
 * 
 * Features:
 * - Centralized security event logging
 * - Threat pattern detection
 * - Real-time security alerts
 * - Audit trail generation
 * - Security metrics collection
 * - Incident classification and severity levels
 */
class SecurityLoggingService
{
    /**
     * Security incident severity levels
     */
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Security incident types
     */
    public const INCIDENT_TYPES = [
        'authentication_failure',
        'authorization_failure',
        'brute_force_attempt',
        'rate_limit_exceeded',
        'suspicious_activity',
        'data_access_violation',
        'encryption_failure',
        'session_hijacking',
        'csrf_attack',
        'xss_attempt',
        'sql_injection_attempt',
        'file_upload_violation',
        'privilege_escalation',
        'account_lockout',
        'suspicious_location',
        'api_abuse',
        'data_export_violation',
    ];

    /**
     * Log a security incident
     */
    public function logSecurityIncident(
        string $type,
        string $severity,
        string $description,
        array $context = [],
        ?Request $request = null
    ): void {
        $request = $request ?? request();
        
        $incident = [
            'incident_type' => $type,
            'severity' => $severity,
            'description' => $description,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'headers' => $this->getSafeHeaders($request),
            'context' => $context,
            'incident_id' => $this->generateIncidentId(),
        ];

        // Log based on severity
        match ($severity) {
            self::SEVERITY_CRITICAL => Log::critical('Security Incident - CRITICAL', $incident),
            self::SEVERITY_HIGH => Log::error('Security Incident - HIGH', $incident),
            self::SEVERITY_MEDIUM => Log::warning('Security Incident - MEDIUM', $incident),
            self::SEVERITY_LOW => Log::info('Security Incident - LOW', $incident),
            default => Log::notice('Security Incident - UNKNOWN', $incident),
        };

        // Track incident patterns
        $this->trackIncidentPatterns($type, $incident);

        // Trigger alerts for high severity incidents
        if (in_array($severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL])) {
            $this->triggerSecurityAlert($incident);
        }
    }

    /**
     * Log authentication failures
     */
    public function logAuthenticationFailure(
        string $email,
        string $reason,
        ?Request $request = null
    ): void {
        $this->logSecurityIncident(
            'authentication_failure',
            self::SEVERITY_MEDIUM,
            "Authentication failed for user: {$email}",
            [
                'email' => $email,
                'failure_reason' => $reason,
                'attempt_time' => now()->toISOString(),
            ],
            $request
        );

        // Track brute force attempts
        $this->trackBruteForceAttempts($email, $request?->ip());
    }

    /**
     * Log authorization failures
     */
    public function logAuthorizationFailure(
        string $resource,
        string $action,
        ?Request $request = null
    ): void {
        $this->logSecurityIncident(
            'authorization_failure',
            self::SEVERITY_MEDIUM,
            "Authorization failed for action: {$action} on resource: {$resource}",
            [
                'resource' => $resource,
                'action' => $action,
                'user_role' => auth()->user()?->role,
                'user_permissions' => auth()->user()?->permissions ?? [],
            ],
            $request
        );
    }

    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity(
        string $activity,
        array $details = [],
        ?Request $request = null
    ): void {
        $severity = $this->calculateActivitySeverity($activity, $details);
        
        $this->logSecurityIncident(
            'suspicious_activity',
            $severity,
            "Suspicious activity detected: {$activity}",
            array_merge($details, [
                'activity_type' => $activity,
                'detection_time' => now()->toISOString(),
            ]),
            $request
        );
    }

    /**
     * Log data access violations
     */
    public function logDataAccessViolation(
        string $dataType,
        string $violation,
        array $context = [],
        ?Request $request = null
    ): void {
        $this->logSecurityIncident(
            'data_access_violation',
            self::SEVERITY_HIGH,
            "Data access violation: {$violation} for {$dataType}",
            array_merge($context, [
                'data_type' => $dataType,
                'violation_type' => $violation,
                'access_time' => now()->toISOString(),
            ]),
            $request
        );
    }

    /**
     * Log potential attack attempts
     */
    public function logAttackAttempt(
        string $attackType,
        array $evidence = [],
        ?Request $request = null
    ): void {
        $severity = match ($attackType) {
            'sql_injection_attempt', 'xss_attempt' => self::SEVERITY_CRITICAL,
            'csrf_attack', 'session_hijacking' => self::SEVERITY_HIGH,
            default => self::SEVERITY_MEDIUM,
        };

        $this->logSecurityIncident(
            $attackType,
            $severity,
            "Potential {$attackType} detected",
            array_merge($evidence, [
                'attack_type' => $attackType,
                'detection_method' => 'automated',
                'raw_input' => $this->sanitizeForLogging($evidence['raw_input'] ?? ''),
            ]),
            $request
        );

        // Immediately block suspicious IPs for critical attacks
        if ($severity === self::SEVERITY_CRITICAL) {
            $this->handleCriticalThreat($request?->ip(), $attackType);
        }
    }

    /**
     * Track brute force attempts
     */
    private function trackBruteForceAttempts(string $email, ?string $ip): void
    {
        $emailKey = "brute_force:email:{$email}";
        $ipKey = "brute_force:ip:{$ip}";
        $window = 900; // 15 minutes

        $emailAttempts = Cache::get($emailKey, 0) + 1;
        $ipAttempts = Cache::get($ipKey, 0) + 1;

        Cache::put($emailKey, $emailAttempts, $window);
        Cache::put($ipKey, $ipAttempts, $window);

        // Log brute force pattern detection
        if ($emailAttempts >= 5 || $ipAttempts >= 10) {
            $this->logSecurityIncident(
                'brute_force_attempt',
                self::SEVERITY_HIGH,
                'Brute force attack pattern detected',
                [
                    'email_attempts' => $emailAttempts,
                    'ip_attempts' => $ipAttempts,
                    'target_email' => $email,
                    'source_ip' => $ip,
                    'detection_threshold' => 'exceeded',
                ]
            );
        }
    }

    /**
     * Track incident patterns for threat intelligence
     */
    private function trackIncidentPatterns(string $type, array $incident): void
    {
        $patternKey = "security_pattern:{$type}";
        $hourlyKey = $patternKey . ':' . now()->format('Y-m-d-H');
        
        $hourlyCount = Cache::get($hourlyKey, 0) + 1;
        Cache::put($hourlyKey, $hourlyCount, 3600); // 1 hour

        // Alert on unusual pattern spikes
        if ($hourlyCount > $this->getPatternThreshold($type)) {
            $this->logSecurityIncident(
                'suspicious_activity',
                self::SEVERITY_HIGH,
                "Unusual spike in {$type} incidents",
                [
                    'incident_type' => $type,
                    'hourly_count' => $hourlyCount,
                    'threshold' => $this->getPatternThreshold($type),
                    'time_window' => now()->format('Y-m-d H:00'),
                ]
            );
        }
    }

    /**
     * Trigger security alerts for high-severity incidents
     */
    private function triggerSecurityAlert(array $incident): void
    {
        // In a production environment, this would:
        // 1. Send alerts to security team via email/Slack/SMS
        // 2. Update security dashboard
        // 3. Trigger automated response procedures
        // 4. Create incident tickets

        Log::alert('SECURITY ALERT TRIGGERED', [
            'alert_type' => 'high_severity_incident',
            'incident' => $incident,
            'alert_timestamp' => now()->toISOString(),
            'requires_immediate_attention' => true,
        ]);
    }

    /**
     * Handle critical security threats
     */
    private function handleCriticalThreat(?string $ip, string $threatType): void
    {
        if (!$ip) return;

        // Add IP to temporary block list
        $blockKey = "blocked_ip:{$ip}";
        Cache::put($blockKey, [
            'reason' => $threatType,
            'blocked_at' => now()->toISOString(),
            'expires_at' => now()->addHour()->toISOString(),
        ], 3600);

        Log::critical('IP address temporarily blocked due to critical threat', [
            'ip' => $ip,
            'threat_type' => $threatType,
            'block_duration' => '1 hour',
            'action' => 'automatic_block',
        ]);
    }

    /**
     * Calculate activity severity based on context
     */
    private function calculateActivitySeverity(string $activity, array $details): string
    {
        // Implement severity calculation logic based on activity type and context
        $highRiskActivities = [
            'multiple_failed_logins',
            'unusual_data_access_pattern',
            'privilege_escalation_attempt',
            'suspicious_file_upload',
        ];

        $criticalActivities = [
            'admin_account_compromise',
            'mass_data_export',
            'encryption_key_access',
            'system_configuration_change',
        ];

        if (in_array($activity, $criticalActivities)) {
            return self::SEVERITY_CRITICAL;
        }

        if (in_array($activity, $highRiskActivities)) {
            return self::SEVERITY_HIGH;
        }

        return self::SEVERITY_MEDIUM;
    }

    /**
     * Get safe headers for logging (exclude sensitive information)
     */
    private function getSafeHeaders(?Request $request): array
    {
        if (!$request) return [];

        $safeHeaders = [];
        $sensitiveHeaders = ['authorization', 'cookie', 'x-csrf-token', 'x-api-key'];

        foreach ($request->headers->all() as $key => $values) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $safeHeaders[$key] = '[REDACTED]';
            } else {
                $safeHeaders[$key] = $values;
            }
        }

        return $safeHeaders;
    }

    /**
     * Sanitize input data for safe logging
     */
    private function sanitizeForLogging(string $input): string
    {
        // Truncate long inputs and remove potentially malicious content
        $sanitized = substr($input, 0, 500);
        $sanitized = preg_replace('/[^\w\s\-_@.,!?()[\]{}]/', '[FILTERED]', $sanitized);
        return $sanitized;
    }

    /**
     * Get pattern threshold for incident type
     */
    private function getPatternThreshold(string $type): int
    {
        return match ($type) {
            'authentication_failure' => 20,
            'authorization_failure' => 15,
            'rate_limit_exceeded' => 50,
            'suspicious_activity' => 10,
            default => 25,
        };
    }

    /**
     * Generate unique incident ID
     */
    private function generateIncidentId(): string
    {
        return 'SEC-' . now()->format('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    /**
     * Generate security metrics report
     */
    public function generateSecurityReport(Carbon $startDate, Carbon $endDate): array
    {
        // In a real implementation, this would query log files or database
        return [
            'report_period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'total_incidents' => 0, // Would count actual incidents
            'incidents_by_severity' => [
                self::SEVERITY_CRITICAL => 0,
                self::SEVERITY_HIGH => 0,
                self::SEVERITY_MEDIUM => 0,
                self::SEVERITY_LOW => 0,
            ],
            'incidents_by_type' => [], // Would group by incident type
            'top_threat_sources' => [], // Would identify top threat IPs
            'security_metrics' => [
                'average_response_time' => '< 5 minutes',
                'blocked_threats' => 0,
                'false_positives' => 0,
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Log a security event to the database
     */
    public function logSecurityEvent(
        ?User $user,
        string $eventType,
        array $details = [],
        string $severity = 'info',
        ?string $targetType = null,
        ?int $targetId = null
    ): SecurityLog {
        if (empty($eventType)) {
            throw new \InvalidArgumentException('Event type cannot be empty');
        }
        
        // Mask sensitive data
        $maskedDetails = $this->maskSensitiveData($details);
        
        return SecurityLog::logEvent(
            $eventType,
            $user?->id,
            request()->ip(),
            request()->userAgent(),
            session()->getId(),
            array_merge($maskedDetails, [
                'severity_level' => $severity,
                'logged_at' => now()->toISOString(),
            ]),
            $targetType,
            $targetId
        );
    }

    /**
     * Mask sensitive data in log details
     */
    private function maskSensitiveData(array $details): array
    {
        $sensitiveKeys = [
            'password', 'password_hash', 'old_password_hash', 'new_password_hash',
            'token', 'api_key', 'secret', 'key', 'credential'
        ];
        
        $masked = [];
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value) && $this->isSensitiveKey($key, $sensitiveKeys)) {
                $masked[$key] = '***' . substr($value, -4);
            } else {
                $masked[$key] = $value;
            }
        }
        
        return $masked;
    }

    /**
     * Check if a key contains sensitive information
     */
    private function isSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        $lowerKey = strtolower($key);
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains($lowerKey, $sensitiveKey)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Log a user action with resource details
     */
    public function logUserAction(
        User $user,
        string $action,
        string $resource,
        int $resourceId,
        array $changes = []
    ): SecurityLog {
        return $this->logSecurityEvent($user, $action, [
            'resource' => $resource,
            'resource_id' => $resourceId,
            'changes' => $changes,
            'action_timestamp' => now()->toISOString(),
        ], 'info', $resource, $resourceId);
    }

    /**
     * Rotate old security logs (delete logs older than 90 days)
     */
    public function rotateOldLogs(): int
    {
        $cutoffDate = now()->subDays(90);
        return SecurityLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get logs by user
     */
    public function getLogsByUser(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by event type
     */
    public function getLogsByEventType(string $eventType, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityLog::where('event_type', $eventType)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by severity level
     */
    public function getLogsBySeverity(string $severity, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityLog::whereJsonContains('metadata->severity_level', $severity)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by date range
     */
    public function getLogsByDateRange(Carbon $startDate, Carbon $endDate, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityLog::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Detect suspicious activity for a user and return detection result
     */
    public function detectSuspiciousActivity(User $user): bool
    {
        $suspiciousIndicators = 0;
        $threshold = 3; // Number of indicators needed to trigger suspicion

        // Check for multiple failed login attempts
        $recentFailures = SecurityLog::where('user_id', $user->id)
            ->where('event_type', 'authentication_failure')
            ->where('created_at', '>=', now()->subHours(1))
            ->count();

        if ($recentFailures >= 5) {
            $suspiciousIndicators++;
        }

        // Check for unusual access patterns (multiple locations in short time)
        $recentAccess = SecurityLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->get();

        $uniqueIPs = $recentAccess->pluck('ip_address')->unique()->count();
        if ($uniqueIPs > 3) {
            $suspiciousIndicators++;
        }

        // Check for rapid resource access
        $rapidAccess = SecurityLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($rapidAccess > 20) {
            $suspiciousIndicators++;
        }

        // Check for access to sensitive resources
        $sensitiveAccess = SecurityLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereJsonContains('metadata->resource', ['admin', 'users', 'security', 'financial'])
            ->count();

        if ($sensitiveAccess > 10) {
            $suspiciousIndicators++;
        }

        // Log the detection attempt
        $this->logSecurityEvent($user, 'suspicious_activity_detection', [
            'indicators_found' => $suspiciousIndicators,
            'threshold' => $threshold,
            'is_suspicious' => $suspiciousIndicators >= $threshold,
        ], $suspiciousIndicators >= $threshold ? 'high' : 'info');

        return $suspiciousIndicators >= $threshold;
    }

    /**
     * Audit all data access (not just violations)
     */
    public function auditDataAccess(User $user, string $resource): void
    {
        $this->logSecurityEvent($user, 'data_access_audit', [
            'resource' => $resource,
            'access_time' => now()->toISOString(),
            'user_role' => $user->role,
            'user_permissions' => $user->permissions ?? [],
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], 'info', $resource);
    }

    /**
     * Validate API permissions for a user and endpoint
     */
    public function validateAPIPermissions(User $user, string $endpoint): bool
    {
        // Super admins have all permissions
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Parse endpoint to determine required permissions
        $requiredPermissions = $this->parseEndpointPermissions($endpoint);
        
        if (empty($requiredPermissions)) {
            return true; // No specific permissions required
        }

        // Check if user has all required permissions
        $hasAllPermissions = collect($requiredPermissions)->every(function ($permission) use ($user) {
            return $user->hasPermission($permission);
        });

        // Log the validation attempt
        $this->logSecurityEvent($user, 'api_permission_validation', [
            'endpoint' => $endpoint,
            'required_permissions' => $requiredPermissions,
            'user_permissions' => $user->permissions ?? [],
            'validation_result' => $hasAllPermissions,
        ], $hasAllPermissions ? 'info' : 'medium');

        return $hasAllPermissions;
    }

    /**
     * Parse endpoint to determine required permissions
     */
    private function parseEndpointPermissions(string $endpoint): array
    {
        $permissionMap = [
            // User management
            '/api/users' => ['user:read'],
            '/api/users/*' => ['user:read'],
            '/api/users/create' => ['user:create'],
            '/api/users/*/edit' => ['user:update'],
            '/api/users/*/delete' => ['user:delete'],
            
            // Restaurant management
            '/api/restaurants' => ['restaurant:read'],
            '/api/restaurants/*' => ['restaurant:read'],
            '/api/restaurants/create' => ['restaurant:create'],
            '/api/restaurants/*/edit' => ['restaurant:update'],
            '/api/restaurants/*/delete' => ['restaurant:delete'],
            
            // Branch management
            '/api/branches' => ['branch:read'],
            '/api/branches/*' => ['branch:read'],
            '/api/branches/create' => ['branch:create'],
            '/api/branches/*/edit' => ['branch:update'],
            '/api/branches/*/delete' => ['branch:delete'],
            
            // Order management
            '/api/orders' => ['order:read'],
            '/api/orders/*' => ['order:read'],
            '/api/orders/create' => ['order:create'],
            '/api/orders/*/edit' => ['order:update'],
            '/api/orders/*/delete' => ['order:delete'],
            
            // Customer management
            '/api/customers' => ['customer:read'],
            '/api/customers/*' => ['customer:read'],
            '/api/customers/create' => ['customer:create'],
            '/api/customers/*/edit' => ['customer:update'],
            '/api/customers/*/delete' => ['customer:delete'],
            
            // Analytics and reports
            '/api/analytics' => ['analytics:read'],
            '/api/reports' => ['reports:read'],
            '/api/dashboard' => ['dashboard:read'],
            
            // System administration
            '/api/admin' => ['admin:access'],
            '/api/admin/*' => ['admin:access'],
            '/api/settings' => ['settings:read'],
            '/api/settings/*' => ['settings:update'],
        ];

        // Find exact match first
        if (isset($permissionMap[$endpoint])) {
            return $permissionMap[$endpoint];
        }

        // Find pattern match
        foreach ($permissionMap as $pattern => $permissions) {
            if (str_contains($pattern, '*')) {
                $regexPattern = str_replace('*', '.*', $pattern);
                if (preg_match("#^{$regexPattern}$#", $endpoint)) {
                    return $permissions;
                }
            }
        }

        return []; // No specific permissions required
    }

    /**
     * Log successful payment
     */
    public function logPaymentSuccess(Order $order, Payment $payment): void
    {
        // For customers, we'll log without a user since they're not User model
        $this->logSecurityEvent(
            null, // No user for customer payments
            'payment_success',
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
                'customer_id' => $order->customer_id,
                'restaurant_id' => $order->restaurant_id,
                'customer_email' => $order->customer->email ?? null,
                'customer_name' => $order->customer->first_name . ' ' . $order->customer->last_name ?? null,
            ],
            'info',
            'orders',
            $order->id
        );
    }

    /**
     * Log failed payment
     */
    public function logPaymentFailure(Order $order, Payment $payment, string $errorMessage): void
    {
        $this->logSecurityEvent(
            null, // No user for customer payments
            'payment_failure',
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
                'customer_id' => $order->customer_id,
                'restaurant_id' => $order->restaurant_id,
                'error_message' => $errorMessage,
                'customer_email' => $order->customer->email ?? null,
                'customer_name' => $order->customer->first_name . ' ' . $order->customer->last_name ?? null,
            ],
            'warning',
            'orders',
            $order->id
        );
    }

    /**
     * Log payment refund
     */
    public function logPaymentRefund(Order $order, Payment $payment, float $refundAmount): void
    {
        $this->logSecurityEvent(
            null, // No user for customer payments
            'payment_refund',
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'original_amount' => $payment->amount,
                'refund_amount' => $refundAmount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
                'customer_id' => $order->customer_id,
                'restaurant_id' => $order->restaurant_id,
                'customer_email' => $order->customer->email ?? null,
                'customer_name' => $order->customer->first_name . ' ' . $order->customer->last_name ?? null,
            ],
            'info',
            'orders',
            $order->id
        );
    }
} 