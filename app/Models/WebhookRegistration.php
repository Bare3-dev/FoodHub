<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class WebhookRegistration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'service',
        'event_type',
        'webhook_url',
        'webhook_id',
        'signature_key',
        'is_active',
        'configuration',
        'last_verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'configuration' => 'array',
            'last_verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope for active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Get active webhook registrations for a service.
     */
    public static function getActiveRegistrations(string $service): \Illuminate\Database\Eloquent\Collection
    {
        return static::service($service)->active()->get();
    }

    /**
     * Check if a webhook registration exists for a service and event.
     */
    public static function exists(string $service, string $eventType, string $url): bool
    {
        return static::where([
            'service' => $service,
            'event_type' => $eventType,
            'webhook_url' => $url,
        ])->exists();
    }

    /**
     * Update last verified timestamp.
     */
    public function markAsVerified(): void
    {
        $this->update(['last_verified_at' => now()]);
    }

    /**
     * Deactivate the webhook registration.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activate the webhook registration.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }
} 