<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Restaurant;
use App\Models\RestaurantConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RestaurantConfigTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();
    }

    #[Test]
    public function it_can_create_restaurant_config()
    {
        $config = RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        $this->assertDatabaseHas('restaurant_configs', [
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
            'config_value' => 'test_value',
            'data_type' => 'string',
        ]);

        $this->assertEquals($this->restaurant->id, $config->restaurant_id);
        $this->assertEquals('test_key', $config->config_key);
        $this->assertEquals('test_value', $config->config_value);
    }

    #[Test]
    public function it_has_restaurant_relationship()
    {
        $config = RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->assertInstanceOf(Restaurant::class, $config->restaurant);
        $this->assertEquals($this->restaurant->id, $config->restaurant->id);
    }

    #[Test]
    public function it_casts_boolean_fields()
    {
        $config = RestaurantConfig::factory()->create([
            'is_encrypted' => true,
            'is_sensitive' => true,
        ]);

        $this->assertTrue($config->is_encrypted);
        $this->assertTrue($config->is_sensitive);
    }

    #[Test]
    public function it_casts_datetime_fields()
    {
        $config = RestaurantConfig::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $config->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $config->updated_at);
    }

    #[Test]
    public function it_can_scope_non_sensitive_configs()
    {
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_sensitive' => false,
        ]);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_sensitive' => true,
        ]);

        $nonSensitiveConfigs = RestaurantConfig::nonSensitive()->get();

        $this->assertEquals(1, $nonSensitiveConfigs->count());
        $this->assertFalse($nonSensitiveConfigs->first()->is_sensitive);
    }

    #[Test]
    public function it_can_scope_sensitive_configs()
    {
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_sensitive' => false,
        ]);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_sensitive' => true,
        ]);

        $sensitiveConfigs = RestaurantConfig::sensitive()->get();

        $this->assertEquals(1, $sensitiveConfigs->count());
        $this->assertTrue($sensitiveConfigs->first()->is_sensitive);
    }

    #[Test]
    public function it_can_scope_encrypted_configs()
    {
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_encrypted' => false,
        ]);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_encrypted' => true,
        ]);

        $encryptedConfigs = RestaurantConfig::encrypted()->get();

        $this->assertEquals(1, $encryptedConfigs->count());
        $this->assertTrue($encryptedConfigs->first()->is_encrypted);
    }

    #[Test]
    public function it_can_scope_non_encrypted_configs()
    {
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_encrypted' => false,
        ]);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_encrypted' => true,
        ]);

        $nonEncryptedConfigs = RestaurantConfig::nonEncrypted()->get();

        $this->assertEquals(1, $nonEncryptedConfigs->count());
        $this->assertFalse($nonEncryptedConfigs->first()->is_encrypted);
    }

    #[Test]
    public function it_has_unique_constraint_on_restaurant_and_key()
    {
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'test_key',
        ]);
    }

    #[Test]
    public function it_can_have_different_keys_for_same_restaurant()
    {
        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'key1',
        ]);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'key2',
        ]);

        $this->assertDatabaseCount('restaurant_configs', 2);
    }

    #[Test]
    public function it_can_have_same_key_for_different_restaurants()
    {
        $restaurant2 = Restaurant::factory()->create();

        RestaurantConfig::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'config_key' => 'same_key',
        ]);

        RestaurantConfig::factory()->create([
            'restaurant_id' => $restaurant2->id,
            'config_key' => 'same_key',
        ]);

        $this->assertDatabaseCount('restaurant_configs', 2);
    }

    #[Test]
    public function it_supports_different_data_types()
    {
        $stringConfig = RestaurantConfig::factory()->create([
            'data_type' => 'string',
            'config_value' => 'test_string',
        ]);

        $integerConfig = RestaurantConfig::factory()->create([
            'data_type' => 'integer',
            'config_value' => '42',
        ]);

        $floatConfig = RestaurantConfig::factory()->create([
            'data_type' => 'float',
            'config_value' => '3.14',
        ]);

        $booleanConfig = RestaurantConfig::factory()->create([
            'data_type' => 'boolean',
            'config_value' => '1',
        ]);

        $arrayConfig = RestaurantConfig::factory()->create([
            'data_type' => 'array',
            'config_value' => json_encode(['key' => 'value']),
        ]);

        $this->assertEquals('string', $stringConfig->data_type);
        $this->assertEquals('integer', $integerConfig->data_type);
        $this->assertEquals('float', $floatConfig->data_type);
        $this->assertEquals('boolean', $booleanConfig->data_type);
        $this->assertEquals('array', $arrayConfig->data_type);
    }

    #[Test]
    public function it_can_be_created_with_factory_states()
    {
        $encryptedConfig = RestaurantConfig::factory()->encrypted()->create();
        $sensitiveConfig = RestaurantConfig::factory()->sensitive()->create();

        $this->assertTrue($encryptedConfig->is_encrypted);
        $this->assertTrue($sensitiveConfig->is_sensitive);
        $this->assertTrue($sensitiveConfig->is_encrypted); // Sensitive configs are also encrypted
    }

    #[Test]
    public function it_can_be_created_with_specific_config_types()
    {
        $loyaltyConfig = RestaurantConfig::factory()->loyaltyPointsPerCurrency()->create();
        $operatingHoursConfig = RestaurantConfig::factory()->operatingHours()->create();

        $this->assertEquals('loyalty_points_per_currency', $loyaltyConfig->config_key);
        $this->assertEquals('operating_hours', $operatingHoursConfig->config_key);
        $this->assertEquals('integer', $loyaltyConfig->data_type);
        $this->assertEquals('array', $operatingHoursConfig->data_type);
    }
} 