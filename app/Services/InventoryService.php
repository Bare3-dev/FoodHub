<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\BranchMenuItem;
use App\Models\InventoryStockChange;
use App\Models\InventoryReport;
use App\Services\SecurityLoggingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

/**
 * Inventory Management Service
 * 
 * Centralized inventory management system for tracking stock levels,
 * availability, and synchronization with POS systems.
 * 
 * Features:
 * - Real-time stock level tracking
 * - POS system synchronization
 * - Low stock alerts
 * - Inventory movement reports
 * - Audit trail for all stock changes
 * - Time-based availability checks
 */
class InventoryService
{
    private SecurityLoggingService $securityLoggingService;
    private NotificationService $notificationService;

    public function __construct(
        SecurityLoggingService $securityLoggingService,
        NotificationService $notificationService
    ) {
        $this->securityLoggingService = $securityLoggingService;
        $this->notificationService = $notificationService;
    }

    /**
     * Update stock quantity for menu item at specific branch
     */
    public function updateItemStock(MenuItem $item, RestaurantBranch $branch, int $quantity): void
    {
        DB::transaction(function () use ($item, $branch, $quantity) {
            $branchMenuItem = BranchMenuItem::where('menu_item_id', $item->id)
                ->where('restaurant_branch_id', $branch->id)
                ->first();

            if (!$branchMenuItem) {
                throw new Exception("Branch menu item not found for item {$item->id} at branch {$branch->id}");
            }

            $previousQuantity = $branchMenuItem->stock_quantity;
            $quantityChange = $quantity - $previousQuantity;
            $previousStatus = $branchMenuItem->stock_status;

            // Update stock quantity
            $branchMenuItem->update([
                'stock_quantity' => $quantity,
                'last_stock_update' => now(),
            ]);

            // Auto-update stock status
            $branchMenuItem->updateStockStatus();

            // Invalidate cache
            $this->invalidateStockCache($branchMenuItem);

            // Log stock change for audit trail
            $this->logStockChange($branchMenuItem, $quantityChange, $previousQuantity, $quantity, 'manual');

            // Trigger notifications based on stock changes
            $this->handleStockChangeNotifications($branchMenuItem, $previousQuantity, $quantity, $previousStatus);

            Log::info('Stock updated', [
                'item_id' => $item->id,
                'branch_id' => $branch->id,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $quantity,
                'change' => $quantityChange,
                'new_status' => $branchMenuItem->stock_status,
            ]);
        });
    }

    /**
     * Check if item is available for ordering - 6-layer validation
     */
    public function checkItemAvailability(MenuItem $item, RestaurantBranch $branch): bool
    {
        // Use cache for performance
        $cacheKey = "item_availability_{$item->id}_{$branch->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($item, $branch) {
            $branchMenuItem = BranchMenuItem::where('menu_item_id', $item->id)
                ->where('restaurant_branch_id', $branch->id)
                ->first();

            if (!$branchMenuItem) {
                return false;
            }

            // Layer 1: Active status check
            if (!$branchMenuItem->is_available) {
                return false;
            }

            // Layer 2: Stock quantity check
            if ($branchMenuItem->track_inventory && $branchMenuItem->stock_quantity <= 0) {
                return false;
            }

            // Layer 3: Time schedule check
            if (!$branchMenuItem->isAvailableForTimeSchedule()) {
                return false;
            }

            // Layer 4: Branch operating hours check
            if (!$this->checkBranchOperatingHours($branch)) {
                return false;
            }

            // Layer 5: Kitchen capacity check
            if (!$branchMenuItem->hasKitchenCapacity()) {
                return false;
            }

            // Layer 6: Stock status check
            if ($branchMenuItem->stock_status === 'out_of_stock') {
                return false;
            }

            return true;
        });
    }

    /**
     * Synchronize inventory levels with POS systems
     */
    public function syncInventoryWithPOS(Restaurant $restaurant): void
    {
        try {
            Log::info('Starting POS inventory synchronization', ['restaurant_id' => $restaurant->id]);

            // Get all branches for the restaurant
            $branches = $restaurant->branches;

            foreach ($branches as $branch) {
                $this->syncBranchInventoryWithPOS($branch);
            }

            Log::info('POS inventory synchronization completed', ['restaurant_id' => $restaurant->id]);
        } catch (Exception $e) {
            Log::error('POS inventory synchronization failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
            ]);

            $this->securityLoggingService->logSecurityEvent(
                null,
                'pos_sync_failure',
                [
                    'restaurant_id' => $restaurant->id,
                    'error' => $e->getMessage(),
                ],
                'error'
            );

            throw $e;
        }
    }

    /**
     * Get items with low stock across restaurant with urgency classification
     */
    public function getLowStockItems(Restaurant $restaurant, int $threshold = 5): array
    {
        $lowStockItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->where('track_inventory', true)
        ->where('stock_quantity', '<=', $threshold)
        ->where('stock_quantity', '>', 0)
        ->with(['menuItem', 'branch'])
        ->get();

        $classifiedItems = [
            'critical' => [],
            'high' => [],
            'medium' => [],
        ];

        foreach ($lowStockItems as $item) {
            $urgency = $this->classifyLowStockUrgency($item);
            $reorderSuggestion = $item->getReorderSuggestion();
            
            $classifiedItems[$urgency][] = [
                'item' => $item,
                'urgency' => $urgency,
                'reorder_suggestion' => $reorderSuggestion,
                'days_until_out_of_stock' => $this->calculateDaysUntilOutOfStock($item),
                'category' => $item->menuItem->category->name ?? 'Uncategorized',
            ];
        }

        return $classifiedItems;
    }

    /**
     * Generate comprehensive inventory report with business intelligence
     */
    public function generateInventoryReport(Restaurant $restaurant, Carbon $period): array
    {
        $startDate = $period->copy()->startOfDay();
        $endDate = $period->copy()->endOfDay();

        $report = [
            'restaurant_id' => $restaurant->id,
            'restaurant_name' => $restaurant->name,
            'period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'stock_movements' => $this->getStockMovements($restaurant, $startDate, $endDate),
            'turnover_rates' => $this->calculateTurnoverRates($restaurant, $startDate, $endDate),
            'low_stock_alerts' => $this->getLowStockAlerts($restaurant),
            'out_of_stock_items' => $this->getOutOfStockItems($restaurant),
            'fast_movers' => $this->getFastMovers($restaurant, $startDate, $endDate),
            'slow_movers' => $this->getSlowMovers($restaurant, $startDate, $endDate),
            'seasonal_trends' => $this->getSeasonalTrends($restaurant, $startDate, $endDate),
            'branch_comparison' => $this->getBranchComparison($restaurant),
            'optimization_insights' => $this->getOptimizationInsights($restaurant, $startDate, $endDate),
            'summary' => $this->generateReportSummary($restaurant, $startDate, $endDate),
        ];

        // Store the report
        $this->storeInventoryReport($restaurant, $report, 'periodic');

        return $report;
    }

    /**
     * Invalidate stock cache for item
     */
    private function invalidateStockCache(BranchMenuItem $branchMenuItem): void
    {
        $cacheKey = "item_availability_{$branchMenuItem->menu_item_id}_{$branchMenuItem->restaurant_branch_id}";
        Cache::forget($cacheKey);
        
        // Also invalidate branch-level cache
        $branchCacheKey = "branch_stock_summary_{$branchMenuItem->restaurant_branch_id}";
        Cache::forget($branchCacheKey);
    }

    /**
     * Handle stock change notifications
     */
    private function handleStockChangeNotifications(BranchMenuItem $branchMenuItem, int $previousQuantity, int $newQuantity, string $previousStatus): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Out of stock notification
        if ($previousQuantity > 0 && $newQuantity <= 0) {
            $this->notificationService->createOutOfStockNotification($branchMenuItem, $user);
        }
        // Low stock notification
        elseif ($branchMenuItem->isLowStock() && !$branchMenuItem->wasLowStock($previousQuantity)) {
            $this->notificationService->createLowStockNotification($branchMenuItem, $user);
        }
    }

    /**
     * Check branch operating hours
     */
    private function checkBranchOperatingHours(RestaurantBranch $branch): bool
    {
        if (!$branch->operating_hours) {
            return true; // No restrictions
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        if (isset($branch->operating_hours[$dayOfWeek])) {
            $hours = $branch->operating_hours[$dayOfWeek];
            
            if (isset($hours['open']) && isset($hours['close'])) {
                return $currentTime >= $hours['open'] && $currentTime <= $hours['close'];
            }
        }

        return false;
    }

    /**
     * Classify low stock urgency
     */
    private function classifyLowStockUrgency(BranchMenuItem $item): string
    {
        $percentage = ($item->stock_quantity / $item->min_stock_threshold) * 100;
        
        if ($percentage <= 25) {
            return 'critical';
        } elseif ($percentage <= 50) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Calculate days until out of stock
     */
    private function calculateDaysUntilOutOfStock(BranchMenuItem $item): float
    {
        $avgDailyConsumption = $item->getAverageDailyConsumption();
        
        if ($avgDailyConsumption <= 0) {
            return 999; // No consumption data
        }
        
        return round($item->stock_quantity / $avgDailyConsumption, 1);
    }

    /**
     * Log stock change for audit trail
     */
    private function logStockChange(
        BranchMenuItem $branchMenuItem,
        int $quantityChange,
        int $previousQuantity,
        int $newQuantity,
        string $changeType,
        ?string $reason = null,
        ?array $metadata = null
    ): void {
        InventoryStockChange::create([
            'branch_menu_item_id' => $branchMenuItem->id,
            'user_id' => auth()->id(),
            'change_type' => $changeType,
            'quantity_change' => $quantityChange,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $reason,
            'metadata' => $metadata,
            'source' => 'inventory_service',
        ]);
    }

    /**
     * Trigger low stock alert
     */
    private function triggerLowStockAlert(BranchMenuItem $branchMenuItem): void
    {
        Log::warning('Low stock alert', [
            'branch_menu_item_id' => $branchMenuItem->id,
            'stock_quantity' => $branchMenuItem->stock_quantity,
            'min_threshold' => $branchMenuItem->min_stock_threshold,
        ]);

        // Here you could implement notification logic
        // For example, send email to restaurant manager
        // or create a notification in the system
    }

    /**
     * Check time-based availability
     */
    private function checkTimeBasedAvailability(BranchMenuItem $branchMenuItem): bool
    {
        // This is a placeholder for time-based availability logic
        // You can implement specific business rules here
        // For example, certain items might only be available during specific hours
        
        $now = now();
        $branch = $branchMenuItem->branch;
        
        // Check if branch is open
        if ($branch->business_hours) {
            $businessHours = $branch->business_hours;
            $dayOfWeek = strtolower($now->format('l'));
            
            if (isset($businessHours[$dayOfWeek])) {
                $hours = $businessHours[$dayOfWeek];
                $currentTime = $now->format('H:i');
                
                // Simple time check - you might want more sophisticated logic
                if (isset($hours['open']) && isset($hours['close'])) {
                    return $currentTime >= $hours['open'] && $currentTime <= $hours['close'];
                }
            }
        }
        
        return true; // Default to available if no specific rules
    }

    /**
     * Sync branch inventory with POS
     */
    private function syncBranchInventoryWithPOS(RestaurantBranch $branch): void
    {
        // This is a placeholder for POS integration
        // You would implement the actual POS API calls here
        
        $branchMenuItems = BranchMenuItem::where('restaurant_branch_id', $branch->id)
            ->where('track_inventory', true)
            ->get();
        
        foreach ($branchMenuItems as $branchMenuItem) {
            // Simulate POS API call to get current stock
            $posStockQuantity = $this->getPOSStockQuantity($branchMenuItem);
            
            if ($posStockQuantity !== $branchMenuItem->stock_quantity) {
                $previousQuantity = $branchMenuItem->stock_quantity;
                $quantityChange = $posStockQuantity - $previousQuantity;
                
                $branchMenuItem->update([
                    'stock_quantity' => $posStockQuantity,
                    'last_stock_update' => now(),
                ]);
                
                $this->updateAvailabilityStatus($branchMenuItem);
                $this->logStockChange($branchMenuItem, $quantityChange, $previousQuantity, $posStockQuantity, 'pos_sync');
                
                Log::info('POS stock sync completed', [
                    'branch_menu_item_id' => $branchMenuItem->id,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $posStockQuantity,
                ]);
            }
        }
    }

    /**
     * Get POS stock quantity (placeholder for actual POS API integration)
     */
    private function getPOSStockQuantity(BranchMenuItem $branchMenuItem): int
    {
        // This is a placeholder - replace with actual POS API call
        // For now, return a random quantity for demonstration
        return rand(0, 50);
    }

    /**
     * Get stock movements for the period
     */
    private function getStockMovements(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $stockChanges = InventoryStockChange::whereHas('branchMenuItem.branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->whereBetween('created_at', [$startDate, $endDate])
        ->with(['branchMenuItem.menuItem', 'branchMenuItem.branch'])
        ->get();

        return [
            'total_changes' => $stockChanges->count(),
            'additions' => $stockChanges->where('quantity_change', '>', 0)->count(),
            'reductions' => $stockChanges->where('quantity_change', '<', 0)->count(),
            'changes_by_type' => $stockChanges->groupBy('change_type')->map->count(),
            'changes_by_source' => $stockChanges->groupBy('source')->map->count(),
        ];
    }

    /**
     * Calculate turnover rates
     */
    private function calculateTurnoverRates(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        // This is a simplified calculation - you might want more sophisticated logic
        $branchMenuItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->where('track_inventory', true)
        ->with(['menuItem'])
        ->get();

        $turnoverRates = [];
        
        foreach ($branchMenuItems as $branchMenuItem) {
            $stockChanges = $branchMenuItem->stockChanges()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('quantity_change', '<', 0) // Only reductions (consumption)
                ->sum('quantity_change');
            
            $averageStock = $branchMenuItem->stock_quantity;
            
            if ($averageStock > 0) {
                $turnoverRate = abs($stockChanges) / $averageStock;
                $turnoverRates[$branchMenuItem->id] = [
                    'item_name' => $branchMenuItem->menuItem->name,
                    'turnover_rate' => round($turnoverRate, 2),
                    'consumption' => abs($stockChanges),
                    'average_stock' => $averageStock,
                ];
            }
        }

        return $turnoverRates;
    }

    /**
     * Get low stock alerts
     */
    private function getLowStockAlerts(Restaurant $restaurant): array
    {
        $lowStockItems = $this->getLowStockItems($restaurant);
        $alerts = [];
        
        foreach (['critical', 'high', 'medium'] as $urgency) {
            if (isset($lowStockItems[$urgency])) {
                foreach ($lowStockItems[$urgency] as $itemData) {
                    $branchMenuItem = $itemData['item'];
                    $alerts[] = [
                        'item_name' => $branchMenuItem->menuItem->name,
                        'branch_name' => $branchMenuItem->branch->name,
                        'current_stock' => $branchMenuItem->stock_quantity,
                        'min_threshold' => $branchMenuItem->min_stock_threshold,
                        'status' => 'low_stock',
                        'urgency' => $urgency,
                    ];
                }
            }
        }
        
        return $alerts;
    }

    /**
     * Get out of stock items
     */
    private function getOutOfStockItems(Restaurant $restaurant): array
    {
        $outOfStockItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->where('track_inventory', true)
        ->where('stock_quantity', 0)
        ->with(['menuItem', 'branch'])
        ->get();

        return $outOfStockItems->map(function ($branchMenuItem) {
            return [
                'item_name' => $branchMenuItem->menuItem->name,
                'branch_name' => $branchMenuItem->branch->name,
                'status' => 'out_of_stock',
            ];
        })->toArray();
    }

    /**
     * Generate report summary
     */
    private function generateReportSummary(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $totalItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })->count();

        $trackedItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })->where('track_inventory', true)->count();

        $lowStockItems = $this->getLowStockItems($restaurant);
        $lowStockCount = count($lowStockItems['critical'] ?? []) + count($lowStockItems['high'] ?? []) + count($lowStockItems['medium'] ?? []);
        $outOfStockCount = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })->where('track_inventory', true)->where('stock_quantity', 0)->count();

        return [
            'total_items' => $totalItems,
            'tracked_items' => $trackedItems,
            'low_stock_items' => $lowStockCount,
            'out_of_stock_items' => $outOfStockCount,
            'tracking_coverage' => $totalItems > 0 ? round(($trackedItems / $totalItems) * 100, 2) : 0,
        ];
    }

    /**
     * Get fast moving items
     */
    private function getFastMovers(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $fastMovers = DB::table('inventory_stock_changes')
            ->join('branch_menu_items', 'inventory_stock_changes.branch_menu_item_id', '=', 'branch_menu_items.id')
            ->join('restaurant_branches', 'branch_menu_items.restaurant_branch_id', '=', 'restaurant_branches.id')
            ->join('menu_items', 'branch_menu_items.menu_item_id', '=', 'menu_items.id')
            ->where('restaurant_branches.restaurant_id', $restaurant->id)
            ->where('inventory_stock_changes.quantity_change', '<', 0)
            ->whereBetween('inventory_stock_changes.created_at', [$startDate, $endDate])
            ->select(
                'menu_items.name as item_name',
                'restaurant_branches.name as branch_name',
                DB::raw('SUM(ABS(inventory_stock_changes.quantity_change)) as total_consumption'),
                DB::raw('COUNT(*) as movement_count')
            )
            ->groupBy('menu_items.id', 'restaurant_branches.id', 'menu_items.name', 'restaurant_branches.name')
            ->orderBy('total_consumption', 'desc')
            ->limit(10)
            ->get();

        return $fastMovers->toArray();
    }

    /**
     * Get slow moving items
     */
    private function getSlowMovers(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $slowMovers = DB::table('inventory_stock_changes')
            ->join('branch_menu_items', 'inventory_stock_changes.branch_menu_item_id', '=', 'branch_menu_items.id')
            ->join('restaurant_branches', 'branch_menu_items.restaurant_branch_id', '=', 'restaurant_branches.id')
            ->join('menu_items', 'branch_menu_items.menu_item_id', '=', 'menu_items.id')
            ->where('restaurant_branches.restaurant_id', $restaurant->id)
            ->where('inventory_stock_changes.quantity_change', '<', 0)
            ->whereBetween('inventory_stock_changes.created_at', [$startDate, $endDate])
            ->select(
                'menu_items.name as item_name',
                'restaurant_branches.name as branch_name',
                DB::raw('SUM(ABS(inventory_stock_changes.quantity_change)) as total_consumption'),
                DB::raw('COUNT(*) as movement_count')
            )
            ->groupBy('menu_items.id', 'restaurant_branches.id', 'menu_items.name', 'restaurant_branches.name')
            ->orderBy('total_consumption', 'asc')
            ->limit(10)
            ->get();

        return $slowMovers->toArray();
    }

    /**
     * Get seasonal trends
     */
    private function getSeasonalTrends(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $trends = DB::table('inventory_stock_changes')
            ->join('branch_menu_items', 'inventory_stock_changes.branch_menu_item_id', '=', 'branch_menu_items.id')
            ->join('restaurant_branches', 'branch_menu_items.restaurant_branch_id', '=', 'restaurant_branches.id')
            ->where('restaurant_branches.restaurant_id', $restaurant->id)
            ->where('inventory_stock_changes.quantity_change', '<', 0)
            ->whereBetween('inventory_stock_changes.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(inventory_stock_changes.created_at) as date'),
                DB::raw('SUM(ABS(inventory_stock_changes.quantity_change)) as daily_consumption'),
                DB::raw('COUNT(*) as movement_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $trends->toArray();
    }

    /**
     * Get branch comparison
     */
    private function getBranchComparison(Restaurant $restaurant): array
    {
        $branches = $restaurant->branches;
        $comparison = [];

        foreach ($branches as $branch) {
            $totalItems = BranchMenuItem::where('restaurant_branch_id', $branch->id)->count();
            $trackedItems = BranchMenuItem::where('restaurant_branch_id', $branch->id)
                ->where('track_inventory', true)
                ->count();
            $lowStockItems = BranchMenuItem::where('restaurant_branch_id', $branch->id)
                ->where('track_inventory', true)
                ->where('stock_quantity', '<=', DB::raw('min_stock_threshold'))
                ->where('stock_quantity', '>', 0)
                ->count();
            $outOfStockItems = BranchMenuItem::where('restaurant_branch_id', $branch->id)
                ->where('track_inventory', true)
                ->where('stock_quantity', 0)
                ->count();

            $comparison[] = [
                'branch_name' => $branch->name,
                'total_items' => $totalItems,
                'tracked_items' => $trackedItems,
                'low_stock_items' => $lowStockItems,
                'out_of_stock_items' => $outOfStockItems,
                'tracking_coverage' => $totalItems > 0 ? round(($trackedItems / $totalItems) * 100, 2) : 0,
            ];
        }

        return $comparison;
    }

    /**
     * Get optimization insights
     */
    private function getOptimizationInsights(Restaurant $restaurant, Carbon $startDate, Carbon $endDate): array
    {
        $insights = [];

        // Overstocked items
        $overstockedItems = BranchMenuItem::whereHas('branch', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->where('track_inventory', true)
        ->where('stock_quantity', '>', DB::raw('min_stock_threshold * 3'))
        ->with(['menuItem', 'branch'])
        ->get();

        $insights['overstocked_items'] = $overstockedItems->map(function ($item) {
            return [
                'item_name' => $item->menuItem->name,
                'branch_name' => $item->branch->name,
                'current_stock' => $item->stock_quantity,
                'recommended_stock' => $item->min_stock_threshold,
                'excess_stock' => $item->stock_quantity - $item->min_stock_threshold,
            ];
        })->toArray();

        // Items with high turnover
        $highTurnoverItems = $this->getFastMovers($restaurant, $startDate, $endDate);
        $insights['high_turnover_items'] = array_slice($highTurnoverItems, 0, 5);

        // Items with low turnover
        $lowTurnoverItems = $this->getSlowMovers($restaurant, $startDate, $endDate);
        $insights['low_turnover_items'] = array_slice($lowTurnoverItems, 0, 5);

        return $insights;
    }

    /**
     * Store inventory report
     */
    private function storeInventoryReport(Restaurant $restaurant, array $reportData, string $reportType): void
    {
        InventoryReport::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => auth()->id(),
            'report_type' => $reportType,
            'report_date' => now()->toDateString(),
            'report_data' => $reportData,
            'summary' => json_encode($reportData['summary']),
            'status' => 'generated',
        ]);
    }
} 