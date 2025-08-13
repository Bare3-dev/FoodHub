<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ApiVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'status',
        'release_date',
        'sunset_date',
        'migration_guide_url',
        'breaking_changes',
        'is_default',
        'min_client_version',
        'max_client_version',
        'notes'
    ];

    protected $casts = [
        'release_date' => 'datetime',
        'sunset_date' => 'datetime',
        'is_default' => 'boolean',
        'breaking_changes' => 'array'
    ];

    // Version status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_DEPRECATED = 'deprecated';
    const STATUS_SUNSET = 'sunset';
    const STATUS_BETA = 'beta';

    /**
     * Get the current active version
     */
    public static function getCurrentVersion(): ?self
    {
        return static::where('status', self::STATUS_ACTIVE)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get all deprecated versions
     */
    public static function getDeprecatedVersions()
    {
        return static::where('status', self::STATUS_DEPRECATED)
            ->where('sunset_date', '>', now())
            ->orderBy('sunset_date')
            ->get();
    }

    /**
     * Get all sunset versions
     */
    public static function getSunsetVersions()
    {
        return static::where('status', self::STATUS_SUNSET)
            ->orWhere('sunset_date', '<=', now())
            ->get();
    }

    /**
     * Check if version is deprecated
     */
    public function isDeprecated(): bool
    {
        return $this->status === self::STATUS_DEPRECATED;
    }

    /**
     * Check if version is sunset
     */
    public function isSunset(): bool
    {
        return $this->status === self::STATUS_SUNSET || 
               ($this->sunset_date && $this->sunset_date->isPast());
    }

    /**
     * Check if version is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get days until sunset
     */
    public function getDaysUntilSunset(): ?int
    {
        if (!$this->sunset_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->sunset_date, false));
    }

    /**
     * Get deprecation warning message
     */
    public function getDeprecationWarning(): ?string
    {
        if (!$this->isDeprecated() && !$this->isSunset()) {
            return null;
        }

        if ($this->isSunset()) {
            return "API version {$this->version} has been sunset and is no longer supported.";
        }

        $daysLeft = $this->getDaysUntilSunset();
        return "API version {$this->version} will be sunset on {$this->sunset_date->format('Y-m-d')} ({$daysLeft} days remaining).";
    }

    /**
     * Get successor version if available
     */
    public function getSuccessorVersion(): ?self
    {
        // Find the next version by release date (including beta versions)
        return static::where('release_date', '>', $this->release_date)
            ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_BETA])
            ->orderBy('release_date')
            ->first();
    }

    /**
     * Get migration guide URL
     */
    public function getMigrationGuideUrl(): ?string
    {
        if ($this->migration_guide_url) {
            return $this->migration_guide_url;
        }

        $successor = $this->getSuccessorVersion();
        if ($successor && $successor->migration_guide_url) {
            return $successor->migration_guide_url;
        }

        return null;
    }

    /**
     * Scope for active versions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for deprecated versions
     */
    public function scopeDeprecated($query)
    {
        return $query->where('status', self::STATUS_DEPRECATED);
    }

    /**
     * Scope for sunset versions
     */
    public function scopeSunset($query)
    {
        return $query->where('status', self::STATUS_SUNSET)
            ->orWhere('sunset_date', '<=', now());
    }

    /**
     * Get version usage statistics
     */
    public function getUsageStats(): array
    {
        // This would be populated by the VersionAnalyticsMiddleware
        return [
            'total_requests' => 0,
            'unique_clients' => 0,
            'error_rate' => 0.0,
            'last_used' => null,
            'popular_endpoints' => []
        ];
    }
}
