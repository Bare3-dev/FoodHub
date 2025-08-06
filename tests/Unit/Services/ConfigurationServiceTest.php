<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantConfig;
use App\Services\ConfigurationService;
use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConfigurationService $configurationService;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configurationService = app(ConfigurationService::class);
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    /** @test */
    public function it_can_get_restaurant_config_with_key()
    {
        // Create a test config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        $this->assertEquals('test_value', $result);
    }

    /** @test */
    public function it_returns_default_config_when_key_not_found()
    {
        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'loyalty_points_per_currency');

        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_can_get_all_restaurant_configs()
    {
        // Create test configs
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'custom_key',
            'config_value' => 'custom_value',
            'data_type' => 'string',
        ]);

        $result = $this->configurationService->getRestaurantConfig($this->restaurant);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('custom_key', $result);
        $this->assertArrayHasKey('loyalty_points_per_currency', $result);
        $this->assertEquals('custom_value', $result['custom_key']);
        $this->assertEquals(1, $result['loyalty_points_per_currency']);
    }

    /** @test */
    public function it_can_set_restaurant_config()
    {
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'test_value');

        $config = RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'test_key')
            ->first();

        $this->assertNotNull($config);
        $this->assertEquals('test_value', $config->config_value);
        $this->assertEquals('string', $config->data_type);
    }

    /** @test */
    public function it_encrypts_sensitive_configs()
    {
        $this->configurationService->setRestaurantConfig($this->restaurant, 'payment_gateway_key', 'secret_key');

        $config = RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'payment_gateway_key')
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->is_encrypted);
        $this->assertTrue($config->is_sensitive);
        $this->assertNotEquals('secret_key', $config->config_value);
    }

    /** @test */
    public function it_can_get_branch_config_with_fallback()
    {
        // Set restaurant config
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'restaurant_value');

        // Set branch-specific config
        $this->configurationService->setRestaurantConfig($this->restaurant, "branch_{$this->branch->id}_test_key", 'branch_value');

        $result = $this->configurationService->getBranchConfig($this->branch, 'test_key');

        $this->assertEquals('branch_value', $result);
    }

    /** @test */
    public function it_falls_back_to_restaurant_config_when_branch_config_not_found()
    {
        // Set restaurant config only
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'restaurant_value');

        $result = $this->configurationService->getBranchConfig($this->branch, 'test_key');

        $this->assertEquals('restaurant_value', $result);
    }

    /** @test */
    public function it_can_update_operating_hours()
    {
        $hours = [
            'monday' => ['open' => '08:00', 'close' => '21:00'],
            'tuesday' => ['open' => '08:00', 'close' => '21:00'],
        ];

        $this->configurationService->updateOperatingHours($this->branch, $hours);

        $this->branch->refresh();
        $operatingHours = $this->branch->operating_hours;

        $this->assertEquals('08:00', $operatingHours['monday']['open']);
        $this->assertEquals('21:00', $operatingHours['monday']['close']);
        $this->assertEquals('08:00', $operatingHours['tuesday']['open']);
        $this->assertEquals('21:00', $operatingHours['tuesday']['close']);
    }

    /** @test */
    public function it_validates_operating_hours_format()
    {
        $this->expectException(BusinessLogicException::class);

        $invalidHours = [
            'monday' => ['open' => '25:00', 'close' => '26:00'], // Invalid time format
        ];

        $this->configurationService->updateOperatingHours($this->branch, $invalidHours);
    }

    /** @test */
    public function it_can_configure_loyalty_program()
    {
        $settings = [
            'points_per_currency' => 2,
            'currency_per_point' => 0.02,
            'tier_thresholds' => [
                'bronze' => 0,
                'silver' => 200,
                'gold' => 1000,
                'platinum' => 2000,
            ],
            'spin_wheel_probabilities' => [
                'points_10' => 0.5,
                'points_25' => 0.3,
                'points_50' => 0.15,
                'points_100' => 0.05,
            ],
            'stamp_card_requirements' => [
                'stamps_needed' => 15,
                'reward_value' => 10.00,
            ],
        ];

        $this->configurationService->configureLoyaltyProgram($this->restaurant, $settings);

        // Verify configs were set
        $pointsPerCurrency = $this->configurationService->getRestaurantConfig($this->restaurant, 'loyalty_points_per_currency');
        $currencyPerPoint = $this->configurationService->getRestaurantConfig($this->restaurant, 'loyalty_currency_per_point');
        $tierThresholds = $this->configurationService->getRestaurantConfig($this->restaurant, 'loyalty_tier_thresholds');

        $this->assertEquals(2, $pointsPerCurrency);
        $this->assertEquals(0.02, $currencyPerPoint);
        $this->assertEquals($settings['tier_thresholds'], $tierThresholds);
    }

    /** @test */
    public function it_validates_loyalty_settings()
    {
        $this->expectException(BusinessLogicException::class);

        $invalidSettings = [
            'points_per_currency' => -1, // Invalid: negative value
        ];

        $this->configurationService->configureLoyaltyProgram($this->restaurant, $invalidSettings);
    }

    /** @test */
    public function it_validates_spin_wheel_probabilities_sum()
    {
        $this->expectException(BusinessLogicException::class);

        $invalidSettings = [
            'spin_wheel_probabilities' => [
                'points_10' => 0.5,
                'points_25' => 0.3,
                'points_50' => 0.1,
                'points_100' => 0.3, // Sum = 1.2, should be 1.0
            ],
        ];

        $this->configurationService->configureLoyaltyProgram($this->restaurant, $invalidSettings);
    }

    /** @test */
    public function it_caches_configuration_data()
    {
        // Create a config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        // First call should cache
        $result1 = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');
        
        // Update the config directly in database
        RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'test_key')
            ->update(['config_value' => 'updated_value']);

        // Second call should return cached value
        $result2 = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        $this->assertEquals('test_value', $result1);
        $this->assertEquals('test_value', $result2); // Should return cached value
    }

    /** @test */
    public function it_clears_cache_when_config_is_updated()
    {
        // Create a config
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        // First call to cache
        $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        // Update config through service
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'updated_value');

        // Get config again
        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        $this->assertEquals('updated_value', $result);
    }

    /** @test */
    public function it_handles_different_data_types()
    {
        // Test integer
        $this->configurationService->setRestaurantConfig($this->restaurant, 'int_key', 42);
        $intResult = $this->configurationService->getRestaurantConfig($this->restaurant, 'int_key');
        $this->assertEquals(42, $intResult);

        // Test float
        $this->configurationService->setRestaurantConfig($this->restaurant, 'float_key', 3.14);
        $floatResult = $this->configurationService->getRestaurantConfig($this->restaurant, 'float_key');
        $this->assertEquals(3.14, $floatResult);

        // Test boolean
        $this->configurationService->setRestaurantConfig($this->restaurant, 'bool_key', true);
        $boolResult = $this->configurationService->getRestaurantConfig($this->restaurant, 'bool_key');
        $this->assertTrue($boolResult);

        // Test array
        $arrayValue = ['key' => 'value', 'number' => 123];
        $this->configurationService->setRestaurantConfig($this->restaurant, 'array_key', $arrayValue);
        $arrayResult = $this->configurationService->getRestaurantConfig($this->restaurant, 'array_key');
        $this->assertEquals($arrayValue, $arrayResult);
    }

    /** @test */
    public function it_validates_config_key_format()
    {
        $this->expectException(BusinessLogicException::class);

        $this->configurationService->setRestaurantConfig($this->restaurant, 'invalid-key', 'value');
    }

    /** @test */
    public function it_validates_config_value_size()
    {
        $this->expectException(BusinessLogicException::class);

        $largeValue = str_repeat('a', 70000); // Too large
        $this->configurationService->setRestaurantConfig($this->restaurant, 'large_key', $largeValue);
    }

    /** @test */
    public function it_handles_encrypted_configs_properly()
    {
        $sensitiveValue = 'secret_api_key_123';
        
        $this->configurationService->setRestaurantConfig($this->restaurant, 'api_secret', $sensitiveValue);
        
        $retrievedValue = $this->configurationService->getRestaurantConfig($this->restaurant, 'api_secret');
        
        $this->assertEquals($sensitiveValue, $retrievedValue);
    }

    /** @test */
    public function it_uses_transactions_for_config_updates()
    {
        // Mock DB to verify transaction is used
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'test_value');
    }
} 