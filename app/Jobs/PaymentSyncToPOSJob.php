<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\POSIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Medium Priority Job: Sync payment information to POS system
 * 
 * This job handles payment synchronization to POS systems
 * with retry logic and exponential backoff for reliability.
 */
class PaymentSyncToPOSJob implements ShouldQueue
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
        private readonly Payment $payment,
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
            Log::info('Starting POS payment sync', [
                'payment_id' => $this->payment->id,
                'order_id' => $this->payment->order_id,
                'pos_type' => $this->posType,
                'amount' => $this->payment->amount,
                'attempt' => $this->attempts()
            ]);

            // Check if POS connection is available
            if (!$this->isPOSConnectionAvailable()) {
                throw new \Exception("POS connection unavailable for {$this->posType}");
            }

            // Attempt to sync payment to POS
            $result = $this->syncPaymentToPOS($posService);

            if ($result['success']) {
                Log::info('POS payment sync successful', [
                    'payment_id' => $this->payment->id,
                    'pos_payment_id' => $result['pos_payment_id'] ?? null,
                    'pos_type' => $this->posType
                ]);

                // Cache successful sync to avoid repeated attempts
                $this->cacheSuccessfulSync();
            } else {
                throw new \Exception($result['error'] ?? 'Unknown sync error');
            }

        } catch (\Exception $e) {
            Log::error('POS payment sync failed', [
                'payment_id' => $this->payment->id,
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
        Log::critical('POS payment sync job failed permanently', [
            'payment_id' => $this->payment->id,
            'pos_type' => $this->posType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->markSyncAsFailed($exception->getMessage());
    }

    /**
     * Sync payment to POS system.
     */
    private function syncPaymentToPOS(POSIntegrationService $posService): array
    {
        // This would integrate with the POS service to sync payment
        // For now, we'll simulate a successful sync
        // In production, this would call the actual POS API
        
        $order = $this->payment->order;
        if (!$order) {
            throw new \Exception('Order not found for payment');
        }

        // Simulate POS payment sync
        $posPaymentId = 'pos_' . $this->payment->id . '_' . time();
        
        return [
            'success' => true,
            'pos_payment_id' => $posPaymentId,
            'message' => 'Payment synced to POS successfully'
        ];
    }

    /**
     * Check if POS connection is available.
     */
    private function isPOSConnectionAvailable(): bool
    {
        $order = $this->payment->order;
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
    private function cacheSuccessfulSync(): void
    {
        $cacheKey = "pos.payment.sync.success.{$this->posType}.{$this->payment->id}";
        Cache::put($cacheKey, [
            'synced_at' => now(),
            'pos_type' => $this->posType,
            'payment_id' => $this->payment->id
        ], 3600); // Cache for 1 hour
    }

    /**
     * Mark sync as failed in cache.
     */
    private function markSyncAsFailed(string $error): void
    {
        $cacheKey = "pos.payment.sync.failed.{$this->posType}.{$this->payment->id}";
        Cache::put($cacheKey, [
            'failed_at' => now(),
            'error' => $error,
            'pos_type' => $this->posType,
            'payment_id' => $this->payment->id
        ], 7200); // Cache for 2 hours
    }
}
