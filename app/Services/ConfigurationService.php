<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantConfig;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\SecurityException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Configuration Service for Restaurant and Branch Settings
 * 
 * Centralized configuration management system for restaurant and branch-specific
 * settings with encryption support, caching, and comprehensive validation.
 * 
 * Features:
 * - Restaurant-specific configuration management
 * - Branch-specific configuration with fallback to restaurant configs
 * - Operating hours management
 * - Loyalty program configuration
 * - Encrypted storage for sensitive data
 * - Comprehensive caching and invalidation
 * - Security logging and audit trails
 */
class ConfigurationService
{
    /**
     * Cache TTL for configuration data (in seconds)
     */
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Default operating hours structure
     */
    private const DEFAULT_OPERATING_HOURS = [
        'monday' => ['open' => '09:00', 'close' => '22:00'],
        'tuesday' => ['open' => '09:00', 'close' => '22:00'],
        'wednesday' => ['open' => '09:00', 'close' => '22:00'],
        'thursday' => ['open' => '09:00', 'close' => '22:00'],
        'friday' => ['open' => '09:00', 'close' => '23:00'],
        'saturday' => ['open' => '10:00', 'close' => '23:00'],
        'sunday' => ['open' => '10:00', 'close' => '21:00'],
    ];

    /**
     * Default loyalty program settings
     */
    private const DEFAULT_LOYALTY_SETTINGS = [
        'points_per_currency' => 1,
        'currency_per_point' => 0.01,
        'tier_thresholds' => [
            'bronze' => 0,
            'silver' => 100,
            'gold' => 500,
            'platinum' => 1000,
        ],
        'spin_wheel_probabilities' => [
            'points_10' => 0.4,
            'points_25' => 0.3,
            'points_50' => 0.2,
            'points_100' => 0.1,
        ],
        'stamp_card_requirements' => [
            'stamps_needed' => 10,
            'reward_value' => 5.00,
        ],
    ];

    /**
     * Get restaurant-specific configuration
     */
    public function getRestaurantConfig(Restaurant $restaurant, ?string $key = null): mixed
    {
        try {
            $cacheKey = "restaurant_config_{$restaurant->id}" . ($key ? "_$key" : '');
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($restaurant, $key) {
                $query = RestaurantConfig::where('restaurant_id', $restaurant->id);
                
                if ($key) {
                    $query->where('config_key', $key);
                    $config = $query->first();
                    
                    if (!$config) {
                        return $this->getDefaultConfig($key);
                    }
                    
                    return $this->processConfigValue($config);
                }
                
                $configs = $query->get();
                $result = [];
                
                foreach ($configs as $config) {
                    $result[$config->config_key] = $this->processConfigValue($config);
                }
                
                // Merge with defaults for missing configs
                $result = array_merge($this->getAllDefaultConfigs(), $result);
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Failed to get restaurant config', [
                'restaurant_id' => $restaurant->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw new BusinessLogicException('Failed to retrieve restaurant configuration');
        }
    }

    /**
     * Set restaurant configuration
     */
    public function setRestaurantConfig(Restaurant $restaurant, string $key, mixed $value): void
    {
        try {
            DB::transaction(function () use ($restaurant, $key, $value) {
                $this->validateConfigKey($key);
                $this->validateConfigValue($value);
                
                // Call specific validation methods for known config types
                if ($key === 'operating_hours' && is_array($value)) {
                    $this->validateOperatingHours($value);
                }
                
                if (str_starts_with($key, 'loyalty_') && is_array($value)) {
                    if ($key === 'loyalty_spin_wheel_probabilities') {
                        $this->validateSpinWheelProbabilities($value);
                    } elseif ($key === 'loyalty_tier_thresholds') {
                        $this->validateTierThresholds($value);
                    }
                }
                
                $dataType = $this->determineDataType($value);
                $isSensitive = $this->isSensitiveConfig($key);
                $isEncrypted = $isSensitive;
                
                $configValue = $this->serializeConfigValue($value, $dataType);
                
                if ($isEncrypted) {
                    $configValue = app(EncryptionService::class)->encrypt($configValue);
                }
                
                RestaurantConfig::updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'config_key' => $key,
                    ],
                    [
                        'config_value' => $configValue,
                        'is_encrypted' => $isEncrypted,
                        'data_type' => $dataType,
                        'is_sensitive' => $isSensitive,
                        'description' => $this->getConfigDescription($key),
                    ]
                );
                
                // Clear related cache
                $this->clearRestaurantConfigCache($restaurant, $key);
                
                // Log configuration change
                Log::info('Restaurant configuration updated', [
                    'restaurant_id' => $restaurant->id,
                    'config_key' => $key,
                    'is_sensitive' => $isSensitive,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to set restaurant config', [
                'restaurant_id' => $restaurant->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw new BusinessLogicException('Failed to update restaurant configuration');
        }
    }

    /**
     * Get branch-specific configuration
     */
    public function getBranchConfig(RestaurantBranch $branch, ?string $key = null): mixed
    {
        try {
            // Ensure restaurant relationship is loaded
            if (!$branch->relationLoaded('restaurant')) {
                $branch->load('restaurant');
            }
            
            // Debug logging
            Log::info('getBranchConfig called', [
                'branch_id' => $branch->id,
                'branch_restaurant_id' => $branch->restaurant_id,
                'restaurant_loaded' => $branch->relationLoaded('restaurant'),
                'restaurant_id' => $branch->restaurant ? $branch->restaurant->id : null,
                'key' => $key,
            ]);
            
            $cacheKey = "branch_config_{$branch->id}" . ($key ? "_$key" : '');
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($branch, $key) {
                if ($key) {
                    // First check for branch-specific config
                    $branchConfig = RestaurantConfig::where('restaurant_id', $branch->restaurant_id)
                        ->where('config_key', "branch_{$branch->id}_$key")
                        ->first();
                    
                    if ($branchConfig) {
                        return $this->processConfigValue($branchConfig);
                    }
                    
                    // Fall back to restaurant config
                    return $this->getRestaurantConfig($branch->restaurant, $key);
                }
                
                // Get all branch-specific configs
                $branchConfigs = RestaurantConfig::where('restaurant_id', $branch->restaurant_id)
                    ->where('config_key', 'like', "branch_{$branch->id}_%")
                    ->get();
                $result = [];
                
                foreach ($branchConfigs as $config) {
                    $branchKey = str_replace("branch_{$branch->id}_", '', $config->config_key);
                    $result[$branchKey] = $this->processConfigValue($config);
                }
                
                // Merge with restaurant configs
                $restaurantConfigs = $this->getRestaurantConfig($branch->restaurant);
                $result = array_merge($restaurantConfigs, $result);
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Failed to get branch config', [
                'branch_id' => $branch->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw new BusinessLogicException('Failed to retrieve branch configuration');
        }
    }

    /**
     * Update branch operating hours
     */
    public function updateOperatingHours(RestaurantBranch $branch, array $hours): void
    {
        try {
            DB::transaction(function () use ($branch, $hours) {
                $this->validateOperatingHours($hours);
                
                // Update branch operating_hours field
                $branch->update([
                    'operating_hours' => array_merge(self::DEFAULT_OPERATING_HOURS, $hours)
                ]);
                
                // Clear menu availability cache
                Cache::forget("menu_availability_branch_{$branch->id}");
                Cache::forget("order_acceptance_branch_{$branch->id}");
                
                Log::info('Branch operating hours updated', [
                    'branch_id' => $branch->id,
                    'hours' => $hours,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update operating hours', [
                'branch_id' => $branch->id,
                'hours' => $hours,
                'error' => $e->getMessage(),
            ]);
            throw new BusinessLogicException('Failed to update operating hours');
        }
    }

    /**
     * Configure loyalty program settings
     */
    public function configureLoyaltyProgram(Restaurant $restaurant, array $settings): void
    {
        try {
            DB::transaction(function () use ($restaurant, $settings) {
                $this->validateLoyaltySettings($settings);
                
                $loyaltyConfigs = [
                    'loyalty_points_per_currency' => $settings['points_per_currency'] ?? 1,
                    'loyalty_currency_per_point' => $settings['currency_per_point'] ?? 0.01,
                    'loyalty_tier_thresholds' => $settings['tier_thresholds'] ?? self::DEFAULT_LOYALTY_SETTINGS['tier_thresholds'],
                    'loyalty_spin_wheel_probabilities' => $settings['spin_wheel_probabilities'] ?? self::DEFAULT_LOYALTY_SETTINGS['spin_wheel_probabilities'],
                    'loyalty_stamp_card_requirements' => $settings['stamp_card_requirements'] ?? self::DEFAULT_LOYALTY_SETTINGS['stamp_card_requirements'],
                ];
                
                foreach ($loyaltyConfigs as $key => $value) {
                    $this->setRestaurantConfig($restaurant, $key, $value);
                }
                
                // Clear loyalty-related caches
                Cache::forget("loyalty_program_{$restaurant->id}");
                Cache::forget("loyalty_tiers_{$restaurant->id}");
                
                Log::info('Loyalty program configured', [
                    'restaurant_id' => $restaurant->id,
                    'settings' => array_keys($settings),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to configure loyalty program', [
                'restaurant_id' => $restaurant->id,
                'settings' => $settings,
                'error' => $e->getMessage(),
            ]);
            throw new BusinessLogicException('Failed to configure loyalty program');
        }
    }

    /**
     * Process configuration value (decrypt if needed)
     */
    private function processConfigValue(RestaurantConfig $config): mixed
    {
        $value = $config->config_value;
        
        if ($config->is_encrypted) {
            $value = app(EncryptionService::class)->decrypt($value);
        }
        
        return $this->deserializeConfigValue($value, $config->data_type);
    }

    /**
     * Serialize configuration value for storage
     */
    private function serializeConfigValue(mixed $value, string $dataType): string
    {
        return match ($dataType) {
            'array', 'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Deserialize configuration value from storage
     */
    private function deserializeConfigValue(string $value, string $dataType): mixed
    {
        return match ($dataType) {
            'array', 'json' => json_decode($value, true),
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => (bool) $value,
            default => $value,
        };
    }

    /**
     * Determine data type of value
     */
    private function determineDataType(mixed $value): string
    {
        return match (true) {
            is_array($value) => 'array',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            default => 'string',
        };
    }

    /**
     * Check if config key is sensitive
     */
    private function isSensitiveConfig(string $key): bool
    {
        $sensitiveKeys = [
            'payment_gateway_key',
            'api_secret',
            'encryption_key',
            'webhook_secret',
            'ssl_certificate',
        ];
        
        return in_array($key, $sensitiveKeys);
    }

    /**
     * Get configuration description
     */
    private function getConfigDescription(string $key): string
    {
        $descriptions = [
            'loyalty_points_per_currency' => 'Points earned per currency unit spent',
            'loyalty_currency_per_point' => 'Currency value per loyalty point',
            'loyalty_tier_thresholds' => 'Points required for each loyalty tier',
            'loyalty_spin_wheel_probabilities' => 'Probability distribution for spin wheel prizes',
            'loyalty_stamp_card_requirements' => 'Requirements for stamp card completion',
            'operating_hours' => 'Restaurant operating hours',
            'delivery_zones' => 'Delivery zone configurations',
            'commission_rate' => 'Platform commission percentage',
        ];
        
        return $descriptions[$key] ?? 'Configuration setting';
    }

    /**
     * Validate configuration key
     */
    private function validateConfigKey(string $key): void
    {
        if (empty($key) || strlen($key) > 255) {
            throw new BusinessLogicException('Invalid configuration key');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            throw new BusinessLogicException('Configuration key contains invalid characters');
        }
    }

    /**
     * Validate configuration value
     */
    private function validateConfigValue(mixed $value): void
    {
        if (is_string($value) && strlen($value) > 65535) {
            throw new BusinessLogicException('Configuration value too large');
        }
        
        if (is_array($value) && count($value) > 1000) {
            throw new BusinessLogicException('Configuration array too large');
        }
    }

    /**
     * Validate operating hours
     */
    private function validateOperatingHours(array $hours): void
    {
        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($hours as $day => $times) {
            if (!in_array($day, $validDays)) {
                throw new BusinessLogicException("Invalid day: $day");
            }
            
            if (!isset($times['open']) || !isset($times['close'])) {
                throw new BusinessLogicException("Invalid time format for $day");
            }
            
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $times['open']) ||
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $times['close'])) {
                throw new BusinessLogicException("Invalid time format for $day");
            }
        }
    }

    /**
     * Validate loyalty settings
     */
    private function validateLoyaltySettings(array $settings): void
    {
        if (isset($settings['points_per_currency']) && $settings['points_per_currency'] <= 0) {
            throw new BusinessLogicException('Points per currency must be positive');
        }
        
        if (isset($settings['currency_per_point']) && $settings['currency_per_point'] <= 0) {
            throw new BusinessLogicException('Currency per point must be positive');
        }
        
        if (isset($settings['tier_thresholds'])) {
            $previousThreshold = 0;
            foreach ($settings['tier_thresholds'] as $tier => $threshold) {
                if ($threshold < $previousThreshold) {
                    throw new BusinessLogicException("Invalid tier threshold for $tier");
                }
                $previousThreshold = $threshold;
            }
        }
        
        if (isset($settings['spin_wheel_probabilities'])) {
            $totalProbability = array_sum($settings['spin_wheel_probabilities']);
            if (abs($totalProbability - 1.0) > 0.01) {
                throw new BusinessLogicException('Spin wheel probabilities must sum to 1.0');
            }
        }
    }

    /**
     * Validate spin wheel probabilities
     */
    private function validateSpinWheelProbabilities(array $probabilities): void
    {
        $totalProbability = array_sum($probabilities);
        if (abs($totalProbability - 1.0) > 0.01) {
            throw new BusinessLogicException('Spin wheel probabilities must sum to 1.0');
        }
    }

    /**
     * Validate tier thresholds
     */
    private function validateTierThresholds(array $thresholds): void
    {
        $previousThreshold = 0;
        foreach ($thresholds as $tier => $threshold) {
            if ($threshold < $previousThreshold) {
                throw new BusinessLogicException("Invalid tier threshold for $tier");
            }
            $previousThreshold = $threshold;
        }
    }

    /**
     * Get default configuration value
     */
    private function getDefaultConfig(string $key): mixed
    {
        $defaults = [
            'loyalty_points_per_currency' => 1,
            'loyalty_currency_per_point' => 0.01,
            'loyalty_tier_thresholds' => self::DEFAULT_LOYALTY_SETTINGS['tier_thresholds'],
            'loyalty_spin_wheel_probabilities' => self::DEFAULT_LOYALTY_SETTINGS['spin_wheel_probabilities'],
            'loyalty_stamp_card_requirements' => self::DEFAULT_LOYALTY_SETTINGS['stamp_card_requirements'],
            'operating_hours' => self::DEFAULT_OPERATING_HOURS,
        ];
        
        return $defaults[$key] ?? null;
    }

    /**
     * Get all default configurations
     */
    private function getAllDefaultConfigs(): array
    {
        return [
            'loyalty_points_per_currency' => 1,
            'loyalty_currency_per_point' => 0.01,
            'loyalty_tier_thresholds' => self::DEFAULT_LOYALTY_SETTINGS['tier_thresholds'],
            'loyalty_spin_wheel_probabilities' => self::DEFAULT_LOYALTY_SETTINGS['spin_wheel_probabilities'],
            'loyalty_stamp_card_requirements' => self::DEFAULT_LOYALTY_SETTINGS['stamp_card_requirements'],
            'operating_hours' => self::DEFAULT_OPERATING_HOURS,
        ];
    }

    /**
     * Clear restaurant configuration cache
     */
    private function clearRestaurantConfigCache(Restaurant $restaurant, ?string $key = null): void
    {
        $cacheKey = "restaurant_config_{$restaurant->id}";
        Cache::forget($cacheKey);
        
        if ($key) {
            Cache::forget("{$cacheKey}_{$key}");
        }
    }
} 