<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Challenge;
use App\Models\CustomerChallenge;
use App\Models\Order;
use App\Services\CustomerChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class CustomerChallengeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private CustomerChallengeService $challengeService;
    private Customer $customer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->challengeService = app(CustomerChallengeService::class);

        // Fake notifications to avoid actual sending during tests
        Notification::fake();
    }

    /**
     * Test complete challenge workflow from creation to completion
     */
    public function test_complete_challenge_workflow(): void
    {
        // Step 1: Create a frequency challenge
        $challengeData = [
            'name' => 'Order 3 Times This Week',
            'description' => 'Place 3 orders to earn bonus points',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 3],
            'reward_type' => 'points',
            'reward_value' => 150,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
        ];

        $challenge = $this->challengeService->createCustomerChallenge($challengeData);
        
        $this->assertInstanceOf(Challenge::class, $challenge);
        $this->assertEquals('frequency', $challenge->challenge_type);

        // Step 2: Assign challenge to customer
        $customerChallenge = $this->challengeService->assignChallengeToCustomer($challenge, $this->customer);
        
        $this->assertNotNull($customerChallenge);
        $this->assertEquals('assigned', $customerChallenge->status);
        $this->assertEquals(0, $customerChallenge->progress_current);

        // Step 3: Customer places first order
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-001',
            'order_total' => 50.00,
        ]);

        $customerChallenge->refresh();
        $this->assertEquals('active', $customerChallenge->status);
        $this->assertEquals(1, $customerChallenge->progress_current);
        $this->assertEquals(33.33, $customerChallenge->progress_percentage);
        $this->assertNotNull($customerChallenge->started_at);

        // Step 4: Customer places second order
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-002',
            'order_total' => 35.00,
        ]);

        $customerChallenge->refresh();
        $this->assertEquals(2, $customerChallenge->progress_current);
        $this->assertEquals(66.67, $customerChallenge->progress_percentage);

        // Step 5: Customer places third order (completes challenge)
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-003',
            'order_total' => 75.00,
        ]);

        $customerChallenge->refresh();
        $this->assertEquals('rewarded', $customerChallenge->status);
        $this->assertEquals(3, $customerChallenge->progress_current);
        $this->assertEquals(100.0, $customerChallenge->progress_percentage);
        $this->assertTrue($customerChallenge->reward_claimed);
        $this->assertNotNull($customerChallenge->completed_at);
        $this->assertNotNull($customerChallenge->reward_claimed_at);

        // Verify progress logs were created
        $this->assertEquals(3, $customerChallenge->progressLogs()->count());
    }

    /**
     * Test milestone notifications during progress
     */
    public function test_milestone_notifications(): void
    {
        $challenge = Challenge::factory()->create([
            'challenge_type' => 'value',
            'requirements' => ['total_amount' => 200],
        ]);

        $customerChallenge = $this->challengeService->assignChallengeToCustomer($challenge, $this->customer);

        // Progress to 25% (50 out of 200)
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-001',
            'order_total' => 50.00,
        ]);

        // Progress to 60% (120 out of 200) - should trigger 50% milestone
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-002',
            'order_total' => 70.00,
        ]);

        // Check that milestone logs were created
        $milestoneLogs = $customerChallenge->progressLogs()->where('milestone_reached', true)->get();
        $this->assertGreaterThan(0, $milestoneLogs->count());
    }

    /**
     * Test variety challenge workflow
     */
    public function test_variety_challenge_workflow(): void
    {
        $challenge = Challenge::factory()->create([
            'challenge_type' => 'variety',
            'requirements' => ['unique_items' => 3],
            'reward_type' => 'discount',
            'reward_value' => 15, // 15% discount
        ]);

        $customerChallenge = $this->challengeService->assignChallengeToCustomer($challenge, $this->customer);

        // Order with 2 different items
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-001',
            'menu_items' => [
                ['id' => 1, 'name' => 'Pizza'],
                ['id' => 2, 'name' => 'Burger'],
            ],
        ]);

        $customerChallenge->refresh();
        $this->assertEquals(2, $customerChallenge->progress_current);

        // Order with 1 more different item (completes challenge)
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-002',
            'menu_items' => [
                ['id' => 3, 'name' => 'Pasta'],
            ],
        ]);

        $customerChallenge->refresh();
        $this->assertEquals('rewarded', $customerChallenge->status);
        $this->assertEquals(3, $customerChallenge->progress_current);
    }

    /**
     * Test challenge expiration workflow
     */
    public function test_challenge_expiration_workflow(): void
    {
        $challenge = Challenge::factory()->create();
        
        // Create customer challenges that should expire
        $expiredChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $this->customer->id,
            'challenge_id' => $challenge->id,
            'status' => 'active',
            'expires_at' => now()->subHour(),
        ]);

        $activeChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $this->customer->id,
            'challenge_id' => $challenge->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        // Run expiration process
        $expiredCount = $this->challengeService->expireOldChallenges();

        $this->assertEquals(1, $expiredCount);

        $expiredChallenge->refresh();
        $activeChallenge->refresh();

        $this->assertEquals('expired', $expiredChallenge->status);
        $this->assertEquals('active', $activeChallenge->status);
    }

    /**
     * Test weekly challenge generation
     */
    public function test_weekly_challenge_generation(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00')); // Monday

        $challenges = $this->challengeService->generateWeeklyChallenges();

        $this->assertGreaterThan(0, $challenges->count());

        foreach ($challenges as $challenge) {
            $this->assertTrue($challenge->is_active);
            $this->assertEquals(
                Carbon::now()->startOfWeek()->toDateString(),
                $challenge->start_date->toDateString()
            );
            $this->assertEquals(
                Carbon::now()->endOfWeek()->toDateString(),
                $challenge->end_date->toDateString()
            );
        }
    }

    /**
     * Test leaderboard functionality
     */
    public function test_leaderboard_functionality(): void
    {
        $challenge = Challenge::factory()->create();
        $customers = Customer::factory()->count(5)->create();

        // Create customer challenges with different progress levels
        $progressLevels = [100, 85, 70, 50, 25];
        
        foreach ($customers as $index => $customer) {
            $customerChallenge = CustomerChallenge::factory()->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'progress_target' => 100,
                'progress_current' => $progressLevels[$index],
                'progress_percentage' => $progressLevels[$index],
                'status' => $progressLevels[$index] >= 100 ? 'completed' : 'active',
            ]);

            if ($progressLevels[$index] >= 100) {
                $customerChallenge->update([
                    'completed_at' => now()->subMinutes($index), // Different completion times
                ]);
            }
        }

        $leaderboard = $this->challengeService->getChallengeLeaderboard($challenge);

        $this->assertCount(5, $leaderboard);

        // Verify ranking order (highest progress first)
        $previousProgress = 101; // Start higher than any possible progress
        foreach ($leaderboard as $entry) {
            $this->assertLessThanOrEqual($previousProgress, $entry['progress']['percentage']);
            $previousProgress = $entry['progress']['percentage'];
        }

        // Verify top performer
        $topPerformer = $leaderboard->first();
        $this->assertEquals(1, $topPerformer['rank']);
        $this->assertEquals(100, $topPerformer['progress']['percentage']);
    }

    /**
     * Test engagement tracking across multiple interactions
     */
    public function test_engagement_tracking(): void
    {
        $challenge = Challenge::factory()->create();
        
        $engagementEvents = [
            'challenge_viewed',
            'progress_checked',
            'leaderboard_viewed',
            'challenge_shared',
        ];

        foreach ($engagementEvents as $eventType) {
            $this->challengeService->trackChallengeEngagement(
                $this->customer,
                $challenge,
                $eventType,
                [
                    'source' => 'mobile_app',
                    'session_id' => 'sess_123',
                ]
            );
        }

        // Verify all engagement events were logged
        $this->assertEquals(
            count($engagementEvents),
            $challenge->engagementLogs()->where('customer_id', $this->customer->id)->count()
        );

        // Verify event types are correct
        $loggedEventTypes = $challenge->engagementLogs()
            ->where('customer_id', $this->customer->id)
            ->pluck('event_type')
            ->toArray();

        foreach ($engagementEvents as $eventType) {
            $this->assertContains($eventType, $loggedEventTypes);
        }
    }

    /**
     * Test reward calculation with different customer tiers
     */
    public function test_reward_calculation_with_tiers(): void
    {
        $challenge = Challenge::factory()->create([
            'reward_type' => 'points',
            'reward_value' => 100,
        ]);

        $rewardCalculation = $this->challengeService->calculateChallengeRewards($challenge, $this->customer);

        $this->assertArrayHasKey('base_value', $rewardCalculation);
        $this->assertArrayHasKey('adjusted_value', $rewardCalculation);
        $this->assertArrayHasKey('tier_multiplier', $rewardCalculation);
        $this->assertArrayHasKey('difficulty_multiplier', $rewardCalculation);
        
        $this->assertEquals(100, $rewardCalculation['base_value']);
        $this->assertGreaterThanOrEqual(80, $rewardCalculation['adjusted_value']); // At least 80% of base value
        $this->assertLessThanOrEqual(150, $rewardCalculation['adjusted_value']); // At most 150% of base value
    }

    /**
     * Test concurrent challenge participation
     */
    public function test_concurrent_challenge_participation(): void
    {
        // Create multiple challenges of different types
        $frequencyChallenge = Challenge::factory()->frequency()->create();
        $valueChallenge = Challenge::factory()->value()->create();
        
        // Assign both challenges to the same customer
        $customerChallenge1 = $this->challengeService->assignChallengeToCustomer($frequencyChallenge, $this->customer);
        $customerChallenge2 = $this->challengeService->assignChallengeToCustomer($valueChallenge, $this->customer);

        $this->assertNotNull($customerChallenge1);
        $this->assertNotNull($customerChallenge2);

        // Place an order that should progress both challenges
        $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
            'order_number' => 'ORD-001',
            'order_total' => 50.00,
        ]);

        $customerChallenge1->refresh();
        $customerChallenge2->refresh();

        // Frequency challenge should progress by 1
        $this->assertEquals(1, $customerChallenge1->progress_current);
        
        // Value challenge should progress by order total
        $this->assertEquals(50.00, $customerChallenge2->progress_current);

        // Both should be active
        $this->assertEquals('active', $customerChallenge1->status);
        $this->assertEquals('active', $customerChallenge2->status);
    }

    /**
     * Test challenge auto-assignment during creation
     */
    public function test_challenge_auto_assignment(): void
    {
        // Create additional customers
        Customer::factory()->count(4)->create(['is_active' => true]);

        $challengeData = [
            'name' => 'Auto Assigned Challenge',
            'description' => 'This challenge should be auto-assigned',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 2],
            'reward_type' => 'points',
            'reward_value' => 50,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
            'auto_assign' => true,
        ];

        $challenge = $this->challengeService->createCustomerChallenge($challengeData);

        // Should have assigned to all 5 customers (original + 4 new)
        $this->assertEquals(5, $challenge->customerChallenges()->count());

        // All assignments should be in 'assigned' status
        $assignedChallenges = $challenge->customerChallenges()->where('status', 'assigned')->count();
        $this->assertEquals(5, $assignedChallenges);
    }

    /**
     * Test data integrity during concurrent operations
     */
    public function test_data_integrity_concurrent_operations(): void
    {
        $challenge = Challenge::factory()->frequency()->create([
            'requirements' => ['order_count' => 5],
        ]);

        $customerChallenge = $this->challengeService->assignChallengeToCustomer($challenge, $this->customer);

        // Simulate concurrent progress updates
        for ($i = 1; $i <= 5; $i++) {
            $this->challengeService->updateChallengeProgress($this->customer, 'order_placed', [
                'order_number' => "ORD-{$i}",
                'order_total' => 25.00,
            ]);
        }

        $customerChallenge->refresh();

        // Verify final state
        $this->assertEquals('rewarded', $customerChallenge->status);
        $this->assertEquals(5, $customerChallenge->progress_current);
        $this->assertEquals(100.0, $customerChallenge->progress_percentage);
        $this->assertTrue($customerChallenge->reward_claimed);

        // Verify all progress logs were created
        $this->assertEquals(5, $customerChallenge->progressLogs()->count());

        // Verify each progress log has correct increment
        $progressLogs = $customerChallenge->progressLogs()->orderBy('created_at')->get();
        foreach ($progressLogs as $index => $log) {
            $this->assertEquals($index, $log->progress_before);
            $this->assertEquals($index + 1, $log->progress_after);
            $this->assertEquals(1, $log->progress_increment);
        }
    }
}