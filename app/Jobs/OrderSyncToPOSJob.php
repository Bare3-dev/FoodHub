<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\POSIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * High Priority Job: Sync FoodHub order to POS system
 * 
 * This job handles critical order synchronization to POS systems
 * with retry logic and exponential backoff for reliability.
 */
class OrderSyncToPOSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 180, 300, 600, 1200]; // 1min, 3min, 5min, 10min, 20min

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
        private readonly Order $order,
        private readonly string $posType,
        private readonly ?string $priority = 'high'
    ) {
        $this->onQueue($priority);
    }

    /**
     * Execute the job.
     */
    public function handle(POSIntegrationService $posService): void
    {
        try {
            Log::info('Starting POS order sync', [
                'order_id' => $this->order->id,
                'pos_type' => $this->posType,
                'attempt' => $this->attempts()
            ]);

            // Check if POS connection is available
            if (!$this->isPOSConnectionAvailable()) {
                throw new \Exception("POS connection unavailable for {$this->posType}");
            }

            // Attempt to sync order to POS
            $result = $posService->createPOSOrder($this->order, $this->posType);

            if ($result['success']) {
                Log::info('POS order sync successful', [
                    'order_id' => $this->order->id,
                    'pos_order_id' => $result['pos_order_id'] ?? null,
                    'pos_type' => $this->posType
                ]);

                // Cache successful sync to avoid repeated attempts
                $this->cacheSuccessfulSync();
            } else {
                throw new \Exception($result['error'] ?? 'Unknown sync error');
            }

        } catch (\Exception $e) {
            Log::error('POS order sync failed', [
                'order_id' => $this->order->id,
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
        Log::critical('POS order sync job failed permanently', [
            'order_id' => $this->order->id,
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
        $cacheKey = "pos.connection.{$this->posType}.{$this->order->restaurant_id}";
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
        $cacheKey = "pos.sync.success.{$this->posType}.{$this->order->id}";
        Cache::put($cacheKey, [
            'synced_at' => now(),
            'pos_type' => $this->posType,
            'order_id' => $this->order->id
        ], 3600); // Cache for 1 hour
    }

    /**
     * Mark sync as failed in cache.
     */
    private function markSyncAsFailed(string $error): void
    {
        $cacheKey = "pos.sync.failed.{$this->posType}.{$this->order->id}";
        Cache::put($cacheKey, [
            'failed_at' => now(),
            'error' => $error,
            'pos_type' => $this->posType,
            'order_id' => $this->order->id
        ], 7200); // Cache for 2 hours

        // Also update POS connection status
        $connectionKey = "pos.connection.{$this->posType}.{$this->order->restaurant_id}";
        Cache::put($connectionKey, [
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => $error
        ], 1800); // Cache for 30 minutes
    }
}
