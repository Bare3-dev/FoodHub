<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantConfig;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\MenuCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comprehensive Caching Service
 * 
 * Implements caching strategies for frequently accessed data
 * to improve API response times and reduce database load.
 */
class CachingService
{
    /**
     * Cache TTL constants
     */
    private const CACHE_TTL = [
        'menu_items' => 1800,        // 30 minutes
        'restaurant_config' => 3600, // 1 hour
        'loyalty_rules' => 7200,     // 2 hours
        'categories' => 3600,        // 1 hour
        'restaurant_info' => 1800,   // 30 minutes
        'pos_connection' => 300,     // 5 minutes
        'analytics' => 600,          // 10 minutes
    ];

    /**
     * Cache key prefixes
     */
    private const CACHE_PREFIX = [
        'menu' => 'menu',
        'restaurant' => 'restaurant',
        'loyalty' => 'loyalty',
        'pos' => 'pos',
        'analytics' => 'analytics'
    ];

    /**
     * Get menu items with caching
     */
    public function getMenuItems(string $restaurantId, ?string $branchId = null): array
    {
        $cacheKey = $this->buildCacheKey('menu', 'items', $restaurantId, $branchId);
        
        return Cache::remember($cacheKey, self::CACHE_TTL['menu_items'], function () use ($restaurantId, $branchId) {
            $query = MenuItem::with(['category', 'restaurant'])
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true);

            if ($branchId) {
                $query->whereHas('branchMenuItems', function ($q) use ($branchId) {
                    $q->where('restaurant_branch_id', $branchId);
                });
            }

            $items = $query->get();
            
            return $this->formatMenuItems($items);
        });
    }

    /**
     * Get restaurant configuration with caching
     */
    public function getRestaurantConfig(string $restaurantId): array
    {
        $cacheKey = $this->buildCacheKey('restaurant', 'config', $restaurantId);
        
        return Cache::remember($cacheKey, self::CACHE_TTL['restaurant_config'], function () use ($restaurantId) {
            $config = RestaurantConfig::where('restaurant_id', $restaurantId)->first();
            
            if (!$config) {
                return $this->getDefaultRestaurantConfig();
            }

            return [
                'operating_hours' => $config->operating_hours,
                'delivery_settings' => $config->delivery_settings,
                'payment_settings' => $config->payment_settings,
                'notification_settings' => $config->notification_settings,
                'pos_integration' => $config->pos_integration,
                'last_updated' => $config->updated_at->toISOString()
            ];
        });
    }

    /**
     * Get loyalty program rules with caching
     */
    public function getLoyaltyRules(string $restaurantId): array
    {
        $cacheKey = $this->buildCacheKey('loyalty', 'rules', $restaurantId);
        
        return Cache::remember($cacheKey, self::CACHE_TTL['loyalty_rules'], function () use ($restaurantId) {
            $program = LoyaltyProgram::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->with('tiers')
                ->first();

            if (!$program) {
                return [];
            }

            return [
                'program_id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'points_per_currency' => $program->points_per_currency,
                'tiers' => $program->tiers->map(function ($tier) {
                    return [
                        'id' => $tier->id,
                        'name' => $tier->name,
                        'min_points' => $tier->min_points,
                        'discount_percentage' => $tier->discount_percentage,
                        'benefits' => $tier->benefits
                    ];
                }),
                'last_updated' => $program->updated_at->toISOString()
            ];
        });
    }

    /**
     * Get menu categories with caching
     */
    public function getMenuCategories(string $restaurantId): array
    {
        $cacheKey = $this->buildCacheKey('menu', 'categories', $restaurantId);
        
        return Cache::remember($cacheKey, self::CACHE_TTL['categories'], function () use ($restaurantId) {
            $categories = MenuCategory::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->withCount('menuItems')
                ->orderBy('sort_order')
                ->get();

            return $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'image_url' => $category->image_url,
                    'sort_order' => $category->sort_order,
                    'menu_items_count' => $category->menu_items_count,
                    'is_active' => $category->is_active
                ];
            })->toArray();
        });
    }

    /**
     * Get restaurant information with caching
     */
    public function getRestaurantInfo(string $restaurantId): array
    {
        $cacheKey = $this->buildCacheKey('restaurant', 'info', $restaurantId);
        
        return Cache::remember($cacheKey, self::CACHE_TTL['restaurant_info'], function () use ($restaurantId) {
            $restaurant = Restaurant::with(['branches', 'cuisineTypes'])
                ->find($restaurantId);

            if (!$restaurant) {
                return [];
            }

            return [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'description' => $restaurant->description,
                'logo_url' => $restaurant->logo_url,
                'cover_image_url' => $restaurant->cover_image_url,
                'address' => $restaurant->address,
                'phone' => $restaurant->phone,
                'email' => $restaurant->email,
                'cuisine_types' => $restaurant->cuisineTypes->pluck('name'),
                'branches' => $restaurant->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'address' => $branch->address,
                        'phone' => $branch->phone,
                        'is_active' => $branch->is_active
                    ];
                }),
                'rating' => $restaurant->rating,
                'total_reviews' => $restaurant->total_reviews,
                'is_active' => $restaurant->is_active,
                'last_updated' => $restaurant->updated_at->toISOString()
            ];
        });
    }

    /**
     * Get POS connection status with caching
     */
    public function getPOSConnectionStatus(string $restaurantId, string $posType): array
    {
        $cacheKey = $this->buildCacheKey('pos', 'connection', $restaurantId, $posType);
        
        return Cache::remember($cacheKey, self::CACHE_TTL['pos_connection'], function () use ($restaurantId, $posType) {
            // This would check actual POS connection status
            // For now, return cached status or default
            $status = Cache::get("pos.connection.{$posType}.{$restaurantId}");
            
            if (!$status) {
                return [
                    'status' => 'unknown',
                    'last_check' => now()->toISOString(),
                    'connection_healthy' => false
                ];
            }

            return [
                'status' => $status['status'] ?? 'unknown',
                'last_check' => $status['last_check'] ?? now()->toISOString(),
                'connection_healthy' => ($status['status'] ?? 'unknown') === 'connected',
                'last_error' => $status['last_error'] ?? null,
                'failed_at' => $status['failed_at'] ?? null
            ];
        });
    }

    /**
     * Get analytics data with caching
     */
    public function getAnalyticsData(string $restaurantId, string $metricType, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = $this->buildCacheKey('analytics', $metricType, $restaurantId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        
        return Cache::remember($cacheKey, self::CACHE_TTL['analytics'], function () use ($restaurantId, $metricType, $startDate, $endDate) {
            // This would fetch actual analytics data
            // For now, return cached data or empty array
            return Cache::get("analytics.{$metricType}.{$restaurantId}", []);
        });
    }

    /**
     * Invalidate cache for specific restaurant
     */
    public function invalidateRestaurantCache(string $restaurantId): void
    {
        $patterns = [
            "restaurant:{$restaurantId}:*",
            "menu:{$restaurantId}:*",
            "loyalty:{$restaurantId}:*",
            "pos:{$restaurantId}:*",
            "analytics:*:{$restaurantId}:*"
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateCachePattern($pattern);
        }

        Log::info('Restaurant cache invalidated', ['restaurant_id' => $restaurantId]);
    }

    /**
     * Invalidate menu cache for specific restaurant
     */
    public function invalidateMenuCache(string $restaurantId, ?string $branchId = null): void
    {
        $patterns = [
            "menu:{$restaurantId}:*"
        ];

        if ($branchId) {
            $patterns[] = "menu:{$restaurantId}:{$branchId}:*";
        }

        foreach ($patterns as $pattern) {
            $this->invalidateCachePattern($pattern);
        }

        Log::info('Menu cache invalidated', [
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId
        ]);
    }

    /**
     * Invalidate loyalty cache for specific restaurant
     */
    public function invalidateLoyaltyCache(string $restaurantId): void
    {
        $this->invalidateCachePattern("loyalty:{$restaurantId}:*");
        
        Log::info('Loyalty cache invalidated', ['restaurant_id' => $restaurantId]);
    }

    /**
     * Invalidate POS connection cache
     */
    public function invalidatePOSConnectionCache(string $restaurantId, string $posType): void
    {
        $this->invalidateCachePattern("pos:connection:{$restaurantId}:{$posType}");
        
        Log::info('POS connection cache invalidated', [
            'restaurant_id' => $restaurantId,
            'pos_type' => $posType
        ]);
    }

    /**
     * Warm up cache for frequently accessed data
     */
    public function warmUpCache(string $restaurantId): void
    {
        try {
            // Warm up menu items cache
            $this->getMenuItems($restaurantId);
            
            // Warm up restaurant config cache
            $this->getRestaurantConfig($restaurantId);
            
            // Warm up loyalty rules cache
            $this->getLoyaltyRules($restaurantId);
            
            // Warm up categories cache
            $this->getMenuCategories($restaurantId);
            
            // Warm up restaurant info cache
            $this->getRestaurantInfo($restaurantId);

            Log::info('Cache warmed up successfully', ['restaurant_id' => $restaurantId]);

        } catch (\Exception $e) {
            Log::error('Failed to warm up cache', [
                'restaurant_id' => $restaurantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        try {
            $stats = [
                'total_keys' => $this->getTotalCacheKeys(),
                'memory_usage' => $this->getCacheMemoryUsage(),
                'hit_rate' => $this->getCacheHitRate(),
                'expired_keys' => $this->getExpiredCacheKeys(),
                'last_updated' => now()->toISOString()
            ];

            return $stats;

        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to retrieve cache statistics'];
        }
    }

    /**
     * Build cache key
     */
    private function buildCacheKey(string $prefix, string $type, string $restaurantId, ...$additional): string
    {
        $parts = array_merge([$prefix, $type, $restaurantId], $additional);
        return implode(':', array_filter($parts));
    }

    /**
     * Format menu items for caching
     */
    private function formatMenuItems($items): array
    {
        return $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => $item->price,
                'image_url' => $item->image_url,
                'is_available' => $item->is_available,
                'category' => [
                    'id' => $item->category->id ?? null,
                    'name' => $item->category->name ?? null
                ],
                'nutritional_info' => $item->nutritional_info,
                'allergens' => $item->allergens,
                'customization_options' => $item->customization_options
            ];
        })->toArray();
    }

    /**
     * Get default restaurant configuration
     */
    private function getDefaultRestaurantConfig(): array
    {
        return [
            'operating_hours' => [],
            'delivery_settings' => [
                'delivery_radius' => 10,
                'min_order_amount' => 0,
                'delivery_fee' => 0
            ],
            'payment_settings' => [
                'accepted_payment_methods' => ['cash', 'card'],
                'require_prepayment' => false
            ],
            'notification_settings' => [
                'email_notifications' => true,
                'sms_notifications' => false,
                'push_notifications' => true
            ],
            'pos_integration' => [
                'enabled' => false,
                'type' => null
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Invalidate cache by pattern
     */
    private function invalidateCachePattern(string $pattern): void
    {
        try {
            // This would use Redis SCAN to find and delete keys matching the pattern
            // For now, we'll use a simple approach
            Cache::forget($pattern);
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate cache pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get total cache keys (Redis implementation)
     */
    private function getTotalCacheKeys(): int
    {
        try {
            // This would use Redis DBSIZE command
            return 0; // Placeholder
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache memory usage (Redis implementation)
     */
    private function getCacheMemoryUsage(): array
    {
        try {
            // This would use Redis INFO memory command
            return ['used_memory' => 0, 'used_memory_peak' => 0];
        } catch (\Exception $e) {
            return ['used_memory' => 0, 'used_memory_peak' => 0];
        }
    }

    /**
     * Get cache hit rate (Redis implementation)
     */
    private function getCacheHitRate(): float
    {
        try {
            // This would calculate hit rate from Redis stats
            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get expired cache keys (Redis implementation)
     */
    private function getExpiredCacheKeys(): int
    {
        try {
            // This would use Redis TTL to find expired keys
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
