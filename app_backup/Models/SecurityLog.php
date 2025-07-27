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
        array $metadata = []
    ): self {
        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'session_id' => $sessionId ?? session()->getId(),
            'metadata' => $metadata,
        ]);
    }
}
