<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ConfigurationService;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function test_can_get_restaurant_config_with_key(): void
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

    #[Test]
    public function test_returns_default_config_when_key_not_found(): void
    {
        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'loyalty_points_per_currency');

        $this->assertEquals(1, $result);
    }

    #[Test]
    public function test_can_get_all_restaurant_configs(): void
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

    #[Test]
    public function test_can_set_restaurant_config(): void
    {
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'test_value');

        $config = RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'test_key')
            ->first();

        $this->assertNotNull($config);
        $this->assertEquals('test_value', $config->config_value);
        $this->assertEquals('string', $config->data_type);
    }

    #[Test]
    public function test_encrypts_sensitive_configs(): void
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

    #[Test]
    public function test_can_get_branch_config_with_fallback(): void
    {
        // Set restaurant config
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'restaurant_value');

        // Set branch-specific config
        $this->configurationService->setRestaurantConfig($this->restaurant, "branch_{$this->branch->id}_test_key", 'branch_value');

        $result = $this->configurationService->getBranchConfig($this->branch, 'test_key');

        $this->assertEquals('branch_value', $result);
    }

    #[Test]
    public function test_falls_back_to_restaurant_config_when_branch_config_not_found(): void
    {
        // Set restaurant config only
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'restaurant_value');

        $result = $this->configurationService->getBranchConfig($this->branch, 'test_key');

        $this->assertEquals('restaurant_value', $result);
    }

    #[Test]
    public function test_can_update_operating_hours(): void
    {
        $hours = [
            'monday' => ['open' => '09:00', 'close' => '17:00'],
            'tuesday' => ['open' => '09:00', 'close' => '17:00'],
            'wednesday' => ['open' => '09:00', 'close' => '17:00'],
        ];

        $this->configurationService->setRestaurantConfig($this->restaurant, 'operating_hours', $hours);

        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'operating_hours');

        $this->assertEquals($hours, $result);
    }

    #[Test]
    public function test_validates_operating_hours_format(): void
    {
        $this->expectException(\App\Exceptions\BusinessLogicException::class);

        $invalidHours = [
            'monday' => ['invalid_time_format'],
        ];

        $this->configurationService->setRestaurantConfig($this->restaurant, 'operating_hours', $invalidHours);
    }

    #[Test]
    public function test_can_configure_loyalty_program(): void
    {
        $loyaltyConfig = [
            'points_per_currency' => 2,
            'minimum_redemption' => 100,
            'expiry_days' => 365,
            'welcome_bonus' => 50,
        ];

        $this->configurationService->configureLoyaltyProgram($this->restaurant, $loyaltyConfig);

        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'loyalty_points_per_currency');

        $this->assertEquals(2, $result);
    }

    #[Test]
    public function test_validates_loyalty_settings(): void
    {
        $this->expectException(\App\Exceptions\BusinessLogicException::class);

        $invalidConfig = [
            'points_per_currency' => -1, // Invalid negative value
        ];

        $this->configurationService->configureLoyaltyProgram($this->restaurant, $invalidConfig);
    }

    #[Test]
    public function test_validates_spin_wheel_probabilities_sum(): void
    {
        $this->expectException(\App\Exceptions\BusinessLogicException::class);

        $invalidProbabilities = [
            'prize_1' => 0.3,
            'prize_2' => 0.3,
            'prize_3' => 0.3, // Sum = 0.9, should be 1.0
        ];

        $this->configurationService->setRestaurantConfig($this->restaurant, 'loyalty_spin_wheel_probabilities', $invalidProbabilities);
    }

    #[Test]
    public function test_caches_configuration_data(): void
    {
        // Set config
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'test_value');

        // Clear cache manually to ensure fresh state
        cache()->forget("restaurant_config_{$this->restaurant->id}");

        // First call should cache the result
        $result1 = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        // Second call should use cache
        $result2 = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        $this->assertEquals('test_value', $result1);
        $this->assertEquals('test_value', $result2);
    }

    #[Test]
    public function test_clears_cache_when_config_is_updated(): void
    {
        // Set initial config
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'initial_value');

        // Get config to populate cache
        $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        // Update config
        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'updated_value');

        // Get config again - should reflect updated value
        $result = $this->configurationService->getRestaurantConfig($this->restaurant, 'test_key');

        $this->assertEquals('updated_value', $result);
    }

    #[Test]
    public function test_handles_different_data_types(): void
    {
        // Test string
        $this->configurationService->setRestaurantConfig($this->restaurant, 'string_key', 'string_value');
        $this->assertEquals('string_value', $this->configurationService->getRestaurantConfig($this->restaurant, 'string_key'));

        // Test integer
        $this->configurationService->setRestaurantConfig($this->restaurant, 'int_key', 42);
        $this->assertEquals(42, $this->configurationService->getRestaurantConfig($this->restaurant, 'int_key'));

        // Test boolean
        $this->configurationService->setRestaurantConfig($this->restaurant, 'bool_key', true);
        $this->assertTrue($this->configurationService->getRestaurantConfig($this->restaurant, 'bool_key'));

        // Test array
        $this->configurationService->setRestaurantConfig($this->restaurant, 'array_key', ['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $this->configurationService->getRestaurantConfig($this->restaurant, 'array_key'));
    }

    #[Test]
    public function test_validates_config_key_format(): void
    {
        // Arrange
        $invalidKey = 'invalid key with spaces';
        $configValue = 'test_value';

        // Act & Assert
        $this->expectException(\App\Exceptions\BusinessLogicException::class);
        $this->configurationService->setRestaurantConfig($this->restaurant, $invalidKey, $configValue);
    }

    #[Test]
    public function test_validates_config_value_size(): void
    {
        $this->expectException(\App\Exceptions\BusinessLogicException::class);

        $largeValue = str_repeat('a', 65536); // Exceeds 65535 character limit

        $this->configurationService->setRestaurantConfig($this->restaurant, 'large_key', $largeValue);
    }

    #[Test]
    public function test_handles_encrypted_configs_properly(): void
    {
        $this->configurationService->setRestaurantConfig($this->restaurant, 'api_secret', 'secret_value');

        $config = RestaurantConfig::where('restaurant_id', $this->restaurant->id)
            ->where('config_key', 'api_secret')
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->is_encrypted);
        $this->assertNotEquals('secret_value', $config->config_value);

        // Decrypted value should match original
        $decryptedValue = $this->configurationService->getRestaurantConfig($this->restaurant, 'api_secret');
        $this->assertEquals('secret_value', $decryptedValue);
    }

    #[Test]
    public function test_uses_transactions_for_config_updates(): void
    {
        // Mock DB facade to verify transaction usage
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->configurationService->setRestaurantConfig($this->restaurant, 'test_key', 'test_value');

        // If we reach here without exception, transaction was used
        $this->assertTrue(true);
    }
} 