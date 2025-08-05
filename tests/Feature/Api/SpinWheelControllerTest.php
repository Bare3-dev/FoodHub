<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\SpinWheel;
use App\Models\SpinWheelPrize;
use App\Models\SpinResult;
use App\Services\SpinWheelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SpinWheelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected LoyaltyProgram $loyaltyProgram;
    protected LoyaltyTier $bronzeTier;
    protected LoyaltyTier $silverTier;
    protected LoyaltyTier $goldTier;
    protected SpinWheel $spinWheel;
    protected SpinWheelPrize $discountPrize;
    protected SpinWheelPrize $pointsPrize;
    protected CustomerLoyaltyPoint $customerLoyaltyPoint;

    protected function setUp(): void
    {
        parent::setUp();

        // Create loyalty program and tiers
        $this->loyaltyProgram = LoyaltyProgram::factory()->create([
            'name' => 'FoodHub Rewards',
            'is_active' => true,
        ]);

        $this->bronzeTier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'name' => 'Bronze',
            'display_name' => 'Bronze Tier',
            'min_points_required' => 0,
            'max_points_capacity' => 999,
            'points_multiplier' => 1.0,
            'discount_percentage' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->silverTier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'name' => 'Silver',
            'display_name' => 'Silver Tier',
            'min_points_required' => 1000,
            'max_points_capacity' => 2499,
            'points_multiplier' => 1.2,
            'discount_percentage' => 5,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->goldTier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'name' => 'Gold',
            'display_name' => 'Gold Tier',
            'min_points_required' => 2500,
            'max_points_capacity' => 4999,
            'points_multiplier' => 1.5,
            'discount_percentage' => 10,
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // Create customer
        $this->customer = Customer::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create customer loyalty points
        $this->customerLoyaltyPoint = CustomerLoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'loyalty_tier_id' => $this->bronzeTier->id,
            'current_points' => 500,
            'total_points_earned' => 500,
            'is_active' => true,
        ]);

        // Create spin wheel
        $this->spinWheel = SpinWheel::create([
            'name' => 'Test Spin Wheel',
            'description' => 'Test spin wheel for testing',
            'is_active' => true,
            'daily_free_spins_base' => 1,
            'max_daily_spins' => 5,
            'spin_cost_points' => 100.00,
            'tier_spin_multipliers' => [
                1 => 1.0,
                2 => 1.5,
                3 => 2.0,
            ],
            'tier_probability_boost' => [
                1 => 1.0,
                2 => 1.2,
                3 => 1.5,
            ],
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        // Create prizes
        $this->discountPrize = SpinWheelPrize::create([
            'spin_wheel_id' => $this->spinWheel->id,
            'name' => '10% Off Next Order',
            'description' => 'Get 10% off your next order',
            'type' => 'discount',
            'value' => 10.00,
            'value_type' => 'percentage',
            'probability' => 0.25,
            'is_active' => true,
        ]);

        $this->pointsPrize = SpinWheelPrize::create([
            'spin_wheel_id' => $this->spinWheel->id,
            'name' => '50 Bonus Points',
            'description' => 'Earn 50 bonus loyalty points',
            'type' => 'bonus_points',
            'value' => 50.00,
            'value_type' => 'points',
            'probability' => 0.20,
            'is_active' => true,
        ]);
    }

    public function test_it_gets_spin_wheel_status()
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/spin-wheel/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'can_spin',
                    'available_spins',
                    'free_spins_remaining',
                    'paid_spins_remaining',
                    'daily_spins_used',
                    'max_daily_spins',
                    'spin_cost_points',
                    'total_spins_used',
                ],
            ]);
    }

    public function test_it_spins_the_wheel()
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/spin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'spin_result' => [
                        'id',
                        'customer_id',
                        'spin_wheel_id',
                        'spin_type',
                        'prize' => [
                            'name',
                            'type',
                            'value',
                            'display_value',
                            'description',
                        ],
                        'status' => [
                            'is_redeemed',
                            'can_be_redeemed',
                            'is_expired',
                        ],
                    ],
                    'prize_won' => [
                        'name',
                        'type',
                        'value',
                        'display_value',
                        'description',
                    ],
                ],
            ]);

        // Verify spin result was created
        $this->assertDatabaseHas('spin_results', [
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
        ]);
    }

    public function test_it_buys_spins_with_loyalty_points()
    {
        // Update customer to have enough points
        $this->customerLoyaltyPoint->update(['current_points' => 500]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/buy-spins', [
                'quantity' => 2,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'spins_purchased',
                    'updated_status',
                ],
            ]);

        // Verify points were deducted
        $this->customerLoyaltyPoint->refresh();
        $this->assertEquals(300, $this->customerLoyaltyPoint->current_points); // 500 - (2 * 100)
    }

    public function test_it_fails_to_buy_spins_with_insufficient_points()
    {
        // Update customer to have insufficient points
        $this->customerLoyaltyPoint->update(['current_points' => 50]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/buy-spins', [
                'quantity' => 2,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to buy spins. Insufficient loyalty points or no active spin wheel.',
            ]);
    }

    public function test_it_gets_redeemable_prizes()
    {
        // Create a spin result
        SpinResult::create([
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
            'spin_wheel_prize_id' => $this->discountPrize->id,
            'spin_type' => 'free',
            'prize_value' => $this->discountPrize->value,
            'prize_type' => $this->discountPrize->type,
            'prize_name' => $this->discountPrize->name,
            'prize_description' => $this->discountPrize->description,
            'is_redeemed' => false,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/spin-wheel/redeemable-prizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'prizes',
                    'total_count',
                ],
            ]);
    }

    public function test_it_redeems_a_prize()
    {
        // Create a spin result
        $spinResult = SpinResult::create([
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
            'spin_wheel_prize_id' => $this->discountPrize->id,
            'spin_type' => 'free',
            'prize_value' => $this->discountPrize->value,
            'prize_type' => $this->discountPrize->type,
            'prize_name' => $this->discountPrize->name,
            'prize_description' => $this->discountPrize->description,
            'is_redeemed' => false,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/redeem-prize', [
                'spin_result_id' => $spinResult->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize redeemed successfully',
            ]);

        // Verify spin result was marked as redeemed
        $spinResult->refresh();
        $this->assertTrue($spinResult->is_redeemed);
    }

    public function test_it_gets_spin_wheel_configuration()
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/spin-wheel/configuration');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'wheel' => [
                        'id',
                        'name',
                        'description',
                        'daily_free_spins_base',
                        'max_daily_spins',
                        'spin_cost_points',
                    ],
                    'prizes',
                ],
            ]);
    }

    public function test_it_fails_to_spin_when_no_spins_available()
    {
        // Create a customer spin record with no available spins
        \App\Models\CustomerSpin::create([
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
            'free_spins_remaining' => 0,
            'paid_spins_remaining' => 0,
            'total_spins_used' => 0,
            'daily_spins_used' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/spin');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot spin the wheel at this time',
            ]);
    }

    public function test_it_fails_to_spin_when_daily_limit_reached()
    {
        // Create a customer spin record with daily limit reached
        \App\Models\CustomerSpin::create([
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
            'free_spins_remaining' => 1,
            'paid_spins_remaining' => 0,
            'total_spins_used' => 0,
            'daily_spins_used' => 5, // Max daily spins
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/spin');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot spin the wheel at this time',
            ]);
    }

    public function test_it_fails_to_redeem_expired_prize()
    {
        // Create an expired spin result
        $spinResult = SpinResult::create([
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
            'spin_wheel_prize_id' => $this->discountPrize->id,
            'spin_type' => 'free',
            'prize_value' => $this->discountPrize->value,
            'prize_type' => $this->discountPrize->type,
            'prize_name' => $this->discountPrize->name,
            'prize_description' => $this->discountPrize->description,
            'is_redeemed' => false,
            'expires_at' => now()->subDays(1), // Expired
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/redeem-prize', [
                'spin_result_id' => $spinResult->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to redeem prize. Prize may be expired or already redeemed.',
            ]);
    }

    public function test_it_fails_to_redeem_already_redeemed_prize()
    {
        // Create an already redeemed spin result
        $spinResult = SpinResult::create([
            'customer_id' => $this->customer->id,
            'spin_wheel_id' => $this->spinWheel->id,
            'spin_wheel_prize_id' => $this->discountPrize->id,
            'spin_type' => 'free',
            'prize_value' => $this->discountPrize->value,
            'prize_type' => $this->discountPrize->type,
            'prize_name' => $this->discountPrize->name,
            'prize_description' => $this->discountPrize->description,
            'is_redeemed' => true, // Already redeemed
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/redeem-prize', [
                'spin_result_id' => $spinResult->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to redeem prize. Prize may be expired or already redeemed.',
            ]);
    }

    public function test_it_validates_buy_spins_request()
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/buy-spins', [
                'quantity' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_it_validates_redeem_prize_request()
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/spin-wheel/redeem-prize', [
                'spin_result_id' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spin_result_id']);
    }
} 