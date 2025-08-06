<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class WebhookLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'service',
        'event_type',
        'payload',
        'success',
        'ip_address',
        'user_agent',
        'signature_verified',
        'response_time_ms',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'success' => 'boolean',
            'signature_verified' => 'boolean',
            'response_time_ms' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope for successful webhooks.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope for failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope for a specific service.
     */
    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope for a specific event type.
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for recent webhooks.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the success rate for a service and event type.
     */
    public static function getSuccessRate(string $service, string $eventType, int $hours = 24): float
    {
        $total = static::service($service)
            ->eventType($eventType)
            ->recent($hours)
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $successful = static::service($service)
            ->eventType($eventType)
            ->recent($hours)
            ->successful()
            ->count();

        return round(($successful / $total) * 100, 2);
    }
} 