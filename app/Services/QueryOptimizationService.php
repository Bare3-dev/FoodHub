<?php

namespace App\Services;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\DeliveryTracking;
use App\Models\CustomerLoyaltyPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Query Optimization Service
 * 
 * Demonstrates best practices for avoiding N+1 queries
 * and optimizing database performance using Eloquent eager loading.
 */
class QueryOptimizationService
{
    /**
     * Get restaurant with optimized queries (avoids N+1)
     */
    public function getRestaurantWithOptimizedData(string $restaurantId): ?Restaurant
    {
        return Restaurant::with([
            'branches' => function ($query) {
                $query->select('id', 'restaurant_id', 'name', 'address', 'phone', 'is_active')
                    ->where('is_active', true)
                    ->orderBy('name');
            },
            'branches.menuItems' => function ($query) {
                $query->select('id', 'restaurant_branch_id', 'menu_item_id', 'price', 'is_available')
                    ->where('is_available', true);
            },
            'branches.menuItems.menuItem' => function ($query) {
                $query->select('id', 'name', 'description', 'image_url', 'category_id');
            },
            'branches.menuItems.menuItem.category' => function ($query) {
                $query->select('id', 'name', 'description');
            },
            'cuisineTypes:id,name',
            'config:id,restaurant_id,operating_hours,delivery_settings,payment_settings',
            'loyaltyProgram' => function ($query) {
                $query->select('id', 'restaurant_id', 'name', 'description', 'points_per_currency', 'is_active')
                    ->where('is_active', true);
            },
            'loyaltyProgram.tiers:id,loyalty_program_id,name,min_points,discount_percentage'
        ])
        ->select('id', 'name', 'description', 'logo_url', 'cover_image_url', 'address', 'phone', 'email', 'rating', 'total_reviews')
        ->find($restaurantId);
    }

    /**
     * Get orders with optimized queries (avoids N+1)
     */
    public function getOrdersWithOptimizedData(string $restaurantId, Carbon $startDate, Carbon $endDate): array
    {
        $orders = Order::with([
            'customer:id,name,email,phone',
            'restaurant:id,name',
            'branch:id,name,address',
            'orderItems' => function ($query) {
                $query->select('id', 'order_id', 'menu_item_id', 'quantity', 'unit_price', 'total_price')
                    ->with(['menuItem:id,name,description,image_url']);
            },
            'payment:id,order_id,amount,payment_method,status',
            'deliveryTracking:id,order_id,driver_id,status,estimated_delivery_time',
            'deliveryTracking.driver:id,name,phone,vehicle_info'
        ])
        ->select('id', 'restaurant_id', 'customer_id', 'branch_id', 'order_number', 'status', 'total_amount', 'created_at')
        ->where('restaurant_id', $restaurantId)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->orderBy('created_at', 'desc')
        ->get();

        return $this->formatOrdersForResponse($orders);
    }

    /**
     * Get customer analytics with optimized queries
     */
    public function getCustomerAnalytics(string $restaurantId, Carbon $startDate, Carbon $endDate): array
    {
        // Use raw SQL for complex aggregations to avoid N+1
        $customerStats = DB::table('customers as c')
            ->join('orders as o', 'c.id', '=', 'o.customer_id')
            ->leftJoin('customer_loyalty_points as clp', 'c.id', '=', 'clp.customer_id')
            ->select([
                'c.id',
                'c.name',
                'c.email',
                DB::raw('COUNT(o.id) as total_orders'),
                DB::raw('SUM(o.total_amount) as total_spent'),
                DB::raw('AVG(o.total_amount) as avg_order_value'),
                DB::raw('MAX(o.created_at) as last_order_date'),
                DB::raw('SUM(clp.points_earned) as total_points_earned'),
                DB::raw('SUM(clp.points_redeemed) as total_points_redeemed')
            ])
            ->where('o.restaurant_id', $restaurantId)
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->groupBy('c.id', 'c.name', 'c.email')
            ->having('total_orders', '>', 0)
            ->orderBy('total_spent', 'desc')
            ->limit(100)
            ->get();

        return [
            'customer_analytics' => $customerStats,
            'summary' => $this->getCustomerAnalyticsSummary($customerStats),
            'period' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString()
            ]
        ];
    }

    /**
     * Get menu performance analytics with optimized queries
     */
    public function getMenuPerformanceAnalytics(string $restaurantId, Carbon $startDate, Carbon $endDate): array
    {
        // Use raw SQL for complex aggregations
        $menuPerformance = DB::table('menu_items as mi')
            ->leftJoin('order_items as oi', 'mi.id', '=', 'oi.menu_item_id')
            ->leftJoin('orders as o', 'oi.order_id', '=', 'o.id')
            ->leftJoin('menu_categories as mc', 'mi.menu_category_id', '=', 'mc.id')
            ->select([
                'mi.id',
                'mi.name',
                'mi.description',
                'mi.price',
                'mc.name as category_name',
                DB::raw('COUNT(oi.id) as times_ordered'),
                DB::raw('SUM(oi.quantity) as total_quantity_sold'),
                DB::raw('SUM(oi.total_price) as total_revenue'),
                DB::raw('AVG(oi.unit_price) as avg_selling_price'),
                DB::raw('CASE WHEN COUNT(oi.id) > 0 THEN (SUM(oi.total_price) / COUNT(oi.id)) ELSE 0 END as revenue_per_order')
            ])
            ->where('mi.restaurant_id', $restaurantId)
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->groupBy('mi.id', 'mi.name', 'mi.description', 'mi.price', 'mc.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return [
            'menu_performance' => $menuPerformance,
            'summary' => $this->getMenuPerformanceSummary($menuPerformance),
            'period' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString()
            ]
        ];
    }

    /**
     * Get delivery performance analytics with optimized queries
     */
    public function getDeliveryPerformanceAnalytics(string $restaurantId, Carbon $startDate, Carbon $endDate): array
    {
        $deliveryStats = DB::table('orders as o')
            ->leftJoin('delivery_tracking as dt', 'o.id', '=', 'dt.order_id')
            ->leftJoin('drivers as d', 'dt.driver_id', '=', 'd.id')
            ->select([
                DB::raw('DATE(o.created_at) as order_date'),
                DB::raw('COUNT(o.id) as total_orders'),
                DB::raw('COUNT(CASE WHEN o.type = "delivery" THEN 1 END) as delivery_orders'),
                DB::raw('COUNT(CASE WHEN o.type = "pickup" THEN 1 END) as pickup_orders'),
                DB::raw('AVG(CASE WHEN dt.estimated_delivery_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, o.created_at, dt.estimated_delivery_time) END) as avg_delivery_time_minutes'),
                DB::raw('COUNT(CASE WHEN dt.status = "delivered" THEN 1 END) as completed_deliveries'),
                DB::raw('COUNT(CASE WHEN dt.status = "in_transit" THEN 1 END) as in_transit_deliveries')
            ])
            ->where('o.restaurant_id', $restaurantId)
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(o.created_at)'))
            ->orderBy('order_date', 'desc')
            ->get();

        return [
            'delivery_analytics' => $deliveryStats,
            'summary' => $this->getDeliveryPerformanceSummary($deliveryStats),
            'period' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString()
            ]
        ];
    }

    /**
     * Get inventory analytics with optimized queries
     */
    public function getInventoryAnalytics(string $restaurantId): array
    {
        // Use chunking for large datasets to avoid memory issues
        $inventoryData = collect();
        
        MenuItem::where('restaurant_id', $restaurantId)
            ->with(['category:id,name', 'inventoryStockChanges' => function ($query) {
                $query->select('id', 'menu_item_id', 'change_type', 'quantity', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(10);
            }])
            ->chunk(100, function ($items) use ($inventoryData) {
                foreach ($items as $item) {
                    $inventoryData->push([
                        'id' => $item->id,
                        'name' => $item->name,
                        'category' => $item->category->name ?? 'Uncategorized',
                        'current_stock' => $item->current_stock ?? 0,
                        'reorder_point' => $item->reorder_point ?? 0,
                        'recent_stock_changes' => $item->inventoryStockChanges->map(function ($change) {
                            return [
                                'type' => $change->change_type,
                                'quantity' => $change->quantity,
                                'date' => $change->created_at->toISOString()
                            ];
                        })
                    ]);
                }
            });

        return [
            'inventory_analytics' => $inventoryData,
            'summary' => $this->getInventoryAnalyticsSummary($inventoryData),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Get optimized search results with pagination
     */
    public function getOptimizedSearchResults(string $restaurantId, string $searchTerm, int $perPage = 20): array
    {
        $query = MenuItem::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where(function (Builder $q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereHas('category', function (Builder $categoryQuery) use ($searchTerm) {
                      $categoryQuery->where('name', 'ILIKE', "%{$searchTerm}%");
                  });
            })
            ->with(['category:id,name', 'restaurant:id,name'])
            ->select('id', 'name', 'description', 'price', 'image_url', 'category_id', 'restaurant_id');

        $total = $query->count();
        $items = $query->orderBy('name')
            ->paginate($perPage);

        return [
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $total,
                'from' => $items->firstItem(),
                'to' => $items->lastItem()
            ]
        ];
    }

    /**
     * Format orders for response
     */
    private function formatOrdersForResponse($orders): array
    {
        return $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at->toISOString(),
                'customer' => [
                    'id' => $order->customer->id ?? null,
                    'name' => $order->customer->name ?? null,
                    'email' => $order->customer->email ?? null
                ],
                'restaurant' => [
                    'id' => $order->restaurant->id ?? null,
                    'name' => $order->restaurant->name ?? null
                ],
                'branch' => [
                    'id' => $order->branch->id ?? null,
                    'name' => $order->branch->name ?? null,
                    'address' => $order->branch->address ?? null
                ],
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'menu_item' => [
                            'id' => $item->menuItem->id ?? null,
                            'name' => $item->menuItem->name ?? null,
                            'description' => $item->menuItem->description ?? null
                        ]
                    ];
                }),
                'payment' => $order->payment ? [
                    'id' => $order->payment->id,
                    'amount' => $order->payment->amount,
                    'method' => $order->payment->payment_method,
                    'status' => $order->payment->status
                ] : null,
                'delivery' => $order->deliveryTracking ? [
                    'id' => $order->deliveryTracking->id,
                    'status' => $order->deliveryTracking->status,
                    'estimated_delivery' => $order->deliveryTracking->estimated_delivery_time,
                    'driver' => $order->deliveryTracking->driver ? [
                        'id' => $order->deliveryTracking->driver->id,
                        'name' => $order->deliveryTracking->driver->name,
                        'phone' => $order->deliveryTracking->driver->phone
                    ] : null
                ] : null
            ];
        })->toArray();
    }

    /**
     * Get customer analytics summary
     */
    private function getCustomerAnalyticsSummary($customerStats): array
    {
        if ($customerStats->isEmpty()) {
            return [];
        }

        return [
            'total_customers' => $customerStats->count(),
            'total_revenue' => $customerStats->sum('total_spent'),
            'avg_order_value' => $customerStats->avg('avg_order_value'),
            'top_spender' => $customerStats->first(),
            'customer_retention_rate' => $this->calculateCustomerRetentionRate($customerStats)
        ];
    }

    /**
     * Get menu performance summary
     */
    private function getMenuPerformanceSummary($menuPerformance): array
    {
        if ($menuPerformance->isEmpty()) {
            return [];
        }

        return [
            'total_items' => $menuPerformance->count(),
            'total_revenue' => $menuPerformance->sum('total_revenue'),
            'most_popular_item' => $menuPerformance->first(),
            'avg_revenue_per_item' => $menuPerformance->avg('total_revenue'),
            'top_performing_category' => $menuPerformance->groupBy('category_name')
                ->map(function ($items) {
                    return [
                        'category' => $items->first()->category_name,
                        'total_revenue' => $items->sum('total_revenue'),
                        'item_count' => $items->count()
                    ];
                })
                ->sortByDesc('total_revenue')
                ->first()
        ];
    }

    /**
     * Get delivery performance summary
     */
    private function getDeliveryPerformanceSummary($deliveryStats): array
    {
        if ($deliveryStats->isEmpty()) {
            return [];
        }

        return [
            'total_orders' => $deliveryStats->sum('total_orders'),
            'delivery_orders' => $deliveryStats->sum('delivery_orders'),
            'pickup_orders' => $deliveryStats->sum('pickup_orders'),
            'avg_delivery_time' => $deliveryStats->avg('avg_delivery_time_minutes'),
            'delivery_success_rate' => $this->calculateDeliverySuccessRate($deliveryStats)
        ];
    }

    /**
     * Get inventory analytics summary
     */
    private function getInventoryAnalyticsSummary($inventoryData): array
    {
        if ($inventoryData->isEmpty()) {
            return [];
        }

        $lowStockItems = $inventoryData->filter(function ($item) {
            return $item['current_stock'] <= $item['reorder_point'];
        });

        return [
            'total_items' => $inventoryData->count(),
            'low_stock_items' => $lowStockItems->count(),
            'items_needing_reorder' => $lowStockItems->pluck('name')->toArray(),
            'avg_stock_level' => $inventoryData->avg('current_stock')
        ];
    }

    /**
     * Calculate customer retention rate
     */
    private function calculateCustomerRetentionRate($customerStats): float
    {
        // Simple calculation - in production this would be more sophisticated
        $customersWithMultipleOrders = $customerStats->filter(function ($customer) {
            return $customer->total_orders > 1;
        })->count();

        return $customerStats->count() > 0 
            ? round(($customersWithMultipleOrders / $customerStats->count()) * 100, 2)
            : 0;
    }

    /**
     * Calculate delivery success rate
     */
    private function calculateDeliverySuccessRate($deliveryStats): float
    {
        $totalDeliveries = $deliveryStats->sum('delivery_orders');
        $completedDeliveries = $deliveryStats->sum('completed_deliveries');

        return $totalDeliveries > 0 
            ? round(($completedDeliveries / $totalDeliveries) * 100, 2)
            : 0;
    }
}
