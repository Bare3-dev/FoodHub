<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class BranchMenuItem extends Model
{
    use HasFactory;

    protected $table = 'branch_menu_items';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_branch_id',
        'menu_item_id',
        'price',
        'is_available',
        'is_featured',
        'sort_order',
        'settings',
        'stock_quantity',
        'min_stock_threshold',
        'track_inventory',
        'last_stock_update',
        'stock_status',
        'kitchen_capacity',
        'max_daily_orders',
        'time_schedules',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'settings' => 'array',
            'stock_quantity' => 'integer',
            'min_stock_threshold' => 'integer',
            'track_inventory' => 'boolean',
            'last_stock_update' => 'datetime',
            'stock_status' => 'string',
            'kitchen_capacity' => 'integer',
            'max_daily_orders' => 'integer',
            'time_schedules' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant branch that owns the branch menu item.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get the restaurant branch that owns the branch menu item.
     * Alias for branch() for backward compatibility.
     */
    public function restaurantBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Get the menu item that owns the branch menu item.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Get the inventory stock changes for this branch menu item.
     */
    public function stockChanges(): HasMany
    {
        return $this->hasMany(InventoryStockChange::class);
    }

    /**
     * Check if the item is in stock.
     */
    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return $this->is_available;
        }
        
        return $this->stock_quantity > 0;
    }

    /**
     * Check if the item is low in stock.
     */
    public function isLowStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }
        
        return $this->stock_quantity <= $this->min_stock_threshold;
    }

    /**
     * Get the stock status.
     */
    public function getStockStatus(): string
    {
        if (!$this->track_inventory) {
            return $this->is_available ? 'available' : 'unavailable';
        }
        
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        }
        
        if ($this->isLowStock()) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Update stock status based on current quantity
     */
    public function updateStockStatus(): void
    {
        if (!$this->track_inventory) {
            return;
        }

        $newStatus = $this->getStockStatus();
        
        if ($this->stock_status !== $newStatus) {
            $this->update(['stock_status' => $newStatus]);
        }
    }

    /**
     * Check if item is available for current time schedule
     */
    public function isAvailableForTimeSchedule(): bool
    {
        if (empty($this->time_schedules)) {
            return true; // No time restrictions
        }

        $now = now();
        $currentDay = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        foreach ($this->time_schedules as $schedule) {
            if ($schedule['day'] === $currentDay) {
                $startTime = $schedule['start_time'] ?? '00:00';
                $endTime = $schedule['end_time'] ?? '23:59';
                
                return $currentTime >= $startTime && $currentTime <= $endTime;
            }
        }

        return false;
    }

    /**
     * Check kitchen capacity for the day
     */
    public function hasKitchenCapacity(): bool
    {
        if (!$this->kitchen_capacity || !$this->max_daily_orders) {
            return true; // No capacity restrictions
        }

        $todayOrders = $this->getTodayOrderCount();
        return $todayOrders < $this->max_daily_orders;
    }

    /**
     * Get today's order count for this item
     */
    private function getTodayOrderCount(): int
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.menu_item_id', $this->menu_item_id)
            ->whereDate('orders.created_at', today())
            ->sum('order_items.quantity');
    }

    /**
     * Get reorder suggestion quantity
     */
    public function getReorderSuggestion(): int
    {
        if (!$this->track_inventory) {
            return 0;
        }

        // Calculate based on average daily consumption
        $avgDailyConsumption = $this->getAverageDailyConsumption();
        $safetyStock = $this->min_stock_threshold;
        
        return (int) max(1, $avgDailyConsumption * 3 + $safetyStock); // 3 days supply + safety stock
    }

    /**
     * Calculate average daily consumption
     */
    public function getAverageDailyConsumption(): float
    {
        $consumption = DB::table('inventory_stock_changes')
            ->where('branch_menu_item_id', $this->id)
            ->where('quantity_change', '<', 0)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum(DB::raw('ABS(quantity_change)'));

        return $consumption / 30; // Average over 30 days
    }

    /**
     * Check if item was low stock at given quantity
     */
    public function wasLowStock(int $quantity): bool
    {
        return $quantity <= $this->min_stock_threshold;
    }
} 