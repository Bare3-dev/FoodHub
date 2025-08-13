<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Services\POSIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Medium Priority Job: Sync inventory updates from POS system
 * 
 * This job handles inventory synchronization from POS systems
 * with retry logic and exponential backoff for reliability.
 */
class InventoryUpdateFromPOSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [300, 900, 1800]; // 5min, 15min, 30min

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 120; // 2 minutes max

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Restaurant $restaurant,
        private readonly string $posType,
        private readonly ?string $priority = 'default'
    ) {
        $this->onQueue($priority);
    }

    /**
     * Execute the job.
     */
    public function handle(POSIntegrationService $posService): void
    {
        try {
            Log::info('Starting POS inventory sync', [
                'restaurant_id' => $this->restaurant->id,
                'pos_type' => $this->posType,
                'attempt' => $this->attempts()
            ]);

            // Check if POS connection is available
            if (!$this->isPOSConnectionAvailable()) {
                throw new \Exception("POS connection unavailable for {$this->posType}");
            }

            // Attempt to sync inventory from POS
            $result = $posService->syncInventoryLevels($this->restaurant, $this->posType);

            if ($result['success']) {
                Log::info('POS inventory sync successful', [
                    'restaurant_id' => $this->restaurant->id,
                    'pos_type' => $this->posType,
                    'updated_items' => $result['updated_items'] ?? 0,
                    'synced_items' => $result['synced_items'] ?? 0
                ]);

                // Cache successful sync to avoid repeated attempts
                $this->cacheSuccessfulSync();
            } else {
                throw new \Exception($result['error'] ?? 'Unknown sync error');
            }

        } catch (\Exception $e) {
            Log::error('POS inventory sync failed', [
                'restaurant_id' => $this->restaurant->id,
                'pos_type' => $this->posType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            // If this is the final attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->markSyncAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('POS inventory sync job failed permanently', [
            'restaurant_id' => $this->restaurant->id,
            'pos_type' => $this->posType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->markSyncAsFailed($exception->getMessage());
    }

    /**
     * Check if POS connection is available.
     */
    private function isPOSConnectionAvailable(): bool
    {
        $cacheKey = "pos.connection.{$this->posType}.{$this->restaurant->id}";
        $connectionStatus = Cache::get($cacheKey);

        // If we don't have cached status, assume it's available
        if ($connectionStatus === null) {
            return true;
        }

        // If connection failed recently, don't retry immediately
        if (isset($connectionStatus['failed_at']) && 
            now()->diffInMinutes($connectionStatus['failed_at']) < 5) {
            return false;
        }

        return $connectionStatus['status'] !== 'failed';
    }

    /**
     * Cache successful sync to avoid repeated attempts.
     */
    private function cacheSuccessfulSync(): void
    {
        $cacheKey = "pos.inventory.sync.success.{$this->posType}.{$this->restaurant->id}";
        Cache::put($cacheKey, [
            'synced_at' => now(),
            'pos_type' => $this->posType,
            'restaurant_id' => $this->restaurant->id
        ], 1800); // Cache for 30 minutes (inventory changes frequently)
    }

    /**
     * Mark sync as failed in cache.
     */
    private function markSyncAsFailed(string $error): void
    {
        $cacheKey = "pos.inventory.sync.failed.{$this->posType}.{$this->restaurant->id}";
        Cache::put($cacheKey, [
            'failed_at' => now(),
            'error' => $error,
            'pos_type' => $this->posType,
            'restaurant_id' => $this->restaurant->id
        ], 3600); // Cache for 1 hour
    }
}
