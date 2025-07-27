<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\SecurityLoggingService;

/**
 * Security Exception
 * 
 * For handling security-related errors and violations
 * with automatic security logging and threat response
 */
class SecurityException extends Exception
{
    protected string $errorCode;
    protected array $context;
    protected int $statusCode;
    protected string $severity;

    public function __construct(
        string $message = 'Security violation detected',
        string $errorCode = 'SECURITY_VIOLATION',
        array $context = [],
        int $statusCode = 403,
        string $severity = 'medium'
    ) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->statusCode = $statusCode;
        $this->severity = $severity;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function render(Request $request = null): JsonResponse
    {
        // Log security incident
        if ($request) {
            $this->logSecurityIncident($request);
        }

        // Return secure error response (minimal information exposure)
        return response()->json([
            'error' => 'Security Violation',
            'message' => 'Access denied due to security policy.',
            'error_code' => $this->errorCode,
            'timestamp' => now()->toISOString(),
            'request_id' => 'req_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 8)),
        ], $this->statusCode);
    }

    protected function logSecurityIncident(?Request $request): void
    {
        try {
            $securityLogger = app(SecurityLoggingService::class);
            $securityLogger->logSecurityIncident(
                'security_exception',
                $this->severity,
                $this->getMessage(),
                array_merge($this->context, [
                    'error_code' => $this->errorCode,
                    'exception_class' => get_class($this),
                ]),
                $request
            );
        } catch (\Throwable $e) {
            // Fallback logging if security logger fails
            logger()->error('Security exception occurred', [
                'message' => $this->getMessage(),
                'error_code' => $this->errorCode,
                'context' => $this->context,
            ]);
        }
    }

    /**
     * Common security exceptions
     */
    public static function suspiciousActivity(string $activity, array $evidence = []): self
    {
        return new self(
            "Suspicious activity detected: {$activity}",
            'SUSPICIOUS_ACTIVITY',
            array_merge(['activity' => $activity], $evidence),
            403,
            'high'
        );
    }

    public static function ipBlocked(string $ip, string $reason): self
    {
        return new self(
            "Access denied from IP address {$ip}",
            'IP_BLOCKED',
            ['ip' => $ip, 'reason' => $reason],
            403,
            'medium'
        );
    }

    public static function accountLocked(string $userId, string $reason): self
    {
        return new self(
            'Account has been temporarily locked due to security concerns.',
            'ACCOUNT_LOCKED',
            ['user_id' => $userId, 'reason' => $reason],
            423,
            'high'
        );
    }

    public static function invalidSecurityToken(string $tokenType): self
    {
        return new self(
            "Invalid or expired {$tokenType} token.",
            'INVALID_SECURITY_TOKEN',
            ['token_type' => $tokenType],
            401,
            'medium'
        );
    }

    public static function encryptionFailure(string $operation): self
    {
        return new self(
            'Data encryption/decryption operation failed.',
            'ENCRYPTION_FAILURE',
            ['operation' => $operation],
            500,
            'critical'
        );
    }

    public static function dataAccessViolation(string $resourceType, string $resourceId): self
    {
        return new self(
            'Unauthorized access attempt to protected data.',
            'DATA_ACCESS_VIOLATION',
            ['resource_type' => $resourceType, 'resource_id' => $resourceId],
            403,
            'high'
        );
    }

    public static function mfaRequired(): self
    {
        return new self(
            'Multi-factor authentication is required for this action.',
            'MFA_REQUIRED',
            [],
            428, // 428 Precondition Required
            'low'
        );
    }

    public static function sessionHijackAttempt(string $sessionId): self
    {
        return new self(
            'Potential session hijacking attempt detected.',
            'SESSION_HIJACK_ATTEMPT',
            ['session_id' => $sessionId],
            403,
            'critical'
        );
    }

    public static function bruteForceAttempt(string $target, int $attemptCount): self
    {
        return new self(
            "Brute force attack detected against {$target}",
            'BRUTE_FORCE_ATTEMPT',
            ['target' => $target, 'attempt_count' => $attemptCount],
            429,
            'critical'
        );
    }

    public static function privilegeEscalationAttempt(string $attemptedAction): self
    {
        return new self(
            'Unauthorized privilege escalation attempt detected.',
            'PRIVILEGE_ESCALATION_ATTEMPT',
            ['attempted_action' => $attemptedAction],
            403,
            'critical'
        );
    }
} 