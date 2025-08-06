<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class WebhookStatistics extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'service',
        'event_type',
        'total_received',
        'successful_processed',
        'failed_processed',
        'average_response_time_ms',
        'last_received_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'total_received' => 'integer',
            'successful_processed' => 'integer',
            'failed_processed' => 'integer',
            'average_response_time_ms' => 'integer',
            'last_received_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
     * Get the success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_received === 0) {
            return 0.0;
        }

        return round(($this->successful_processed / $this->total_received) * 100, 2);
    }

    /**
     * Get the failure rate percentage.
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_received === 0) {
            return 0.0;
        }

        return round(($this->failed_processed / $this->total_received) * 100, 2);
    }

    /**
     * Increment total received count.
     */
    public function incrementReceived(): void
    {
        $this->increment('total_received');
        $this->update(['last_received_at' => now()]);
    }

    /**
     * Increment successful processed count.
     */
    public function incrementSuccessful(): void
    {
        $this->increment('successful_processed');
        $this->update(['last_received_at' => now()]);
    }

    /**
     * Increment failed processed count.
     */
    public function incrementFailed(): void
    {
        $this->increment('failed_processed');
        $this->update(['last_received_at' => now()]);
    }

    /**
     * Update average response time.
     */
    public function updateAverageResponseTime(int $responseTime): void
    {
        $currentAvg = $this->average_response_time_ms ?? 0;
        $totalProcessed = $this->successful_processed + $this->failed_processed;
        
        if ($totalProcessed > 0) {
            $newAvg = (($currentAvg * ($totalProcessed - 1)) + $responseTime) / $totalProcessed;
            $this->update(['average_response_time_ms' => (int) $newAvg]);
        } else {
            $this->update(['average_response_time_ms' => $responseTime]);
        }
    }

    /**
     * Get statistics for a service and event type.
     */
    public static function getStatistics(string $service, string $eventType): ?self
    {
        return static::where([
            'service' => $service,
            'event_type' => $eventType,
        ])->first();
    }
} 