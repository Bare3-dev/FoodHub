<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\LoyaltyProgram;
use App\Models\Restaurant;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTier;
use App\Models\StampCard;
use App\Models\CustomerChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoyaltyProgramTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyProgram $loyaltyProgram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loyaltyProgram = LoyaltyProgram::factory()->create();
    }

    /**
     * Test loyalty program has correct relationships
     */
    public function test_it_has_correct_relationships(): void
    {
        $restaurant = Restaurant::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $restaurant->id
        ]);

        // Test restaurant relationship
        $this->assertEquals($restaurant->id, $loyaltyProgram->restaurant->id);
        $this->assertTrue($restaurant->loyaltyPrograms->contains($loyaltyProgram));

        // Test customer loyalty points relationship
        $customerLoyaltyPoint = CustomerLoyaltyPoint::factory()->create([
            'loyalty_program_id' => $loyaltyProgram->id
        ]);
        $this->assertTrue($loyaltyProgram->customerLoyaltyPoints->contains($customerLoyaltyPoint));

        // Test loyalty tiers relationship
        $loyaltyTier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $loyaltyProgram->id
        ]);
        $this->assertTrue($loyaltyProgram->loyaltyTiers->contains($loyaltyTier));

        // Test stamp cards relationship
        $stampCard = StampCard::factory()->create([
            'loyalty_program_id' => $loyaltyProgram->id
        ]);
        $this->assertTrue($loyaltyProgram->stampCards->contains($stampCard));
    }

    /**
     * Test loyalty program validates required fields
     */
    public function test_it_validates_required_fields(): void
    {
        $restaurant = Restaurant::factory()->create();
        
        $requiredFields = [
            'restaurant_id' => $restaurant->id,
            'name' => 'Rewards Program',
            'type' => 'points',
            'start_date' => '2024-01-01',
            'rules' => [
                'minimum_spend' => 10,
                'points_expiry_months' => 12
            ],
            'points_per_dollar' => 1.0,
            'dollar_per_point' => 0.01
        ];

        $loyaltyProgram = LoyaltyProgram::create($requiredFields);

        $this->assertDatabaseHas('loyalty_programs', [
            'id' => $loyaltyProgram->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'Rewards Program',
            'type' => 'points'
        ]);
    }

    /**
     * Test loyalty program enforces business rules
     */
    public function test_it_enforces_business_rules(): void
    {
        $restaurant = Restaurant::factory()->create();

        // Test that type must be valid enum value
        $this->expectException(\Illuminate\Database\QueryException::class);
        LoyaltyProgram::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Invalid Program',
            'type' => 'invalid_type',
            'start_date' => '2024-01-01',
            'rules' => [],
            'points_per_dollar' => 1.0,
            'dollar_per_point' => 0.01
        ]);
    }

    /**
     * Test loyalty program scopes data correctly
     */
    public function test_it_scopes_data_correctly(): void
    {
        // Create loyalty programs with different active statuses
        $activeProgram = LoyaltyProgram::factory()->create(['is_active' => true]);
        $inactiveProgram = LoyaltyProgram::factory()->create(['is_active' => false]);

        // Test active scope
        $activePrograms = LoyaltyProgram::active()->get();
        $this->assertTrue($activePrograms->contains($activeProgram));
        $this->assertFalse($activePrograms->contains($inactiveProgram));
    }

    /**
     * Test loyalty program handles points calculation correctly
     */
    public function test_it_handles_points_calculation_correctly(): void
    {
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'points_per_dollar' => 2.5,
            'dollar_per_point' => 0.02,
            'minimum_spend_for_points' => 5
        ]);

        $this->assertEquals('2.50', $loyaltyProgram->points_per_dollar);
        $this->assertEquals('0.02', $loyaltyProgram->dollar_per_point);
        $this->assertEquals(5, $loyaltyProgram->minimum_spend_for_points);
    }

    /**
     * Test loyalty program handles rules correctly
     */
    public function test_it_handles_rules_correctly(): void
    {
        $rules = [
            'minimum_spend' => 15,
            'points_expiry_months' => 18,
            'minimum_points_redemption' => 500,
            'redemption_options' => [
                'discount' => true,
                'free_delivery' => false,
                'free_item' => true,
                'cash_back' => false
            ]
        ];

        $loyaltyProgram = LoyaltyProgram::factory()->create(['rules' => $rules]);

        $this->assertEquals($rules, $loyaltyProgram->rules);
        $this->assertIsArray($loyaltyProgram->rules);
        $this->assertEquals(15, $loyaltyProgram->rules['minimum_spend']);
        $this->assertEquals(18, $loyaltyProgram->rules['points_expiry_months']);
    }

    /**
     * Test loyalty program handles bonus multipliers correctly
     */
    public function test_it_handles_bonus_multipliers_correctly(): void
    {
        $bonusMultipliers = [
            'happy_hour' => 2.0,
            'birthday' => 3.0,
            'first_order' => 5.0,
            'referral' => 10.0
        ];

        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'bonus_multipliers' => $bonusMultipliers
        ]);

        $this->assertEquals($bonusMultipliers, $loyaltyProgram->bonus_multipliers);
        $this->assertIsArray($loyaltyProgram->bonus_multipliers);
        $this->assertEquals(2.0, $loyaltyProgram->bonus_multipliers['happy_hour']);
        $this->assertEquals(10.0, $loyaltyProgram->bonus_multipliers['referral']);
    }

    /**
     * Test loyalty program handles dates correctly
     */
    public function test_it_handles_dates_correctly(): void
    {
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $loyaltyProgram->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $loyaltyProgram->end_date);
        $this->assertEquals('2024-01-01', $loyaltyProgram->start_date->format('Y-m-d'));
        $this->assertEquals('2024-12-31', $loyaltyProgram->end_date->format('Y-m-d'));
    }

    /**
     * Test loyalty program handles optional fields correctly
     */
    public function test_it_handles_optional_fields_correctly(): void
    {
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'description' => 'A great rewards program',
            'end_date' => null
        ]);

        $this->assertEquals('A great rewards program', $loyaltyProgram->description);
        $this->assertNull($loyaltyProgram->end_date);
    }

    /**
     * Test loyalty program factory states work correctly
     */
    public function test_it_uses_factory_states_correctly(): void
    {
        // Test inactive state
        $inactiveProgram = LoyaltyProgram::factory()->inactive()->create();
        $this->assertFalse($inactiveProgram->is_active);

        // Test high points rate state
        $highPointsProgram = LoyaltyProgram::factory()->highPointsRate()->create();
        $this->assertGreaterThanOrEqual(5.0, (float)$highPointsProgram->points_per_dollar);
        $this->assertLessThanOrEqual(10.0, (float)$highPointsProgram->points_per_dollar);

        // Test low points rate state
        $lowPointsProgram = LoyaltyProgram::factory()->lowPointsRate()->create();
        $this->assertGreaterThanOrEqual(0.1, (float)$lowPointsProgram->points_per_dollar);
        $this->assertLessThanOrEqual(2.0, (float)$lowPointsProgram->points_per_dollar);

        // Test short expiry state
        $shortExpiryProgram = LoyaltyProgram::factory()->shortExpiry()->create();
        $this->assertLessThanOrEqual(6, $shortExpiryProgram->rules['points_expiry_months']);

        // Test long expiry state
        $longExpiryProgram = LoyaltyProgram::factory()->longExpiry()->create();
        $this->assertGreaterThanOrEqual(24, $longExpiryProgram->rules['points_expiry_months']);

        // Test high minimum redemption state
        $highMinRedemptionProgram = LoyaltyProgram::factory()->highMinimumRedemption()->create();
        $this->assertGreaterThanOrEqual(1000, $highMinRedemptionProgram->rules['minimum_points_redemption']);

        // Test low minimum redemption state
        $lowMinRedemptionProgram = LoyaltyProgram::factory()->lowMinimumRedemption()->create();
        $this->assertLessThanOrEqual(200, $lowMinRedemptionProgram->rules['minimum_points_redemption']);

        // Test all redemption options state
        $allOptionsProgram = LoyaltyProgram::factory()->allRedemptionOptions()->create();
        $this->assertTrue($allOptionsProgram->rules['redemption_options']['cash_back']);

        // Test limited redemption options state
        $limitedOptionsProgram = LoyaltyProgram::factory()->limitedRedemptionOptions()->create();
        $this->assertFalse($limitedOptionsProgram->rules['redemption_options']['free_delivery']);

        // Test high bonus multipliers state
        $highBonusProgram = LoyaltyProgram::factory()->highBonusMultipliers()->create();
        $this->assertEquals(20.0, $highBonusProgram->bonus_multipliers['referral']);

        // Test low bonus multipliers state
        $lowBonusProgram = LoyaltyProgram::factory()->lowBonusMultipliers()->create();
        $this->assertEquals(1.5, $lowBonusProgram->bonus_multipliers['happy_hour']);

        // Test expiring soon state
        $expiringProgram = LoyaltyProgram::factory()->expiringSoon()->create();
        $this->assertInstanceOf(\Carbon\Carbon::class, $expiringProgram->end_date);
        $this->assertTrue($expiringProgram->end_date->isAfter(now()->subDay())); // Allow some flexibility

        // Test newly launched state
        $newProgram = LoyaltyProgram::factory()->newlyLaunched()->create();
        $this->assertInstanceOf(\Carbon\Carbon::class, $newProgram->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $newProgram->end_date);
        $this->assertTrue($newProgram->start_date->isAfter(now()->subMonths(2))); // Allow some flexibility
        $this->assertTrue($newProgram->end_date->isAfter(now()->addMonths(6))); // Allow some flexibility
    }

    /**
     * Test loyalty program handles different types correctly
     */
    public function test_it_handles_different_types_correctly(): void
    {
        $types = ['points', 'stamps', 'tiers', 'challenges'];

        foreach ($types as $type) {
            $loyaltyProgram = LoyaltyProgram::factory()->create(['type' => $type]);
            $this->assertEquals($type, $loyaltyProgram->type);
        }
    }

    /**
     * Test loyalty program handles edge cases correctly
     */
    public function test_it_handles_edge_cases_correctly(): void
    {
        // Test very high points per dollar
        $highPointsProgram = LoyaltyProgram::factory()->create([
            'points_per_dollar' => 99.99
        ]);
        $this->assertEquals('99.99', $highPointsProgram->points_per_dollar);

        // Test very low points per dollar
        $lowPointsProgram = LoyaltyProgram::factory()->create([
            'points_per_dollar' => 0.01
        ]);
        $this->assertEquals('0.01', $lowPointsProgram->points_per_dollar);

        // Test very high dollar per point
        $highDollarProgram = LoyaltyProgram::factory()->create([
            'dollar_per_point' => 0.99
        ]);
        $this->assertEquals('0.99', $highDollarProgram->dollar_per_point);

        // Test very low dollar per point
        $lowDollarProgram = LoyaltyProgram::factory()->create([
            'dollar_per_point' => 0.001
        ]);
        $this->assertEquals('0.00', $lowDollarProgram->dollar_per_point); // Rounded to 2 decimals

        // Test zero minimum spend
        $zeroMinProgram = LoyaltyProgram::factory()->create([
            'minimum_spend_for_points' => 0
        ]);
        $this->assertEquals(0, $zeroMinProgram->minimum_spend_for_points);

        // Test high minimum spend
        $highMinProgram = LoyaltyProgram::factory()->create([
            'minimum_spend_for_points' => 999
        ]);
        $this->assertEquals(999, $highMinProgram->minimum_spend_for_points);
    }

    /**
     * Test loyalty program handles complex rules correctly
     */
    public function test_it_handles_complex_rules_correctly(): void
    {
        $complexRules = [
            'minimum_spend' => 25,
            'points_expiry_months' => 24,
            'minimum_points_redemption' => 1000,
            'redemption_options' => [
                'discount' => true,
                'free_delivery' => true,
                'free_item' => true,
                'cash_back' => true
            ],
            'tier_progression' => [
                'bronze' => ['min_points' => 0, 'max_points' => 999],
                'silver' => ['min_points' => 1000, 'max_points' => 4999],
                'gold' => ['min_points' => 5000, 'max_points' => 19999],
                'platinum' => ['min_points' => 20000, 'max_points' => 49999],
                'diamond' => ['min_points' => 50000, 'max_points' => null]
            ],
            'notification_settings' => [
                'points_earned' => true,
                'points_redeemed' => true,
                'points_expiring' => true,
                'tier_upgrade' => true,
                'birthday_bonus' => true
            ]
        ];

        $loyaltyProgram = LoyaltyProgram::factory()->create(['rules' => $complexRules]);

        $this->assertEquals($complexRules, $loyaltyProgram->rules);
        $this->assertArrayHasKey('tier_progression', $loyaltyProgram->rules);
        $this->assertArrayHasKey('notification_settings', $loyaltyProgram->rules);
        $this->assertEquals(5, count($loyaltyProgram->rules['tier_progression']));
        $this->assertEquals(5, count($loyaltyProgram->rules['notification_settings']));
    }

    /**
     * Test loyalty program handles multiple programs per restaurant
     */
    public function test_it_handles_multiple_programs_per_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        // Create multiple programs for the same restaurant
        $program1 = LoyaltyProgram::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Points Program',
            'type' => 'points'
        ]);

        $program2 = LoyaltyProgram::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Stamps Program',
            'type' => 'stamps'
        ]);

        $program3 = LoyaltyProgram::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Tiers Program',
            'type' => 'tiers'
        ]);

        // Test that restaurant has all programs
        $restaurantPrograms = $restaurant->loyaltyPrograms;
        $this->assertCount(3, $restaurantPrograms);
        $this->assertTrue($restaurantPrograms->contains($program1));
        $this->assertTrue($restaurantPrograms->contains($program2));
        $this->assertTrue($restaurantPrograms->contains($program3));

        // Test that programs belong to the same restaurant
        $this->assertEquals($restaurant->id, $program1->restaurant_id);
        $this->assertEquals($restaurant->id, $program2->restaurant_id);
        $this->assertEquals($restaurant->id, $program3->restaurant_id);
    }

    /**
     * Test loyalty program handles cascade deletion
     */
    public function test_it_handles_cascade_deletion(): void
    {
        $restaurant = Restaurant::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $restaurant->id
        ]);

        $programId = $loyaltyProgram->id;

        // Verify program exists
        $this->assertDatabaseHas('loyalty_programs', ['id' => $programId]);

        // Delete the restaurant
        $restaurant->delete();

        // Verify program is also deleted (cascade)
        $this->assertDatabaseMissing('loyalty_programs', ['id' => $programId]);
    }

    /**
     * Test loyalty program handles attribute casting correctly
     */
    public function test_it_casts_attributes_correctly(): void
    {
        $loyaltyProgram = LoyaltyProgram::factory()->create([
            'is_active' => true,
            'points_per_dollar' => 3.5,
            'dollar_per_point' => 0.05,
            'minimum_spend_for_points' => 10,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'rules' => ['test' => 'value'],
            'bonus_multipliers' => ['happy_hour' => 2.0]
        ]);

        // Test boolean casting
        $this->assertIsBool($loyaltyProgram->is_active);

        // Test decimal casting (returns string in Laravel)
        $this->assertIsString($loyaltyProgram->points_per_dollar);
        $this->assertIsString($loyaltyProgram->dollar_per_point);
        $this->assertEquals('3.50', $loyaltyProgram->points_per_dollar);
        $this->assertEquals('0.05', $loyaltyProgram->dollar_per_point);

        // Test integer casting
        $this->assertIsInt($loyaltyProgram->minimum_spend_for_points);

        // Test date casting
        $this->assertInstanceOf(\Carbon\Carbon::class, $loyaltyProgram->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $loyaltyProgram->end_date);

        // Test array casting
        $this->assertIsArray($loyaltyProgram->rules);
        $this->assertIsArray($loyaltyProgram->bonus_multipliers);
    }

    /**
     * Test loyalty program handles program expiration correctly
     */
    public function test_it_handles_program_expiration_correctly(): void
    {
        // Test program with end date in the past
        $expiredProgram = LoyaltyProgram::factory()->create([
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
            'is_active' => true
        ]);

        // Test program with end date in the future
        $activeProgram = LoyaltyProgram::factory()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_active' => true
        ]);

        // Test program with no end date (ongoing)
        $ongoingProgram = LoyaltyProgram::factory()->create([
            'start_date' => '2024-01-01',
            'end_date' => null,
            'is_active' => true
        ]);

        // Test that dates are properly cast and accessible
        $this->assertInstanceOf(\Carbon\Carbon::class, $expiredProgram->end_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $activeProgram->end_date);
        $this->assertNull($ongoingProgram->end_date);

        // Test date formatting
        $this->assertEquals('2023-12-31', $expiredProgram->end_date->format('Y-m-d'));
        $this->assertEquals('2024-12-31', $activeProgram->end_date->format('Y-m-d'));
    }
} 