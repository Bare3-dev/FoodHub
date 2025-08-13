<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiVersionNotificationService;
use App\Models\ApiVersion;
use Illuminate\Support\Facades\Log;

class SendApiVersionDeprecationReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api:send-deprecation-reminders 
                            {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send deprecation reminders for API versions approaching sunset';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting API version deprecation reminder process...');
        
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual notifications will be sent');
        }
        
        try {
            $deprecatedVersions = $this->getDeprecatedVersions();
            
            if ($deprecatedVersions->isEmpty()) {
                $this->info('No deprecated versions found. All versions are current.');
                return 0;
            }
            
            $this->info("Found {$deprecatedVersions->count()} deprecated version(s)");
            
            $notificationService = new ApiVersionNotificationService();
            $totalNotifications = 0;
            
            foreach ($deprecatedVersions as $version) {
                $notificationsSent = $this->processVersion($version, $notificationService, $dryRun);
                $totalNotifications += $notificationsSent;
                
                $this->info("Version {$version->version}: {$notificationsSent} notifications processed");
            }
            
            $this->info("Total notifications processed: {$totalNotifications}");
            
            if (!$dryRun) {
                $this->info('Deprecation reminders sent successfully!');
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error processing deprecation reminders: {$e->getMessage()}");
            Log::error('Failed to send deprecation reminders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Get versions that need deprecation reminders
     */
    private function getDeprecatedVersions()
    {
        return ApiVersion::where('status', ApiVersion::STATUS_DEPRECATED)
            ->where('sunset_date', '>', now())
            ->orderBy('sunset_date')
            ->get();
    }
    
    /**
     * Process a single version for deprecation reminders
     */
    private function processVersion(ApiVersion $version, ApiVersionNotificationService $service, bool $dryRun): int
    {
        $daysUntilSunset = $version->getDaysUntilSunset();
        
        if ($daysUntilSunset === null) {
            $this->warn("Version {$version->version} has no sunset date set");
            return 0;
        }
        
        $this->info("Version {$version->version}: {$daysUntilSunset} days until sunset");
        
        // Determine if we should send a reminder based on timeline
        $shouldSendReminder = $this->shouldSendReminder($daysUntilSunset);
        
        if (!$shouldSendReminder) {
            $this->info("Version {$version->version}: No reminder needed at this time");
            return 0;
        }
        
        $urgency = $this->getNotificationUrgency($daysUntilSunset);
        $this->info("Version {$version->version}: Sending {$urgency} urgency reminder");
        
        if ($dryRun) {
            $this->line("DRY RUN: Would send {$urgency} reminder for version {$version->version}");
            return 1;
        }
        
        try {
            $service->sendDeprecationNotifications($version);
            return 1;
        } catch (\Exception $e) {
            $this->error("Failed to send reminder for version {$version->version}: {$e->getMessage()}");
            Log::error('Failed to send deprecation reminder', [
                'version' => $version->version,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Determine if a reminder should be sent based on days until sunset
     */
    private function shouldSendReminder(?int $daysUntilSunset): bool
    {
        if ($daysUntilSunset === null) {
            return false;
        }
        
        // Send reminders at key intervals
        $reminderDays = [1, 7, 30, 60, 90];
        
        return in_array($daysUntilSunset, $reminderDays);
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
}
