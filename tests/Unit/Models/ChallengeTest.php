<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Challenge;
use App\Models\CustomerChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test challenge creation with all required fields
     */
    public function test_can_create_challenge_with_required_fields(): void
    {
        $challengeData = [
            'name' => 'Weekly Warrior',
            'description' => 'Order 5 times this week',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 5],
            'reward_type' => 'points',
            'reward_value' => 100,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
        ];

        $challenge = Challenge::create($challengeData);

        $this->assertDatabaseHas('challenges', [
            'name' => 'Weekly Warrior',
            'challenge_type' => 'frequency',
            'reward_type' => 'points',
            'reward_value' => 100,
        ]);

        $this->assertEquals('frequency', $challenge->challenge_type);
        $this->assertEquals(['order_count' => 5], $challenge->requirements);
    }

    /**
     * Test challenge type validation
     */
    public function test_challenge_type_must_be_valid(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Challenge::create([
            'name' => 'Invalid Challenge',
            'description' => 'Test',
            'challenge_type' => 'invalid_type',
            'requirements' => [],
            'reward_type' => 'points',
            'reward_value' => 100,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
        ]);
    }

    /**
     * Test isCurrentlyActive method
     */
    public function test_is_currently_active_returns_correct_status(): void
    {
        // Active challenge
        $activeChallenge = Challenge::factory()->create([
            'is_active' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->assertTrue($activeChallenge->isCurrentlyActive());

        // Inactive challenge
        $inactiveChallenge = Challenge::factory()->create([
            'is_active' => false,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->assertFalse($inactiveChallenge->isCurrentlyActive());

        // Expired challenge
        $expiredChallenge = Challenge::factory()->create([
            'is_active' => true,
            'start_date' => now()->subWeek(),
            'end_date' => now()->subDay(),
        ]);

        $this->assertFalse($expiredChallenge->isCurrentlyActive());

        // Future challenge
        $futureChallenge = Challenge::factory()->create([
            'is_active' => true,
            'start_date' => now()->addDay(),
            'end_date' => now()->addWeek(),
        ]);

        $this->assertFalse($futureChallenge->isCurrentlyActive());
    }

    /**
     * Test hasExpired method
     */
    public function test_has_expired_returns_correct_status(): void
    {
        $expiredChallenge = Challenge::factory()->create([
            'end_date' => now()->subDay(),
        ]);

        $activeChallenge = Challenge::factory()->create([
            'end_date' => now()->addDay(),
        ]);

        $this->assertTrue($expiredChallenge->hasExpired());
        $this->assertFalse($activeChallenge->hasExpired());
    }

    /**
     * Test isFull method with max participants
     */
    public function test_is_full_returns_correct_status(): void
    {
        $challenge = Challenge::factory()->create([
            'max_participants' => 2,
        ]);

        // Challenge is not full initially
        $this->assertFalse($challenge->isFull());

        // Add customer challenges up to the limit
        CustomerChallenge::factory()->count(2)->create([
            'challenge_id' => $challenge->id,
            'status' => 'active',
        ]);

        // Refresh the challenge to get updated counts
        $challenge->refresh();
        $this->assertTrue($challenge->isFull());
    }

    /**
     * Test isFull method without max participants
     */
    public function test_is_full_returns_false_when_no_max_participants(): void
    {
        $challenge = Challenge::factory()->create([
            'max_participants' => null,
        ]);

        CustomerChallenge::factory()->count(100)->create([
            'challenge_id' => $challenge->id,
            'status' => 'active',
        ]);

        $this->assertFalse($challenge->isFull());
    }

    /**
     * Test getCompletionRate method
     */
    public function test_get_completion_rate_calculates_correctly(): void
    {
        $challenge = Challenge::factory()->create();

        // No customer challenges
        $this->assertEquals(0.0, $challenge->getCompletionRate());

        // Create customer challenges with different statuses
        CustomerChallenge::factory()->count(3)->create([
            'challenge_id' => $challenge->id,
            'status' => 'completed',
        ]);

        CustomerChallenge::factory()->count(2)->create([
            'challenge_id' => $challenge->id,
            'status' => 'active',
        ]);

        // Refresh to get updated counts
        $challenge->refresh();
        
        // 3 completed out of 5 total = 60%
        $this->assertEquals(60.0, $challenge->getCompletionRate());
    }

    /**
     * Test getAverageCompletionTime method
     */
    public function test_get_average_completion_time_calculates_correctly(): void
    {
        $challenge = Challenge::factory()->create();

        // No completed challenges
        $this->assertNull($challenge->getAverageCompletionTime());

        $baseTime = Carbon::now();

        // Create completed customer challenges with different completion times
        CustomerChallenge::factory()->create([
            'challenge_id' => $challenge->id,
            'status' => 'completed',
            'started_at' => $baseTime,
            'completed_at' => $baseTime->copy()->addDays(2), // 2 days
        ]);

        CustomerChallenge::factory()->create([
            'challenge_id' => $challenge->id,
            'status' => 'completed',
            'started_at' => $baseTime->copy()->addHours(1),
            'completed_at' => $baseTime->copy()->addDays(4), // ~3 days
        ]);

        $challenge->refresh();
        
        // Average should be around 2.5 days
        $averageTime = $challenge->getAverageCompletionTime();
        $this->assertGreaterThan(2.0, $averageTime);
        $this->assertLessThan(3.0, $averageTime);
    }

    /**
     * Test active scope
     */
    public function test_active_scope_returns_only_active_challenges(): void
    {
        Challenge::factory()->create([
            'is_active' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        Challenge::factory()->create([
            'is_active' => false,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        Challenge::factory()->create([
            'is_active' => true,
            'start_date' => now()->addDay(),
            'end_date' => now()->addWeek(),
        ]);

        $activeChallenges = Challenge::active()->get();
        
        $this->assertCount(1, $activeChallenges);
    }

    /**
     * Test challenge type scope
     */
    public function test_of_type_scope_filters_by_challenge_type(): void
    {
        Challenge::factory()->count(2)->create(['challenge_type' => 'frequency']);
        Challenge::factory()->count(3)->create(['challenge_type' => 'variety']);
        Challenge::factory()->create(['challenge_type' => 'value']);

        $frequencyChallenges = Challenge::ofType('frequency')->get();
        $varietyChallenges = Challenge::ofType('variety')->get();

        $this->assertCount(2, $frequencyChallenges);
        $this->assertCount(3, $varietyChallenges);

        foreach ($frequencyChallenges as $challenge) {
            $this->assertEquals('frequency', $challenge->challenge_type);
        }
    }

    /**
     * Test customer challenges relationship
     */
    public function test_customer_challenges_relationship(): void
    {
        $challenge = Challenge::factory()->create();
        
        CustomerChallenge::factory()->count(3)->create([
            'challenge_id' => $challenge->id,
        ]);

        $this->assertCount(3, $challenge->customerChallenges);
        $this->assertInstanceOf(CustomerChallenge::class, $challenge->customerChallenges->first());
    }

    /**
     * Test requirements and metadata casting
     */
    public function test_json_fields_are_properly_cast(): void
    {
        $requirements = ['order_count' => 5, 'min_amount' => 50];
        $metadata = ['difficulty' => 'medium', 'category' => 'weekly'];
        $targetSegments = ['tier' => 'gold', 'region' => 'riyadh'];

        $challenge = Challenge::factory()->create([
            'requirements' => $requirements,
            'metadata' => $metadata,
            'target_segments' => $targetSegments,
        ]);

        $this->assertEquals($requirements, $challenge->requirements);
        $this->assertEquals($metadata, $challenge->metadata);
        $this->assertEquals($targetSegments, $challenge->target_segments);
        $this->assertIsArray($challenge->requirements);
        $this->assertIsArray($challenge->metadata);
        $this->assertIsArray($challenge->target_segments);
    }
}