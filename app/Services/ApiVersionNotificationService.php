<?php

namespace App\Services;

use App\Models\ApiVersion;
use App\Models\User;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class ApiVersionNotificationService
{
    /**
     * Send deprecation notifications for a version
     */
    public function sendDeprecationNotifications(ApiVersion $apiVersion): void
    {
        if (!$apiVersion->isDeprecated() && !$apiVersion->isSunset()) {
            return;
        }

        $this->sendEmailNotifications($apiVersion);
        $this->sendDashboardNotifications($apiVersion);
        $this->updateDocumentation($apiVersion);
        $this->logNotificationSent($apiVersion);
    }

    /**
     * Send email notifications to affected users
     */
    private function sendEmailNotifications(ApiVersion $apiVersion): void
    {
        $daysUntilSunset = $apiVersion->getDaysUntilSunset();
        
        // Determine notification urgency based on timeline
        $urgency = $this->getNotificationUrgency($daysUntilSunset);
        
        // Get users who should be notified
        $users = $this->getUsersToNotify($apiVersion);
        
        foreach ($users as $user) {
            try {
                $this->sendUserDeprecationEmail($user, $apiVersion, $urgency);
            } catch (\Exception $e) {
                Log::error('Failed to send deprecation email', [
                    'user_id' => $user->id,
                    'version' => $apiVersion->version,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send dashboard notifications
     */
    private function sendDashboardNotifications(ApiVersion $apiVersion): void
    {
        $daysUntilSunset = $apiVersion->getDaysUntilSunset();
        $urgency = $this->getNotificationUrgency($daysUntilSunset);
        
        // Store notification in cache for dashboard display
        $notificationKey = "api.deprecation.{$apiVersion->version}";
        $notification = [
            'version' => $apiVersion->version,
            'message' => $apiVersion->getDeprecationWarning(),
            'urgency' => $urgency,
            'sunset_date' => $apiVersion->sunset_date?->toISOString(),
            'days_remaining' => $daysUntilSunset,
            'migration_guide' => $apiVersion->getMigrationGuideUrl(),
            'successor_version' => $apiVersion->getSuccessorVersion()?->version,
            'created_at' => now()->toISOString(),
            'read_by' => []
        ];
        
        Cache::put($notificationKey, $notification, 86400 * 30); // 30 days
        
        // Also store in database for persistent notifications
        $this->storeDatabaseNotification($apiVersion, $urgency);
    }

    /**
     * Update documentation with deprecation notices
     */
    private function updateDocumentation(ApiVersion $apiVersion): void
    {
        $deprecationNotice = [
            'version' => $apiVersion->version,
            'status' => $apiVersion->status,
            'sunset_date' => $apiVersion->sunset_date?->toISOString(),
            'migration_guide' => $apiVersion->getMigrationGuideUrl(),
            'breaking_changes' => $apiVersion->breaking_changes,
            'last_updated' => now()->toISOString()
        ];
        
        // Store in cache for API documentation
        Cache::put("api.docs.deprecation.{$apiVersion->version}", $deprecationNotice, 86400);
        
        // Log documentation update
        Log::info('API documentation updated with deprecation notice', [
            'version' => $apiVersion->version,
            'sunset_date' => $apiVersion->sunset_date?->toISOString()
        ]);
    }

    /**
     * Get notification urgency based on days until sunset
     */
    private function getNotificationUrgency(?int $daysUntilSunset): string
    {
        if ($daysUntilSunset === null) {
            return 'info';
        }
        
        if ($daysUntilSunset <= 30) {
            return 'critical';
        } elseif ($daysUntilSunset <= 60) {
            return 'high';
        } elseif ($daysUntilSunset <= 90) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get users who should be notified about deprecation
     */
    private function getUsersToNotify(ApiVersion $apiVersion): \Illuminate\Database\Eloquent\Collection
    {
        // Get users who have used this API version recently
        $recentUsers = $this->getRecentVersionUsers($apiVersion->version);
        
        // Get admin users
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();
        
        // Get restaurant owners/managers
        $restaurantUsers = User::whereHas('restaurants')->get();
        
        // Combine and deduplicate
        $allUsers = $recentUsers->merge($adminUsers)->merge($restaurantUsers);
        
        return $allUsers->unique('id');
    }

    /**
     * Get users who have recently used a specific API version
     */
    private function getRecentVersionUsers(string $version): \Illuminate\Database\Eloquent\Collection
    {
        // Get from analytics cache
        $analyticsKey = "api.analytics." . date('Y-m-d-H');
        $analytics = Cache::get($analyticsKey, []);
        
        $userIds = collect($analytics)
            ->where('version', $version)
            ->pluck('user_id')
            ->filter()
            ->unique();
        
        if ($userIds->isEmpty()) {
            return collect();
        }
        
        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Send deprecation email to a specific user
     */
    private function sendUserDeprecationEmail(User $user, ApiVersion $apiVersion, string $urgency): void
    {
        $daysUntilSunset = $apiVersion->getDaysUntilSunset();
        $successor = $apiVersion->getSuccessorVersion();
        
        $emailData = [
            'user_name' => $user->name,
            'version' => $apiVersion->version,
            'sunset_date' => $apiVersion->sunset_date?->format('F j, Y'),
            'days_remaining' => $daysUntilSunset,
            'urgency' => $urgency,
            'migration_guide' => $apiVersion->getMigrationGuideUrl(),
            'successor_version' => $successor?->version,
            'breaking_changes' => $apiVersion->breaking_changes,
            'support_contact' => 'api-support@foodhub.com'
        ];
        
        // Send email based on urgency
        if ($urgency === 'critical') {
            Mail::send('emails.api.deprecation_critical', $emailData, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('URGENT: API Version Deprecation - Immediate Action Required');
            });
        } elseif ($urgency === 'high') {
            Mail::send('emails.api.deprecation_high', $emailData, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Important: API Version Deprecation - Action Required Soon');
            });
        } else {
            Mail::send('emails.api.deprecation_info', $emailData, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('API Version Deprecation Notice');
            });
        }
    }

    /**
     * Store notification in database for persistent storage
     */
    private function storeDatabaseNotification(ApiVersion $apiVersion, string $urgency): void
    {
        // This would integrate with your notification system
        // For now, we'll just log it
        Log::info('Database notification stored', [
            'version' => $apiVersion->version,
            'urgency' => $urgency,
            'sunset_date' => $apiVersion->sunset_date?->toISOString()
        ]);
    }

    /**
     * Log that notifications were sent
     */
    private function logNotificationSent(ApiVersion $apiVersion): void
    {
        Log::info('API version deprecation notifications sent', [
            'version' => $apiVersion->version,
            'status' => $apiVersion->status,
            'sunset_date' => $apiVersion->sunset_date?->toISOString(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get all active deprecation notifications
     */
    public static function getActiveDeprecationNotifications(): array
    {
        $notifications = [];
        
        // Get deprecated versions
        $deprecatedVersions = ApiVersion::whereIn('status', [
            ApiVersion::STATUS_DEPRECATED,
            ApiVersion::STATUS_SUNSET
        ])->get();
        
        foreach ($deprecatedVersions as $version) {
            $notificationKey = "api.deprecation.{$version->version}";
            $notification = Cache::get($notificationKey);
            
            if ($notification) {
                $notifications[] = $notification;
            }
        }
        
        return $notifications;
    }

    /**
     * Mark notification as read by user
     */
    public static function markNotificationAsRead(string $version, int $userId): void
    {
        $notificationKey = "api.deprecation.{$version}";
        $notification = Cache::get($notificationKey);
        
        if ($notification && !in_array($userId, $notification['read_by'])) {
            $notification['read_by'][] = $userId;
            Cache::put($notificationKey, $notification, 86400 * 30);
        }
    }

    /**
     * Schedule periodic deprecation reminders
     */
    public function scheduleDeprecationReminders(): void
    {
        $deprecatedVersions = ApiVersion::where('status', ApiVersion::STATUS_DEPRECATED)
            ->where('sunset_date', '>', now())
            ->get();
        
        foreach ($deprecatedVersions as $version) {
            $daysUntilSunset = $version->getDaysUntilSunset();
            
            // Send reminders at key intervals
            if ($daysUntilSunset === 30 || $daysUntilSunset === 7 || $daysUntilSunset === 1) {
                $this->sendDeprecationNotifications($version);
            }
        }
    }
}
