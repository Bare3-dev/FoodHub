<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class AnalyticsDashboard extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'analytics_dashboard';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'restaurant_branch_id',
        'metric_name',
        'metric_value',
        'date_range',
        'start_date',
        'end_date',
        'data_type',
        'is_automated',
        'last_calculated_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metric_value' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_automated' => 'boolean',
            'last_calculated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant for this analytics entry.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the restaurant branch for this analytics entry.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get metric description.
     */
    public function getMetricDescription(): string
    {
        $descriptions = [
            'daily_revenue' => 'Daily Revenue',
            'weekly_revenue' => 'Weekly Revenue',
            'monthly_revenue' => 'Monthly Revenue',
            'customer_satisfaction' => 'Customer Satisfaction',
            'staff_performance' => 'Staff Performance',
            'order_volume' => 'Order Volume',
            'average_order_value' => 'Average Order Value',
            'customer_retention' => 'Customer Retention',
            'delivery_efficiency' => 'Delivery Efficiency',
            'kitchen_efficiency' => 'Kitchen Efficiency',
            'peak_hours' => 'Peak Hours Analysis',
            'popular_items' => 'Popular Menu Items',
            'customer_demographics' => 'Customer Demographics',
            'loyalty_program_usage' => 'Loyalty Program Usage',
        ];

        return $descriptions[$this->metric_name] ?? 'Unknown Metric';
    }

    /**
     * Get data type description.
     */
    public function getDataTypeDescription(): string
    {
        $descriptions = [
            'revenue' => 'Revenue Data',
            'orders' => 'Order Data',
            'customers' => 'Customer Data',
            'staff' => 'Staff Data',
            'delivery' => 'Delivery Data',
            'kitchen' => 'Kitchen Data',
        ];

        return $descriptions[$this->data_type] ?? 'Unknown Data Type';
    }

    /**
     * Check if this analytics entry is up to date.
     */
    public function isUpToDate(): bool
    {
        if (!$this->last_calculated_at) {
            return false;
        }

        $hoursSinceLastUpdate = $this->last_calculated_at->diffInHours(now());
        
        // Consider stale if not updated in the last 24 hours
        return $hoursSinceLastUpdate <= 24;
    }

    /**
     * Get the primary metric value.
     */
    public function getPrimaryValue()
    {
        if (is_array($this->metric_value)) {
            return $this->metric_value['primary_value'] ?? null;
        }
        return $this->metric_value;
    }

    /**
     * Get the secondary metric value.
     */
    public function getSecondaryValue()
    {
        if (is_array($this->metric_value)) {
            return $this->metric_value['secondary_value'] ?? null;
        }
        return null;
    }

    /**
     * Get the trend value (positive/negative percentage).
     */
    public function getTrendValue()
    {
        if (is_array($this->metric_value)) {
            return $this->metric_value['trend'] ?? null;
        }
        return null;
    }

    /**
     * Check if trend is positive.
     */
    public function isTrendPositive(): bool
    {
        $trend = $this->getTrendValue();
        return $trend && $trend > 0;
    }

    /**
     * Check if trend is negative.
     */
    public function isTrendNegative(): bool
    {
        $trend = $this->getTrendValue();
        return $trend && $trend < 0;
    }

    /**
     * Get trend description.
     */
    public function getTrendDescription(): string
    {
        $trend = $this->getTrendValue();
        
        if (!$trend) {
            return 'No change';
        }
        
        if ($trend > 0) {
            return "+{$trend}% increase";
        }
        
        return "{$trend}% decrease";
    }

    /**
     * Get formatted metric value for display.
     */
    public function getFormattedValue(): string
    {
        $value = $this->getPrimaryValue();
        
        if (is_numeric($value)) {
            // Format based on metric type
            switch ($this->metric_name) {
                case 'daily_revenue':
                case 'weekly_revenue':
                case 'monthly_revenue':
                case 'average_order_value':
                    return 'SAR ' . number_format($value, 2);
                
                case 'customer_satisfaction':
                    return number_format($value, 1) . '/5';
                
                case 'order_volume':
                    return number_format($value) . ' orders';
                
                case 'customer_retention':
                case 'delivery_efficiency':
                case 'kitchen_efficiency':
                    return number_format($value, 1) . '%';
                
                default:
                    return number_format($value);
            }
        }
        
        return (string) $value;
    }

    /**
     * Scope to get analytics for a specific restaurant.
     */
    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope to get analytics for a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('restaurant_branch_id', $branchId);
    }

    /**
     * Scope to get analytics by metric name.
     */
    public function scopeByMetric($query, string $metricName)
    {
        return $query->where('metric_name', $metricName);
    }

    /**
     * Scope to get analytics by date range.
     */
    public function scopeByDateRange($query, string $dateRange)
    {
        return $query->where('date_range', $dateRange);
    }

    /**
     * Scope to get analytics by data type.
     */
    public function scopeByDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    /**
     * Scope to get automated analytics.
     */
    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    /**
     * Scope to get up-to-date analytics.
     */
    public function scopeUpToDate($query)
    {
        return $query->where('last_calculated_at', '>=', now()->subHours(24));
    }

    /**
     * Scope to get stale analytics (needs recalculation).
     */
    public function scopeStale($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_calculated_at')
              ->orWhere('last_calculated_at', '<', now()->subHours(24));
        });
    }
} 