<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PerformanceMetric;
use App\Models\CustomerFeedback;
use App\Models\AnalyticsDashboard;
use App\Models\Order;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AnalyticsService
{
    /**
     * Calculate and store performance metrics for a user.
     */
    public function calculateUserPerformanceMetrics(User $user, Carbon $date): void
    {
        $metrics = [];

        // Order processing speed
        $processingSpeed = $this->calculateOrderProcessingSpeed($user, $date);
        if ($processingSpeed !== null) {
            $metrics[] = [
                'user_id' => $user->id,
                'restaurant_branch_id' => $user->restaurant_branch_id,
                'restaurant_id' => $user->restaurant_id,
                'metric_type' => 'order_processing_speed',
                'metric_value' => $processingSpeed,
                'metric_unit' => 'minutes',
                'metric_date' => $date,
                'metric_details' => $this->getProcessingSpeedDetails($user, $date),
                'period_type' => 'daily',
                'is_automated' => true,
            ];
        }

        // Customer satisfaction
        $satisfaction = $this->calculateCustomerSatisfaction($user, $date);
        if ($satisfaction !== null) {
            $metrics[] = [
                'user_id' => $user->id,
                'restaurant_branch_id' => $user->restaurant_branch_id,
                'restaurant_id' => $user->restaurant_id,
                'metric_type' => 'customer_satisfaction',
                'metric_value' => $satisfaction,
                'metric_unit' => 'rating',
                'metric_date' => $date,
                'metric_details' => $this->getSatisfactionDetails($user, $date),
                'period_type' => 'daily',
                'is_automated' => true,
            ];
        }

        // Productivity (orders per hour)
        $productivity = $this->calculateProductivity($user, $date);
        if ($productivity !== null) {
            $metrics[] = [
                'user_id' => $user->id,
                'restaurant_branch_id' => $user->restaurant_branch_id,
                'restaurant_id' => $user->restaurant_id,
                'metric_type' => 'productivity',
                'metric_value' => $productivity,
                'metric_unit' => 'orders/hour',
                'metric_date' => $date,
                'metric_details' => $this->getProductivityDetails($user, $date),
                'period_type' => 'daily',
                'is_automated' => true,
            ];
        }

        // Store all metrics
        foreach ($metrics as $metric) {
            PerformanceMetric::updateOrCreate(
                [
                    'user_id' => $metric['user_id'],
                    'metric_type' => $metric['metric_type'],
                    'metric_date' => $metric['metric_date'],
                ],
                $metric
            );
        }
    }

    /**
     * Calculate order processing speed for a user.
     */
    private function calculateOrderProcessingSpeed(User $user, Carbon $date): ?float
    {
        $orders = Order::where('restaurant_branch_id', $user->restaurant_branch_id)
            ->whereDate('created_at', $date)
            ->whereNotNull('confirmed_at')
            ->whereNotNull('prepared_at')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $totalProcessingTime = 0;
        $processedOrders = 0;

        foreach ($orders as $order) {
            if ($order->confirmed_at && $order->prepared_at) {
                $processingTime = $order->confirmed_at->diffInMinutes($order->prepared_at);
                $totalProcessingTime += $processingTime;
                $processedOrders++;
            }
        }

        return $processedOrders > 0 ? round($totalProcessingTime / $processedOrders, 2) : null;
    }

    /**
     * Calculate customer satisfaction for a user.
     */
    private function calculateCustomerSatisfaction(User $user, Carbon $date): ?float
    {
        $feedback = CustomerFeedback::where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->where('status', 'approved')
            ->get();

        if ($feedback->isEmpty()) {
            return null;
        }

        $totalRating = $feedback->sum('rating');
        $totalFeedback = $feedback->count();

        return round($totalRating / $totalFeedback, 2);
    }

    /**
     * Calculate productivity (orders per hour) for a user.
     */
    private function calculateProductivity(User $user, Carbon $date): ?float
    {
        $orders = Order::where('restaurant_branch_id', $user->restaurant_branch_id)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        // Calculate total working hours (simplified - can be enhanced with shift data)
        $workingHours = 8; // Default 8-hour shift
        
        return round($orders->count() / $workingHours, 2);
    }

    /**
     * Get processing speed details.
     */
    private function getProcessingSpeedDetails(User $user, Carbon $date): array
    {
        $orders = Order::where('restaurant_branch_id', $user->restaurant_branch_id)
            ->whereDate('created_at', $date)
            ->whereNotNull('confirmed_at')
            ->whereNotNull('prepared_at')
            ->get();

        return [
            'total_orders' => $orders->count(),
            'average_processing_time' => $this->calculateOrderProcessingSpeed($user, $date),
            'fastest_order' => $orders->min(function ($order) {
                return $order->confirmed_at->diffInMinutes($order->prepared_at);
            }),
            'slowest_order' => $orders->max(function ($order) {
                return $order->confirmed_at->diffInMinutes($order->prepared_at);
            }),
        ];
    }

    /**
     * Get satisfaction details.
     */
    private function getSatisfactionDetails(User $user, Carbon $date): array
    {
        $feedback = CustomerFeedback::where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->where('status', 'approved')
            ->get();

        return [
            'total_feedback' => $feedback->count(),
            'positive_feedback' => $feedback->where('rating', '>=', 4)->count(),
            'negative_feedback' => $feedback->where('rating', '<=', 2)->count(),
            'average_rating' => $feedback->avg('rating'),
            'feedback_types' => $feedback->groupBy('feedback_type')->map->count(),
        ];
    }

    /**
     * Get productivity details.
     */
    private function getProductivityDetails(User $user, Carbon $date): array
    {
        $orders = Order::where('restaurant_branch_id', $user->restaurant_branch_id)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'average_order_value' => $orders->avg('total_amount'),
            'orders_by_status' => $orders->groupBy('status')->map->count(),
        ];
    }

    /**
     * Calculate and store restaurant-level analytics.
     */
    public function calculateRestaurantAnalytics(Restaurant $restaurant, Carbon $date): void
    {
        $analytics = [];

        // Daily revenue
        $dailyRevenue = $this->calculateDailyRevenue($restaurant, $date);
        if ($dailyRevenue !== null) {
            $analytics[] = [
                'restaurant_id' => $restaurant->id,
                'metric_name' => 'daily_revenue',
                'metric_value' => [
                    'primary_value' => $dailyRevenue,
                    'trend' => $this->calculateRevenueTrend($restaurant, $date),
                ],
                'date_range' => 'daily',
                'start_date' => $date,
                'end_date' => $date,
                'data_type' => 'revenue',
                'is_automated' => true,
                'last_calculated_at' => now(),
            ];
        }

        // Customer satisfaction
        $satisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date);
        if ($satisfaction !== null) {
            $analytics[] = [
                'restaurant_id' => $restaurant->id,
                'metric_name' => 'customer_satisfaction',
                'metric_value' => [
                    'primary_value' => $satisfaction,
                    'trend' => $this->calculateSatisfactionTrend($restaurant, $date),
                ],
                'date_range' => 'daily',
                'start_date' => $date,
                'end_date' => $date,
                'data_type' => 'customers',
                'is_automated' => true,
                'last_calculated_at' => now(),
            ];
        }

        // Order volume
        $orderVolume = $this->calculateOrderVolume($restaurant, $date);
        if ($orderVolume !== null) {
            $analytics[] = [
                'restaurant_id' => $restaurant->id,
                'metric_name' => 'order_volume',
                'metric_value' => [
                    'primary_value' => $orderVolume,
                    'trend' => $this->calculateOrderVolumeTrend($restaurant, $date),
                ],
                'date_range' => 'daily',
                'start_date' => $date,
                'end_date' => $date,
                'data_type' => 'orders',
                'is_automated' => true,
                'last_calculated_at' => now(),
            ];
        }

        // Store all analytics
        foreach ($analytics as $analytic) {
            AnalyticsDashboard::updateOrCreate(
                [
                    'restaurant_id' => $analytic['restaurant_id'],
                    'metric_name' => $analytic['metric_name'],
                    'date_range' => $analytic['date_range'],
                    'start_date' => $analytic['start_date'],
                ],
                $analytic
            );
        }
    }

    /**
     * Calculate daily revenue for a restaurant.
     */
    private function calculateDailyRevenue(Restaurant $restaurant, Carbon $date): ?float
    {
        $revenue = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('payment_status', 'paid')
            ->sum('total_amount');
            
        return $revenue ? (float) $revenue : null;
    }

    /**
     * Calculate customer satisfaction for a restaurant.
     */
    private function calculateRestaurantSatisfaction(Restaurant $restaurant, Carbon $date): ?float
    {
        $feedback = CustomerFeedback::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'approved')
            ->get();

        if ($feedback->isEmpty()) {
            return null;
        }

        return round($feedback->avg('rating'), 2);
    }

    /**
     * Calculate order volume for a restaurant.
     */
    private function calculateOrderVolume(Restaurant $restaurant, Carbon $date): ?int
    {
        return Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->count();
    }

    /**
     * Calculate revenue trend (percentage change from previous day).
     */
    private function calculateRevenueTrend(Restaurant $restaurant, Carbon $date): ?float
    {
        $currentRevenue = $this->calculateDailyRevenue($restaurant, $date);
        $previousRevenue = $this->calculateDailyRevenue($restaurant, $date->copy()->subDay());

        if ($previousRevenue === null || $previousRevenue == 0) {
            return null;
        }

        return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
    }

    /**
     * Calculate satisfaction trend.
     */
    private function calculateSatisfactionTrend(Restaurant $restaurant, Carbon $date): ?float
    {
        $currentSatisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date);
        $previousSatisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date->copy()->subDay());

        if ($previousSatisfaction === null || $previousSatisfaction == 0) {
            return null;
        }

        return round((($currentSatisfaction - $previousSatisfaction) / $previousSatisfaction) * 100, 2);
    }

    /**
     * Calculate order volume trend.
     */
    private function calculateOrderVolumeTrend(Restaurant $restaurant, Carbon $date): ?float
    {
        $currentVolume = $this->calculateOrderVolume($restaurant, $date);
        $previousVolume = $this->calculateOrderVolume($restaurant, $date->copy()->subDay());

        if ($previousVolume === null || $previousVolume == 0) {
            return null;
        }

        return round((($currentVolume - $previousVolume) / $previousVolume) * 100, 2);
    }

    /**
     * Get comprehensive analytics dashboard data.
     */
    public function getDashboardData(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $analytics = AnalyticsDashboard::where('restaurant_id', $restaurant->id)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        $performanceMetrics = PerformanceMetric::where('restaurant_id', $restaurant->id)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->get();

        $customerFeedback = CustomerFeedback::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'approved')
            ->get();

        return [
            'analytics' => $analytics->groupBy('metric_name'),
            'performance_metrics' => $performanceMetrics->groupBy('metric_type'),
                        'customer_feedback' => [
                'total_feedback' => $customerFeedback->count(),
                'average_rating' => $customerFeedback->count() > 0 ? round($customerFeedback->avg('rating'), 2) : 0,
                'positive_feedback_percentage' => $customerFeedback->count() > 0
                    ? round(($customerFeedback->where('rating', '>=', 4)->count() / $customerFeedback->count()) * 100, 2)
                    : 0,
                'feedback_by_type' => $customerFeedback->groupBy('feedback_type')->map->count(),
            ],
            'summary' => $this->generateSummary($analytics, $performanceMetrics, $customerFeedback),
        ];
    }

    /**
     * Generate analytics summary.
     */
    private function generateSummary(Collection $analytics, Collection $performanceMetrics, Collection $customerFeedback): array
    {
        $latestAnalytics = $analytics->groupBy('metric_name')->map->last();
        
        return [
            'total_revenue' => $latestAnalytics->get('daily_revenue')?->getPrimaryValue() ?? 0,
            'total_orders' => $latestAnalytics->get('order_volume')?->getPrimaryValue() ?? 0,
            'customer_satisfaction' => $latestAnalytics->get('customer_satisfaction')?->getPrimaryValue() ?? 0,
            'average_order_value' => $latestAnalytics->get('daily_revenue')?->getPrimaryValue() && $latestAnalytics->get('order_volume')?->getPrimaryValue()
                ? round($latestAnalytics->get('daily_revenue')->getPrimaryValue() / $latestAnalytics->get('order_volume')->getPrimaryValue(), 2)
                : 0,
            'trends' => [
                'revenue_trend' => $latestAnalytics->get('daily_revenue')?->getTrendValue(),
                'order_trend' => $latestAnalytics->get('order_volume')?->getTrendValue(),
                'satisfaction_trend' => $latestAnalytics->get('customer_satisfaction')?->getTrendValue(),
            ],
        ];
    }

    /**
     * Calculate trends correctly.
     */
    public function calculateTrendsCorrectly(Restaurant $restaurant, Carbon $date): array
    {
        $currentRevenue = $this->calculateDailyRevenue($restaurant, $date);
        $previousRevenue = $this->calculateDailyRevenue($restaurant, $date->copy()->subDay());
        
        $currentSatisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date);
        $previousSatisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date->copy()->subDay());
        
        $currentVolume = $this->calculateOrderVolume($restaurant, $date);
        $previousVolume = $this->calculateOrderVolume($restaurant, $date->copy()->subDay());
        
        return [
            'revenue_trend' => $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0,
            'satisfaction_trend' => $previousSatisfaction > 0 ? (($currentSatisfaction - $previousSatisfaction) / $previousSatisfaction) * 100 : 0,
            'volume_trend' => $previousVolume > 0 ? (($currentVolume - $previousVolume) / $previousVolume) * 100 : 0,
        ];
    }

    /**
     * Handle zero previous values for trends.
     */
    public function handleZeroPreviousValuesForTrends(Restaurant $restaurant, Carbon $date): array
    {
        $currentRevenue = $this->calculateDailyRevenue($restaurant, $date);
        $previousRevenue = $this->calculateDailyRevenue($restaurant, $date->copy()->subDay());
        
        $currentSatisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date);
        $previousSatisfaction = $this->calculateRestaurantSatisfaction($restaurant, $date->copy()->subDay());
        
        return [
            'revenue_trend' => $previousRevenue == 0 ? null : (($currentRevenue - $previousRevenue) / $previousRevenue) * 100,
            'satisfaction_trend' => $previousSatisfaction == 0 ? null : (($currentSatisfaction - $previousSatisfaction) / $previousSatisfaction) * 100,
        ];
    }

    /**
     * Handle feedback moderation status.
     */
    public function handleFeedbackModerationStatus(Restaurant $restaurant, Carbon $date): array
    {
        $pendingFeedback = CustomerFeedback::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'pending')
            ->count();
            
        $approvedFeedback = CustomerFeedback::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'approved')
            ->count();
            
        $rejectedFeedback = CustomerFeedback::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'rejected')
            ->count();
            
        return [
            'pending' => $pendingFeedback,
            'approved' => $approvedFeedback,
            'rejected' => $rejectedFeedback,
            'total' => $pendingFeedback + $approvedFeedback + $rejectedFeedback,
        ];
    }

    /**
     * Generate performance report for a user.
     */
    public function generateUserPerformanceReport(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $metrics = PerformanceMetric::where('user_id', $user->id)
            ->whereBetween('metric_date', [$startDate, $endDate])
            ->get();

        $feedback = CustomerFeedback::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'approved')
            ->get();

        return [
            'user_info' => [
                'name' => $user->name,
                'role' => $user->role,
                'branch' => $user->branch?->name,
            ],
            'metrics_summary' => [
                'average_processing_speed' => $metrics->where('metric_type', 'order_processing_speed')->avg('metric_value'),
                'average_satisfaction' => $metrics->where('metric_type', 'customer_satisfaction')->avg('metric_value'),
                'average_productivity' => $metrics->where('metric_type', 'productivity')->avg('metric_value'),
            ],
            'customer_feedback' => [
                'total_feedback' => $feedback->count(),
                'average_rating' => round($feedback->avg('rating'), 2),
                'positive_feedback_percentage' => $feedback->count() > 0 
                    ? round(($feedback->where('rating', '>=', 4)->count() / $feedback->count()) * 100, 2)
                    : 0,
            ],
            'detailed_metrics' => $metrics->groupBy('metric_type'),
            'feedback_details' => $feedback->groupBy('feedback_type'),
        ];
    }

    /**
     * Calculate processing speed with break times.
     */
    public function calculateProcessingSpeedWithBreakTimes(User $user, Carbon $date): ?float
    {
        $orders = Order::where('restaurant_branch_id', $user->restaurant_branch_id)
            ->whereDate('created_at', $date)
            ->whereNotNull('confirmed_at')
            ->whereNotNull('prepared_at')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $totalProcessingTime = 0;
        $processedOrders = 0;

        foreach ($orders as $order) {
            if ($order->confirmed_at && $order->prepared_at) {
                // Subtract break time (assuming 1 hour break)
                $processingTime = $order->confirmed_at->diffInMinutes($order->prepared_at);
                $breakTime = 60; // 1 hour break
                $actualProcessingTime = max(0, $processingTime - $breakTime);
                
                $totalProcessingTime += $actualProcessingTime;
                $processedOrders++;
            }
        }

        return $processedOrders > 0 ? round($totalProcessingTime / $processedOrders, 2) : null;
    }

    /**
     * Handle multiple feedback types.
     */
    public function handleMultipleFeedbackTypes(Restaurant $restaurant, Carbon $date): array
    {
        $feedback = CustomerFeedback::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'approved')
            ->get();

        $feedbackByType = $feedback->groupBy('feedback_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'average_rating' => round($group->avg('rating'), 2),
                'positive_percentage' => $group->count() > 0 
                    ? round(($group->where('rating', '>=', 4)->count() / $group->count()) * 100, 2)
                    : 0,
            ];
        });

        return [
            'total_feedback' => $feedback->count(),
            'feedback_by_type' => $feedbackByType,
            'overall_average' => round($feedback->avg('rating'), 2),
        ];
    }
} 