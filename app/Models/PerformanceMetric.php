<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class PerformanceMetric extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'restaurant_branch_id',
        'restaurant_id',
        'metric_type',
        'metric_value',
        'metric_unit',
        'metric_date',
        'metric_details',
        'period_type',
        'is_automated',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metric_value' => 'decimal:4',
            'metric_details' => 'array',
            'metric_date' => 'date',
            'is_automated' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user (staff member) for this metric.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the restaurant branch for this metric.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get the restaurant for this metric.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get metric description.
     */
    public function getMetricDescription(): string
    {
        $descriptions = [
            'order_processing_speed' => 'Average time to process orders',
            'customer_satisfaction' => 'Customer satisfaction scores',
            'productivity' => 'Orders per hour, revenue per hour',
            'attendance_rate' => 'Attendance percentage',
            'error_rate' => 'Mistakes per order',
            'teamwork_score' => 'Collaboration metrics',
            'upsell_rate' => 'Additional sales percentage',
            'customer_retention' => 'Repeat customer rate',
            'delivery_efficiency' => 'Delivery time vs estimated',
            'kitchen_efficiency' => 'Preparation time efficiency',
        ];

        return $descriptions[$this->metric_type] ?? 'Unknown metric type';
    }

    /**
     * Check if metric is good performance (above threshold).
     */
    public function isGoodPerformance(): bool
    {
        $thresholds = [
            'order_processing_speed' => 15, // minutes
            'customer_satisfaction' => 4.0, // rating
            'productivity' => 10, // orders per hour
            'attendance_rate' => 95, // percentage
            'error_rate' => 2, // percentage
            'teamwork_score' => 4.0, // rating
            'upsell_rate' => 15, // percentage
            'customer_retention' => 70, // percentage
            'delivery_efficiency' => 90, // percentage
            'kitchen_efficiency' => 85, // percentage
        ];

        $threshold = $thresholds[$this->metric_type] ?? 0;
        
        // For metrics where lower is better (like processing speed, error rate)
        $lowerIsBetter = in_array($this->metric_type, ['order_processing_speed', 'error_rate']);
        
        return $lowerIsBetter ? $this->metric_value <= $threshold : $this->metric_value >= $threshold;
    }

    /**
     * Get performance level (excellent, good, average, poor).
     */
    public function getPerformanceLevel(): string
    {
        $levels = [
            'order_processing_speed' => [
                'excellent' => 10,
                'good' => 15,
                'average' => 20,
            ],
            'customer_satisfaction' => [
                'excellent' => 4.5,
                'good' => 4.0,
                'average' => 3.5,
            ],
            'productivity' => [
                'excellent' => 15,
                'good' => 10,
                'average' => 7,
            ],
            'attendance_rate' => [
                'excellent' => 98,
                'good' => 95,
                'average' => 90,
            ],
            'error_rate' => [
                'excellent' => 1,
                'good' => 2,
                'average' => 5,
            ],
            'teamwork_score' => [
                'excellent' => 4.5,
                'good' => 4.0,
                'average' => 3.5,
            ],
            'upsell_rate' => [
                'excellent' => 25,
                'good' => 15,
                'average' => 10,
            ],
            'customer_retention' => [
                'excellent' => 85,
                'good' => 70,
                'average' => 60,
            ],
            'delivery_efficiency' => [
                'excellent' => 95,
                'good' => 90,
                'average' => 85,
            ],
            'kitchen_efficiency' => [
                'excellent' => 95,
                'good' => 85,
                'average' => 75,
            ],
        ];

        $metricLevels = $levels[$this->metric_type] ?? [];
        
        foreach (['excellent', 'good', 'average'] as $level) {
            if (isset($metricLevels[$level])) {
                $lowerIsBetter = in_array($this->metric_type, ['order_processing_speed', 'error_rate']);
                $isBetter = $lowerIsBetter ? $this->metric_value <= $metricLevels[$level] : $this->metric_value >= $metricLevels[$level];
                
                if ($isBetter) {
                    return $level;
                }
            }
        }

        return 'poor';
    }

    /**
     * Scope to get metrics for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get metrics for a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('restaurant_branch_id', $branchId);
    }

    /**
     * Scope to get metrics for a specific restaurant.
     */
    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope to get metrics by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope to get metrics for a date range.
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get metrics by period type.
     */
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period_type', $period);
    }

    /**
     * Scope to get automated metrics.
     */
    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    /**
     * Scope to get manual metrics.
     */
    public function scopeManual($query)
    {
        return $query->where('is_automated', false);
    }
} 