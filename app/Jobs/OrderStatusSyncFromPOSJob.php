<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\PosOrderMapping;
use App\Services\POSIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * High Priority Job: Sync order status from POS system to FoodHub
 * 
 * This job handles critical order status updates from POS systems
 * with retry logic and exponential backoff for reliability.
 */
class OrderStatusSyncFromPOSJob implements ShouldQueue
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
        private readonly string $posOrderId,
        private readonly string $posType,
        private readonly string $newStatus,
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
            Log::info('Starting POS order status sync', [
                'pos_order_id' => $this->posOrderId,
                'pos_type' => $this->posType,
                'new_status' => $this->newStatus,
                'attempt' => $this->attempts()
            ]);

            // Check if POS connection is available
            if (!$this->isPOSConnectionAvailable()) {
                throw new \Exception("POS connection unavailable for {$this->posType}");
            }

            // Attempt to sync order status from POS
            $result = $posService->updateOrderStatus($this->posOrderId, $this->posType, $this->newStatus);

            if ($result['success']) {
                Log::info('POS order status sync successful', [
                    'pos_order_id' => $this->posOrderId,
                    'foodhub_order_id' => $result['order_id'],
                    'new_status' => $result['status'],
                    'pos_type' => $this->posType
                ]);

                // Cache successful sync to avoid repeated attempts
                $this->cacheSuccessfulSync($result['order_id']);
            } else {
                throw new \Exception($result['error'] ?? 'Unknown sync error');
            }

        } catch (\Exception $e) {
            Log::error('POS order status sync failed', [
                'pos_order_id' => $this->posOrderId,
                'pos_type' => $this->posType,
                'new_status' => $this->newStatus,
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
        Log::critical('POS order status sync job failed permanently', [
            'pos_order_id' => $this->posOrderId,
            'pos_type' => $this->posType,
            'new_status' => $this->newStatus,
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
        // Get restaurant ID from POS order mapping
        $mapping = PosOrderMapping::where('pos_order_id', $this->posOrderId)
            ->where('pos_type', $this->posType)
            ->first();

        if (!$mapping) {
            return false;
        }

        $order = Order::find($mapping->foodhub_order_id);
        if (!$order) {
            return false;
        }

        $cacheKey = "pos.connection.{$this->posType}.{$order->restaurant_id}";
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
    private function cacheSuccessfulSync(string $orderId): void
    {
        $cacheKey = "pos.status.sync.success.{$this->posType}.{$this->posOrderId}";
        Cache::put($cacheKey, [
            'synced_at' => now(),
            'pos_type' => $this->posType,
            'pos_order_id' => $this->posOrderId,
            'foodhub_order_id' => $orderId,
            'status' => $this->newStatus
        ], 3600); // Cache for 1 hour
    }

    /**
     * Mark sync as failed in cache.
     */
    private function markSyncAsFailed(string $error): void
    {
        $cacheKey = "pos.status.sync.failed.{$this->posType}.{$this->posOrderId}";
        Cache::put($cacheKey, [
            'failed_at' => now(),
            'error' => $error,
            'pos_type' => $this->posType,
            'pos_order_id' => $this->posOrderId,
            'new_status' => $this->newStatus
        ], 7200); // Cache for 2 hours
    }
}
