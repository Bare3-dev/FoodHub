<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

/**
 * Custom Queue Monitoring Service
 * 
 * Provides Horizon-like functionality for Windows environments
 * where the pcntl extension is not available.
 */
class QueueMonitoringService
{
    /**
     * Get comprehensive queue statistics
     */
    public function getQueueStatistics(): array
    {
        try {
            $stats = [
                'overview' => $this->getOverviewStats(),
                'queues' => $this->getQueueStats(),
                'jobs' => $this->getJobStats(),
                'failed_jobs' => $this->getFailedJobStats(),
                'performance' => $this->getPerformanceStats(),
                'last_updated' => now()->toISOString()
            ];

            // Cache the statistics for 30 seconds to avoid excessive database queries
            Cache::put('queue.monitoring.stats', $stats, 30);

            return $stats;

        } catch (\Exception $e) {
            Log::error('Failed to get queue statistics', ['error' => $e->getMessage()]);
            
            // Return cached stats if available
            return Cache::get('queue.monitoring.stats', [
                'error' => 'Failed to retrieve queue statistics',
                'last_updated' => now()->toISOString()
            ]);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(): array
    {
        $totalJobs = DB::table('jobs')->count();
        $pendingJobs = DB::table('jobs')->where('reserved_at', null)->count();
        $reservedJobs = DB::table('jobs')->where('reserved_at', '>', 0)->count();
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            'total_jobs' => $totalJobs,
            'pending_jobs' => $pendingJobs,
            'reserved_jobs' => $reservedJobs,
            'failed_jobs' => $failedJobs,
            'active_workers' => $this->getActiveWorkerCount(),
            'queue_health' => $this->getQueueHealth($totalJobs, $failedJobs)
        ];
    }

    /**
     * Get statistics for each queue
     */
    private function getQueueStats(): array
    {
        $queues = ['high', 'default', 'low'];
        $stats = [];

        foreach ($queues as $queue) {
            $queueStats = DB::table('jobs')
                ->where('queue', $queue)
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(CASE WHEN reserved_at IS NULL THEN 1 END) as pending,
                    COUNT(CASE WHEN reserved_at > 0 THEN 1 END) as reserved,
                    AVG(CASE WHEN reserved_at > 0 THEN (UNIX_TIMESTAMP() - reserved_at) END) as avg_wait_time
                ')
                ->first();

            $stats[$queue] = [
                'total' => $queueStats->total ?? 0,
                'pending' => $queueStats->pending ?? 0,
                'reserved' => $queueStats->reserved ?? 0,
                'avg_wait_time' => round($queueStats->avg_wait_time ?? 0, 2),
                'health' => $this->getQueueHealth($queueStats->total ?? 0, 0)
            ];
        }

        return $stats;
    }

    /**
     * Get job statistics
     */
    private function getJobStats(): array
    {
        $recentJobs = DB::table('jobs')
            ->select('queue', 'attempts', 'created_at', 'reserved_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $jobTypes = $recentJobs->groupBy('queue')->map(function ($jobs) {
            return [
                'count' => $jobs->count(),
                'avg_attempts' => round($jobs->avg('attempts'), 2),
                'oldest_job' => $jobs->min('created_at'),
                'newest_job' => $jobs->max('created_at')
            ];
        });

        return [
            'recent_jobs' => $recentJobs->count(),
            'by_queue' => $jobTypes,
            'avg_processing_time' => $this->calculateAverageProcessingTime()
        ];
    }

    /**
     * Get failed job statistics
     */
    private function getFailedJobStats(): array
    {
        $failedJobs = DB::table('failed_jobs')
            ->select('queue', 'failed_at', 'exception')
            ->orderBy('failed_at', 'desc')
            ->limit(50)
            ->get();

        $failureReasons = $failedJobs->groupBy('queue')->map(function ($jobs) {
            return [
                'count' => $jobs->count(),
                'recent_failures' => $jobs->take(5)->map(function ($job) {
                    return [
                        'failed_at' => $job->failed_at,
                        'exception_summary' => $this->summarizeException($job->exception)
                    ];
                })
            ];
        });

        return [
            'total_failed' => $failedJobs->count(),
            'by_queue' => $failureReasons,
            'failure_rate' => $this->calculateFailureRate()
        ];
    }

    /**
     * Get performance statistics
     */
    private function getPerformanceStats(): array
    {
        $last24Hours = now()->subDay();
        
        $jobsProcessed = DB::table('jobs')
            ->where('created_at', '>=', $last24Hours)
            ->count();

        $jobsFailed = DB::table('failed_jobs')
            ->where('failed_at', '>=', $last24Hours)
            ->count();

        $avgProcessingTime = $this->calculateAverageProcessingTime();

        return [
            'jobs_per_hour' => round($jobsProcessed / 24, 2),
            'success_rate' => $jobsProcessed > 0 ? round((($jobsProcessed - $jobsFailed) / $jobsProcessed) * 100, 2) : 100,
            'avg_processing_time' => $avgProcessingTime,
            'throughput' => [
                'high_priority' => $this->getQueueThroughput('high'),
                'default' => $this->getQueueThroughput('default'),
                'low_priority' => $this->getQueueThroughput('low')
            ]
        ];
    }

    /**
     * Get active worker count
     */
    private function getActiveWorkerCount(): int
    {
        // Check Redis for active workers
        try {
            $workers = Redis::keys('horizon:workers:*');
            return count($workers);
        } catch (\Exception $e) {
            // Fallback: estimate based on reserved jobs
            $recentlyReserved = DB::table('jobs')
                ->where('reserved_at', '>=', now()->subMinutes(5))
                ->count();
            
            return $recentlyReserved > 0 ? 1 : 0;
        }
    }

    /**
     * Get queue health status
     */
    private function getQueueHealth(int $totalJobs, int $failedJobs): string
    {
        if ($totalJobs === 0) {
            return 'idle';
        }

        $failureRate = ($failedJobs / $totalJobs) * 100;

        if ($failureRate > 20) {
            return 'critical';
        } elseif ($failureRate > 10) {
            return 'warning';
        } elseif ($failureRate > 5) {
            return 'degraded';
        } else {
            return 'healthy';
        }
    }

    /**
     * Calculate average processing time
     */
    private function calculateAverageProcessingTime(): float
    {
        $processedJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '>', 0)
            ->selectRaw('AVG(UNIX_TIMESTAMP() - created_at) as avg_time')
            ->first();

        return round($processedJobs->avg_time ?? 0, 2);
    }

    /**
     * Calculate failure rate
     */
    private function calculateFailureRate(): float
    {
        $totalJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        if ($totalJobs === 0) {
            return 0;
        }

        return round(($failedJobs / $totalJobs) * 100, 2);
    }

    /**
     * Get queue throughput
     */
    private function getQueueThroughput(string $queue): array
    {
        $lastHour = now()->subHour();
        
        $processed = DB::table('jobs')
            ->where('queue', $queue)
            ->where('created_at', '>=', $lastHour)
            ->whereNotNull('reserved_at')
            ->count();

        $failed = DB::table('failed_jobs')
            ->where('queue', $queue)
            ->where('failed_at', '>=', $lastHour)
            ->count();

        return [
            'processed_per_hour' => $processed,
            'failed_per_hour' => $failed,
            'success_rate' => $processed > 0 ? round((($processed - $failed) / $processed) * 100, 2) : 100
        ];
    }

    /**
     * Summarize exception for display
     */
    private function summarizeException(string $exception): string
    {
        $lines = explode("\n", $exception);
        $firstLine = trim($lines[0] ?? '');
        
        // Extract class name from first line
        if (preg_match('/^([a-zA-Z0-9\\_]+):/', $firstLine, $matches)) {
            return $matches[1];
        }

        return substr($firstLine, 0, 50) . (strlen($firstLine) > 50 ? '...' : '');
    }

    /**
     * Retry a failed job
     */
    public function retryFailedJob(string $jobId): bool
    {
        try {
            $failedJob = DB::table('failed_jobs')->where('id', $jobId)->first();
            
            if (!$failedJob) {
                return false;
            }

            // Move job back to jobs table
            DB::table('jobs')->insert([
                'queue' => $failedJob->queue,
                'payload' => $failedJob->payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp
            ]);

            // Remove from failed jobs
            DB::table('failed_jobs')->where('id', $jobId)->delete();

            Log::info('Failed job retried', ['job_id' => $jobId, 'queue' => $failedJob->queue]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to retry job', ['job_id' => $jobId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear failed jobs
     */
    public function clearFailedJobs(): int
    {
        try {
            $count = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->truncate();
            
            Log::info('Failed jobs cleared', ['count' => $count]);
            
            return $count;

        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get real-time queue monitoring data
     */
    public function getRealTimeData(): array
    {
        return [
            'current_jobs' => $this->getCurrentJobs(),
            'queue_sizes' => $this->getQueueSizes(),
            'worker_status' => $this->getWorkerStatus(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get current jobs being processed
     */
    private function getCurrentJobs(): array
    {
        $reservedJobs = DB::table('jobs')
            ->where('reserved_at', '>', 0)
            ->select('queue', 'attempts', 'reserved_at')
            ->get();

        return [
            'total_processing' => $reservedJobs->count(),
            'by_queue' => $reservedJobs->groupBy('queue')->map->count(),
            'avg_attempts' => round($reservedJobs->avg('attempts'), 2)
        ];
    }

    /**
     * Get current queue sizes
     */
    private function getQueueSizes(): array
    {
        $queues = ['high', 'default', 'low'];
        $sizes = [];

        foreach ($queues as $queue) {
            $sizes[$queue] = [
                'pending' => DB::table('jobs')->where('queue', $queue)->where('reserved_at', null)->count(),
                'processing' => DB::table('jobs')->where('queue', $queue)->where('reserved_at', '>', 0)->count()
            ];
        }

        return $sizes;
    }

    /**
     * Get worker status
     */
    private function getWorkerStatus(): array
    {
        return [
            'active_workers' => $this->getActiveWorkerCount(),
            'last_heartbeat' => Cache::get('queue.workers.last_heartbeat', 'unknown'),
            'worker_health' => $this->getWorkerHealth()
        ];
    }

    /**
     * Get worker health status
     */
    private function getWorkerHealth(): string
    {
        $lastHeartbeat = Cache::get('queue.workers.last_heartbeat');
        
        if (!$lastHeartbeat) {
            return 'unknown';
        }

        $lastHeartbeatTime = Carbon::parse($lastHeartbeat);
        
        if ($lastHeartbeatTime->diffInMinutes(now()) > 5) {
            return 'stale';
        }

        return 'healthy';
    }
}
