<?php

namespace Tests\Unit\Models;

use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use App\Models\ChallengeProgressLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class CustomerChallengeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test customer challenge creation
     */
    public function test_can_create_customer_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $customer = Customer::factory()->create();

        $customerChallenge = CustomerChallenge::create([
            'customer_id' => $customer->id,
            'challenge_id' => $challenge->id,
            'assigned_at' => now(),
            'progress_target' => 5.0,
            'expires_at' => now()->addWeek(),
            'status' => 'assigned', // Explicitly set status
        ]);

        $this->assertDatabaseHas('customer_challenges', [
            'customer_id' => $customer->id,
            'challenge_id' => $challenge->id,
            'status' => 'assigned',
            'progress_current' => 0,
            'progress_percentage' => 0,
        ]);

        $this->assertEquals('assigned', $customerChallenge->status);
        $this->assertEquals(0, $customerChallenge->progress_percentage);
    }

    /**
     * Test isActive method
     */
    public function test_is_active_returns_correct_status(): void
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $customer3 = Customer::factory()->create();
        $challenge1 = Challenge::factory()->create();
        $challenge2 = Challenge::factory()->create();
        $challenge3 = Challenge::factory()->create();

        // Active challenge
        $activeChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $customer1->id,
            'challenge_id' => $challenge1->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($activeChallenge->isActive());

        // Completed challenge
        $completedChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $customer2->id,
            'challenge_id' => $challenge2->id,
            'status' => 'completed',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($completedChallenge->isActive());

        // Expired challenge
        $expiredChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $customer3->id,
            'challenge_id' => $challenge3->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($expiredChallenge->isActive());
    }

    /**
     * Test updateProgress method
     */
    public function test_update_progress_calculates_percentage_correctly(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'status' => 'assigned',
            'progress_current' => 0,
            'progress_target' => 10,
        ]);

        // Update to 30% progress
        $customerChallenge->updateProgress(3);

        $this->assertEquals(3, $customerChallenge->progress_current);
        $this->assertEquals(30.0, $customerChallenge->progress_percentage);
        $this->assertEquals('active', $customerChallenge->status);
        $this->assertNotNull($customerChallenge->started_at);
    }

    /**
     * Test progress completion
     */
    public function test_update_progress_marks_as_completed_when_target_reached(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'status' => 'active',
            'progress_current' => 8,
            'progress_target' => 10,
            'started_at' => now()->subDays(2),
        ]);

        // Complete the challenge
        $customerChallenge->updateProgress(10);

        $this->assertEquals(10, $customerChallenge->progress_current);
        $this->assertEquals(100.0, $customerChallenge->progress_percentage);
        $this->assertEquals('completed', $customerChallenge->status);
        $this->assertNotNull($customerChallenge->completed_at);
    }

    /**
     * Test progress doesn't exceed target
     */
    public function test_update_progress_caps_at_target(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_current' => 8,
            'progress_target' => 10,
        ]);

        // Try to set progress beyond target
        $customerChallenge->updateProgress(15);

        $this->assertEquals(10, $customerChallenge->progress_current);
        $this->assertEquals(100.0, $customerChallenge->progress_percentage);
    }

    /**
     * Test calculateProgressPercentage method
     */
    public function test_calculate_progress_percentage(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_current' => 7.5,
            'progress_target' => 10,
        ]);

        $percentage = $customerChallenge->calculateProgressPercentage();
        
        $this->assertEquals(75.0, $percentage);
    }

    /**
     * Test calculateProgressPercentage with zero target
     */
    public function test_calculate_progress_percentage_with_zero_target(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_current' => 5,
            'progress_target' => 0,
        ]);

        $percentage = $customerChallenge->calculateProgressPercentage();
        
        $this->assertEquals(0.0, $percentage);
    }

    /**
     * Test getDaysRemaining method
     */
    public function test_get_days_remaining(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'expires_at' => now()->addDays(5),
        ]);

        $daysRemaining = $customerChallenge->getDaysRemaining();
        
        // Allow for small time differences during test execution
        $this->assertGreaterThanOrEqual(4, $daysRemaining);
        $this->assertLessThanOrEqual(5, $daysRemaining);
    }

    /**
     * Test getDaysRemaining with no expiry
     */
    public function test_get_days_remaining_with_no_expiry(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'expires_at' => null,
        ]);

        $daysRemaining = $customerChallenge->getDaysRemaining();
        
        $this->assertNull($daysRemaining);
    }

    /**
     * Test isCloseToCompletion method
     */
    public function test_is_close_to_completion(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_current' => 8.5,
            'progress_target' => 10,
        ]);

        $customerChallenge->progress_percentage = $customerChallenge->calculateProgressPercentage();

        $this->assertTrue($customerChallenge->isCloseToCompletion());

        $customerChallenge->progress_current = 7;
        $customerChallenge->progress_percentage = $customerChallenge->calculateProgressPercentage();

        $this->assertFalse($customerChallenge->isCloseToCompletion());
    }

    /**
     * Test expiresSoon method
     */
    public function test_expires_soon(): void
    {
        // Use a fixed time to avoid timing issues
        $fixedTime = Carbon::create(2024, 1, 1, 12, 0, 0);
        
        // Mock the now() function to return our fixed time
        Carbon::setTestNow($fixedTime);
        
        // Test with a challenge that expires in 12 hours (should return true)
        $expiringSoon = CustomerChallenge::factory()->create([
            'expires_at' => $fixedTime->copy()->addHours(12),
        ]);

        // Verify the expiry time is actually in the future
        $this->assertTrue($expiringSoon->expires_at->isFuture());
        
        // Test the expiresSoon method directly without relying on exact timing
        $this->assertTrue($expiringSoon->expiresSoon());

        // Test with a challenge that expires in 2 days (should return false)
        $notExpiringSoon = CustomerChallenge::factory()->create([
            'expires_at' => $fixedTime->copy()->addDays(2),
        ]);

        $this->assertFalse($notExpiringSoon->expiresSoon());

        // Test with no expiry date (should return false)
        $noExpiry = CustomerChallenge::factory()->create([
            'expires_at' => null,
        ]);

        $this->assertFalse($noExpiry->expiresSoon());
        
        // Reset the test time
        Carbon::setTestNow();
    }

    /**
     * Test getMilestoneLevel method
     */
    public function test_get_milestone_level(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_target' => 10,
        ]);

        // Test different progress levels
        $testCases = [
            [2.5, '25%'],  // 25% of 10 = 2.5
            [5, '50%'],     // 50% of 10 = 5
            [7.5, '75%'],   // 75% of 10 = 7.5
            [10, 'completed'], // 100% of 10 = 10
            [1, null],      // 10% of 10 = 1 (below 25% threshold)
        ];

        foreach ($testCases as [$progress, $expectedMilestone]) {
            $customerChallenge->progress_current = $progress;
            $customerChallenge->progress_percentage = $customerChallenge->calculateProgressPercentage();
            $customerChallenge->save();
            
            $this->assertEquals($expectedMilestone, $customerChallenge->getMilestoneLevel());
        }
    }

    /**
     * Test checkMilestoneReached method
     */
    public function test_check_milestone_reached(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_target' => 10,
        ]);

        // Progress from 20% to 60% should trigger 50% milestone
        $customerChallenge->progress_percentage = 20.0;
        $customerChallenge->save();
        
        $customerChallenge->progress_percentage = 60.0;
        $customerChallenge->save();
        
        $milestone = $customerChallenge->checkMilestoneReached(20.0);
        $this->assertEquals('50%', $milestone);

        // Progress from 60% to 65% should not trigger new milestone
        $customerChallenge->progress_percentage = 65.0;
        $customerChallenge->save();
        
        $milestone = $customerChallenge->checkMilestoneReached(60.0);
        $this->assertNull($milestone);
    }

    /**
     * Test markAsExpired method
     */
    public function test_mark_as_expired(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'status' => 'active',
        ]);

        $customerChallenge->markAsExpired();

        $this->assertEquals('expired', $customerChallenge->status);
    }

    /**
     * Test claimReward method
     */
    public function test_claim_reward(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create([
            'status' => 'completed',
            'reward_claimed' => false,
        ]);

        $rewardDetails = [
            'type' => 'points',
            'value' => 100,
            'awarded_at' => now(),
        ];

        $customerChallenge->claimReward($rewardDetails);

        $this->assertTrue($customerChallenge->reward_claimed);
        $this->assertEquals('rewarded', $customerChallenge->status);
        $this->assertEquals($rewardDetails['type'], $customerChallenge->reward_details['type']);
        $this->assertEquals($rewardDetails['value'], $customerChallenge->reward_details['value']);
        $this->assertNotNull($customerChallenge->reward_claimed_at);
    }

    /**
     * Test active scope
     */
    public function test_active_scope(): void
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $customer3 = Customer::factory()->create();
        $challenge1 = Challenge::factory()->create();
        $challenge2 = Challenge::factory()->create();
        $challenge3 = Challenge::factory()->create();

        // Create challenges with different statuses
        CustomerChallenge::factory()->create([
            'customer_id' => $customer1->id,
            'challenge_id' => $challenge1->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        CustomerChallenge::factory()->create([
            'customer_id' => $customer2->id,
            'challenge_id' => $challenge2->id,
            'status' => 'completed',
            'expires_at' => now()->addDay(),
        ]);

        CustomerChallenge::factory()->create([
            'customer_id' => $customer3->id,
            'challenge_id' => $challenge3->id,
            'status' => 'active',
            'expires_at' => now()->subDay(), // Expired
        ]);

        $activeChallenges = CustomerChallenge::active()->get();
        
        $this->assertCount(1, $activeChallenges);
        $this->assertEquals('active', $activeChallenges->first()->status);
    }

    /**
     * Test relationships
     */
    public function test_customer_and_challenge_relationships(): void
    {
        $customer = Customer::factory()->create();
        $challenge = Challenge::factory()->create();

        $customerChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $customer->id,
            'challenge_id' => $challenge->id,
        ]);

        $this->assertInstanceOf(Customer::class, $customerChallenge->customer);
        $this->assertInstanceOf(Challenge::class, $customerChallenge->challenge);
        $this->assertEquals($customer->id, $customerChallenge->customer->id);
        $this->assertEquals($challenge->id, $customerChallenge->challenge->id);
    }

    /**
     * Test progress logs relationship
     */
    public function test_progress_logs_relationship(): void
    {
        $customerChallenge = CustomerChallenge::factory()->create();

        ChallengeProgressLog::factory()->count(3)->create([
            'customer_challenge_id' => $customerChallenge->id,
        ]);

        $this->assertCount(3, $customerChallenge->progressLogs);
        $this->assertInstanceOf(ChallengeProgressLog::class, $customerChallenge->progressLogs->first());
    }

    /**
     * Test json field casting
     */
    public function test_json_fields_casting(): void
    {
        $progressDetails = ['items_tried' => ['pizza', 'burger'], 'orders' => [1, 2, 3]];
        $rewardDetails = ['type' => 'points', 'amount' => 100];
        $metadata = ['source' => 'weekly_generation', 'difficulty' => 'medium'];

        $customerChallenge = CustomerChallenge::factory()->create([
            'progress_details' => $progressDetails,
            'reward_details' => $rewardDetails,
            'metadata' => $metadata,
        ]);

        $this->assertEquals($progressDetails, $customerChallenge->progress_details);
        $this->assertEquals($rewardDetails, $customerChallenge->reward_details);
        $this->assertEquals($metadata, $customerChallenge->metadata);
        $this->assertIsArray($customerChallenge->progress_details);
        $this->assertIsArray($customerChallenge->reward_details);
        $this->assertIsArray($customerChallenge->metadata);
    }
}