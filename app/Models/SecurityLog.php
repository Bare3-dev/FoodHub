<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'session_id',
        'target_type',
        'target_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that triggered this security event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a security event to the database
     */
    public static function logEvent(
        string $eventType,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        array $metadata = [],
        ?string $targetType = null,
        ?int $targetId = null
    ): self {
        // Skip logging in testing environment unless explicitly enabled
        if (app()->environment('testing') && !config('security.logging_enabled_in_tests', false)) {
            return new self(); // Return empty model for tests
        }

        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'session_id' => $sessionId ?? session()->getId(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
        ]);
    }
}
