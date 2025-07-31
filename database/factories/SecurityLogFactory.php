<?php

namespace Database\Factories;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SecurityLog>
 */
class SecurityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_type' => $this->faker->randomElement([
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
            ]),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'session_id' => $this->faker->uuid(),
            'metadata' => [
                'description' => $this->faker->sentence(),
                'details' => $this->faker->paragraph(),
                'timestamp' => $this->faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
                'additional_info' => $this->faker->randomElements([
                    'ip_location' => $this->faker->city(),
                    'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                    'os' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
                    'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
                    'referrer' => $this->faker->url(),
                    'request_method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
                    'response_code' => $this->faker->randomElement([200, 201, 400, 401, 403, 404, 500]),
                    'processing_time' => $this->faker->numberBetween(10, 5000),
                    'memory_usage' => $this->faker->numberBetween(1024, 102400),
                    'database_queries' => $this->faker->numberBetween(1, 50),
                    'cache_hits' => $this->faker->numberBetween(0, 100),
                    'cache_misses' => $this->faker->numberBetween(0, 20),
                ], $this->faker->numberBetween(3, 8))
            ],
        ];
    }

    /**
     * Indicate that the security log is for a login success event.
     */
    public function loginSuccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'login_success',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'login_method' => $this->faker->randomElement(['email', 'username', 'phone']),
                'login_time' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s'),
                'session_duration' => $this->faker->numberBetween(300, 3600),
            ]),
        ]);
    }

    /**
     * Indicate that the security log is for a login failure event.
     */
    public function loginFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'login_failed',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'attempted_email' => $this->faker->email(),
                'failure_reason' => $this->faker->randomElement([
                    'invalid_credentials',
                    'account_locked',
                    'account_disabled',
                    'too_many_attempts',
                    'invalid_captcha'
                ]),
                'attempt_count' => $this->faker->numberBetween(1, 10),
            ]),
        ]);
    }

    /**
     * Indicate that the security log is for a high severity event.
     */
    public function highSeverity(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $this->faker->randomElement([
                'permission_denied',
                'data_access_violation',
                'suspicious_activity',
                'brute_force_attempt'
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'severity_level' => 'high',
                'requires_attention' => true,
                'alert_sent' => true,
            ]),
        ]);
    }

    /**
     * Indicate that the security log is for a critical severity event.
     */
    public function criticalSeverity(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $this->faker->randomElement([
                'sql_injection_attempt',
                'xss_attempt',
                'csrf_attack',
                'session_hijacking'
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'severity_level' => 'critical',
                'requires_immediate_attention' => true,
                'ip_blocked' => true,
                'alert_sent' => true,
            ]),
        ]);
    }

    /**
     * Indicate that the security log is for a system event.
     */
    public function systemEvent(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'event_type' => $this->faker->randomElement([
                'system_error',
                'database_backup',
                'cache_cleared',
                'maintenance_mode_enabled',
                'maintenance_mode_disabled',
                'error_occurred'
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'system_component' => $this->faker->randomElement([
                    'database', 'cache', 'queue', 'file_system', 'api_gateway'
                ]),
                'error_code' => $this->faker->randomElement(['E001', 'E002', 'E003', 'E500']),
                'affected_services' => $this->faker->randomElements([
                    'user_authentication', 'order_processing', 'payment_gateway', 
                    'notification_service', 'reporting_engine'
                ], $this->faker->numberBetween(1, 3)),
            ]),
        ]);
    }

    /**
     * Indicate that the security log is for a data access event.
     */
    public function dataAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $this->faker->randomElement([
                'data_export',
                'data_import',
                'data_deletion',
                'data_modification'
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'table' => $this->faker->randomElement([
                    'customers', 'orders', 'menu_items', 'restaurants', 'users'
                ]),
                'record_count' => $this->faker->numberBetween(1, 1000),
                'format' => $this->faker->randomElement(['csv', 'json', 'xml', 'pdf']),
                'source' => $this->faker->randomElement(['external_api', 'manual_upload', 'scheduled_job']),
                'reason' => $this->faker->randomElement(['backup', 'cleanup', 'migration', 'reporting']),
            ]),
        ]);
    }
} 