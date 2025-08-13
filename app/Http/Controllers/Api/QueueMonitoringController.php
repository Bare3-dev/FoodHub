<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QueueMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Queue Monitoring Controller
 * 
 * Provides admin-only access to queue monitoring and management
 * with role-based permissions for restaurant owners and managers.
 */
class QueueMonitoringController extends Controller
{
    public function __construct(
        private readonly QueueMonitoringService $queueMonitoringService
    ) {}

    /**
     * Get comprehensive queue statistics
     */
    public function index(): JsonResponse
    {
        // Check if user has permission to view queue monitoring
        if (!Gate::allows('viewQueueMonitoring')) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => 'You do not have permission to view queue monitoring.'
            ], 403);
        }

        try {
            $stats = $this->queueMonitoringService->getQueueStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue statistics', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve queue statistics',
                'message' => 'An error occurred while fetching queue data.'
            ], 500);
        }
    }

    /**
     * Get real-time queue monitoring data
     */
    public function realTime(): JsonResponse
    {
        if (!Gate::allows('viewQueueMonitoring')) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => 'You do not have permission to view queue monitoring.'
            ], 403);
        }

        try {
            $realTimeData = $this->queueMonitoringService->getRealTimeData();
            
            return response()->json([
                'success' => true,
                'data' => $realTimeData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get real-time queue data', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve real-time data',
                'message' => 'An error occurred while fetching real-time queue data.'
            ], 500);
        }
    }

    /**
     * Retry a failed job
     */
    public function retryJob(Request $request): JsonResponse
    {
        if (!Gate::allows('manageQueueJobs')) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => 'You do not have permission to manage queue jobs.'
            ], 403);
        }

        $request->validate([
            'job_id' => 'required|string'
        ]);

        try {
            $success = $this->queueMonitoringService->retryFailedJob($request->job_id);
            
            if ($success) {
                Log::info('Failed job retried by admin', [
                    'user_id' => auth()->id(),
                    'job_id' => $request->job_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Job successfully retried.'
                ]);
            } else {
                return response()->json([
                    'error' => 'Job Retry Failed',
                    'message' => 'Failed to retry the specified job.'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to retry job', [
                'user_id' => auth()->id(),
                'job_id' => $request->job_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Job Retry Failed',
                'message' => 'An error occurred while retrying the job.'
            ], 500);
        }
    }

    /**
     * Clear all failed jobs
     */
    public function clearFailedJobs(): JsonResponse
    {
        if (!Gate::allows('manageQueueJobs')) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => 'You do not have permission to manage queue jobs.'
            ], 403);
        }

        try {
            $count = $this->queueMonitoringService->clearFailedJobs();
            
            Log::info('Failed jobs cleared by admin', [
                'user_id' => auth()->id(),
                'count' => $count
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully cleared {$count} failed jobs.",
                'data' => ['cleared_count' => $count]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to Clear Jobs',
                'message' => 'An error occurred while clearing failed jobs.'
            ], 500);
        }
    }

    /**
     * Get POS sync job status
     */
    public function getPOSSyncStatus(): JsonResponse
    {
        if (!Gate::allows('viewQueueMonitoring')) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => 'You do not have permission to view queue monitoring.'
            ], 403);
        }

        try {
            $posSyncStats = $this->getPOSSyncStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $posSyncStats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get POS sync status', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve POS sync status',
                'message' => 'An error occurred while fetching POS sync data.'
            ], 500);
        }
    }

    /**
     * Get POS sync statistics
     */
    private function getPOSSyncStatistics(): array
    {
        $posTypes = ['square', 'toast', 'local'];
        $stats = [];

        foreach ($posTypes as $posType) {
            $stats[$posType] = [
                'recent_syncs' => $this->getRecentPOSSyncs($posType),
                'sync_success_rate' => $this->getPOSSyncSuccessRate($posType),
                'last_sync' => $this->getLastPOSSync($posType),
                'connection_status' => $this->getPOSConnectionStatus($posType)
            ];
        }

        return [
            'pos_sync_overview' => $stats,
            'total_sync_jobs' => $this->getTotalPOSSyncJobs(),
            'failed_sync_jobs' => $this->getFailedPOSSyncJobs(),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Get recent POS syncs for a specific type
     */
    private function getRecentPOSSyncs(string $posType): array
    {
        // This would query the actual POS sync logs
        // For now, return mock data
        return [
            'successful' => rand(10, 50),
            'failed' => rand(0, 5),
            'pending' => rand(0, 3)
        ];
    }

    /**
     * Get POS sync success rate
     */
    private function getPOSSyncSuccessRate(string $posType): float
    {
        // Mock calculation
        $successful = rand(80, 95);
        $failed = rand(5, 20);
        
        return round(($successful / ($successful + $failed)) * 100, 2);
    }

    /**
     * Get last POS sync timestamp
     */
    private function getLastPOSSync(string $posType): ?string
    {
        // Mock data - in production this would query actual sync logs
        $lastSync = now()->subMinutes(rand(5, 60));
        return $lastSync->toISOString();
    }

    /**
     * Get POS connection status
     */
    private function getPOSConnectionStatus(string $posType): string
    {
        // Mock connection status
        $statuses = ['connected', 'disconnected', 'error'];
        return $statuses[array_rand($statuses)];
    }

    /**
     * Get total POS sync jobs
     */
    private function getTotalPOSSyncJobs(): int
    {
        // Mock total count
        return rand(100, 500);
    }

    /**
     * Get failed POS sync jobs
     */
    private function getFailedPOSSyncJobs(): int
    {
        // Mock failed count
        return rand(5, 25);
    }
}
