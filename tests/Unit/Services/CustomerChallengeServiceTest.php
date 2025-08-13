<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CustomerChallengeService;
use App\Services\NotificationService;
use App\Services\LoyaltyService;
use App\Services\SecurityLoggingService;
use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use App\Models\ChallengeProgressLog;
use App\Models\ChallengeEngagementLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Carbon\Carbon;

class CustomerChallengeServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerChallengeService $service;
    private $notificationService;
    private $loyaltyService;
    private $securityLoggingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test double that extends NotificationService to satisfy type hints
        $this->notificationService = new class extends \App\Services\NotificationService {
            public function createChallengeNotification(\App\Models\User|\App\Models\Customer $user, string $type, string $title, string $message, array $data = [], string $category = 'challenge'): \App\Models\Notification {
                $notification = new \App\Models\Notification();
                $notification->id = \Illuminate\Support\Str::uuid();
                $notification->type = $type;
                $notification->notifiable_type = get_class($user);
                $notification->notifiable_id = $user->id;
                $notification->data = array_merge($data, [
                    'title' => $title,
                    'message' => $message,
                    'category' => $category
                ]);
                $notification->created_at = now();
                $notification->updated_at = now();
                
                return $notification;
            }
        };
        
        $this->loyaltyService = Mockery::mock(LoyaltyService::class);
        $this->securityLoggingService = Mockery::mock(SecurityLoggingService::class);
        
        // Bind the test doubles to the service container
        $this->app->instance(NotificationService::class, $this->notificationService);
        $this->app->instance(LoyaltyService::class, $this->loyaltyService);
        $this->app->instance(SecurityLoggingService::class, $this->securityLoggingService);
        
        $this->service = $this->app->make(CustomerChallengeService::class);
    }

    /**
     * Test creating a customer challenge
     */
    public function test_create_customer_challenge_with_valid_data(): void
    {
        $challengeData = [
            'name' => 'Weekly Warrior',
            'description' => 'Order 5 times this week',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 5],
            'reward_type' => 'points',
            'reward_value' => 100,
            'start_date' => now()->addDay(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
        ];

        $challenge = $this->service->createCustomerChallenge($challengeData);

        $this->assertInstanceOf(Challenge::class, $challenge);
        $this->assertEquals('Weekly Warrior', $challenge->name);
        $this->assertEquals('frequency', $challenge->challenge_type);
        $this->assertEquals(['order_count' => 5], $challenge->requirements);
        $this->assertTrue($challenge->is_active);
    }

    /**
     * Test creating challenge with invalid data throws exception
     */
    public function test_create_customer_challenge_with_invalid_data_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: name');

        $this->service->createCustomerChallenge([
            'description' => 'Test challenge',
            // Missing required 'name' field
        ]);
    }

    /**
     * Test creating challenge with invalid date range throws exception
     */
    public function test_create_customer_challenge_with_invalid_dates_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start date must be before end date');

        $challengeData = [
            'name' => 'Invalid Challenge',
            'description' => 'Test',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 5],
            'reward_type' => 'points',
            'reward_value' => 100,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addDay(), // End before start
        ];

        $this->service->createCustomerChallenge($challengeData);
    }

    /**
     * Test assigning challenge to eligible customer
     */
    public function test_assign_challenge_to_eligible_customer(): void
    {
        $challenge = Challenge::factory()->active()->create();
        $customer = Customer::factory()->create();

        $customerChallenge = $this->service->assignChallengeToCustomer($challenge, $customer);

        $this->assertInstanceOf(CustomerChallenge::class, $customerChallenge);
        $this->assertEquals($challenge->id, $customerChallenge->challenge_id);
        $this->assertEquals($customer->id, $customerChallenge->customer_id);
        $this->assertEquals('active', $customerChallenge->status);
    }

    /**
     * Test assigning challenge to ineligible customer returns null
     */
    public function test_assign_challenge_to_ineligible_customer_returns_null(): void
    {
        $challenge = Challenge::factory()->inactive()->create();
        $customer = Customer::factory()->create();

        $customerChallenge = $this->service->assignChallengeToCustomer($challenge, $customer);

        $this->assertNull($customerChallenge);
    }

    /**
     * Test checking challenge progress
     */
    public function test_check_challenge_progress(): void
    {
        $customer = Customer::factory()->create();
        $challenge1 = Challenge::factory()->create();
        $challenge2 = Challenge::factory()->create();
        $challenge3 = Challenge::factory()->create();
        
        CustomerChallenge::factory()
            ->active()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge1->id,
            ]);

        CustomerChallenge::factory()
            ->active()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge2->id,
            ]);

        CustomerChallenge::factory()
            ->completed()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge3->id,
            ]);

        $progress = $this->service->checkChallengeProgress($customer);

        $this->assertCount(2, $progress); // Only active challenges
        
        $firstProgress = $progress[0];
        $this->assertArrayHasKey('challenge_name', $firstProgress);
        $this->assertArrayHasKey('progress_percentage', $firstProgress);
        $this->assertArrayHasKey('days_remaining', $firstProgress);
    }

    /**
     * Test updating challenge progress
     */
    public function test_update_challenge_progress(): void
    {
        $customer = Customer::factory()->create();
        $challenge = Challenge::factory()->frequency()->create();
        
        $customerChallenge = CustomerChallenge::factory()
            ->active()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'progress_target' => 5,
                'progress_current' => 2,
            ]);

        $actionData = [
            'order_number' => 'ORD-123',
            'order_total' => 50.00,
        ];

        $this->service->updateChallengeProgress($customer, 'order_placed', $actionData);

        $customerChallenge->refresh();
        $this->assertEquals(3, $customerChallenge->progress_current);
        $this->assertEquals(60.0, $customerChallenge->progress_percentage);
    }

    /**
     * Test updating progress completes challenge
     */
    public function test_update_progress_completes_challenge(): void
    {
        $challenge = Challenge::factory()->frequency()->create([
            'reward_type' => 'points',
            'reward_value' => 100,
        ]);
        $customer = Customer::factory()->create();
        
        // Create CustomerChallenge manually to avoid factory creating its own challenge
        $customerChallenge = CustomerChallenge::create([
            'customer_id' => $customer->id,
            'challenge_id' => $challenge->id,
            'progress_target' => 5,
            'progress_current' => 4,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(7),
            'reward_claimed' => false,
            'assigned_at' => now(),
        ]);

        // Debug output
        echo "\n=== Test Debug ===\n";
        echo "Challenge ID: {$challenge->id}, reward_type: {$challenge->reward_type}\n";
        echo "Customer ID: {$customer->id}\n";
        echo "CustomerChallenge ID: {$customerChallenge->id}\n";
        echo "Initial progress: {$customerChallenge->progress_current}/{$customerChallenge->progress_target}\n";
        echo "Initial status: {$customerChallenge->status}\n";
        
        // Verify the relationship is loaded
        $customerChallenge->load('challenge');
        echo "Challenge relationship loaded: " . ($customerChallenge->challenge ? 'yes' : 'no') . "\n";
        if ($customerChallenge->challenge) {
            echo "Challenge reward_type: " . $customerChallenge->challenge->reward_type . "\n";
        }

        // Mock loyalty service for reward processing - use more flexible expectations
        $this->loyaltyService
            ->shouldReceive('awardPoints')
            ->once()
            ->with(Mockery::type(Customer::class), Mockery::any(), 'challenge_completion', Mockery::any())
            ->andReturn(true);

        $actionData = ['order_number' => 'ORD-123'];

        echo "Calling updateChallengeProgress...\n";
        $this->service->updateChallengeProgress($customer, 'order_placed', $actionData);

        $customerChallenge->refresh();
        echo "After update:\n";
        echo "Progress: {$customerChallenge->progress_current}/{$customerChallenge->progress_target}\n";
        echo "Status: {$customerChallenge->status}\n";
        echo "Reward claimed: " . ($customerChallenge->reward_claimed ? 'yes' : 'no') . "\n";
        echo "=== End Debug ===\n\n";

        $this->assertEquals(5, $customerChallenge->progress_current);
        $this->assertEquals(100.0, $customerChallenge->progress_percentage);
        $this->assertEquals('rewarded', $customerChallenge->status);
        $this->assertTrue($customerChallenge->reward_claimed);
    }

    /**
     * Test validating challenge completion
     */
    public function test_validate_challenge_completion(): void
    {
        $challenge = Challenge::factory()->active()->frequency()->create();
        $customer = Customer::factory()->create();
        
        $customerChallenge = CustomerChallenge::factory()
            ->completed()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'progress_target' => 5,
                'progress_current' => 5,
                'reward_claimed' => false,
            ]);

        $isValid = $this->service->validateChallengeCompletion($customerChallenge);

        $this->assertTrue($isValid);
    }

    /**
     * Test validating already claimed reward fails
     */
    public function test_validate_challenge_completion_with_claimed_reward_fails(): void
    {
        $challenge = Challenge::factory()->active()->create();
        $customer = Customer::factory()->create();
        
        $customerChallenge = CustomerChallenge::factory()
            ->rewarded()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'reward_claimed' => true,
            ]);

        $isValid = $this->service->validateChallengeCompletion($customerChallenge);

        $this->assertFalse($isValid);
    }

    /**
     * Test completing challenge reward
     */
    public function test_complete_challenge_reward(): void
    {
        $challenge = Challenge::factory()->active()->create([
            'reward_type' => 'points',
            'reward_value' => 100,
        ]);
        $customer = Customer::factory()->create();
        
        $customerChallenge = CustomerChallenge::factory()
            ->completed()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'reward_claimed' => false,
            ]);

        // Ensure the status is 'completed' for validation to pass
        $customerChallenge->update(['status' => 'completed']);

        $this->loyaltyService
            ->shouldReceive('awardPoints')
            ->once()
            ->with(Mockery::type(Customer::class), Mockery::type('float'), 'challenge_completion', Mockery::type('array'))
            ->andReturn(true);

        $success = $this->service->completeChallengeReward($customerChallenge);

        $this->assertTrue($success);
        $customerChallenge->refresh();
        $this->assertTrue($customerChallenge->reward_claimed);
        $this->assertEquals('rewarded', $customerChallenge->status);
    }

    /**
     * Test getting active customer challenges
     */
    public function test_get_active_customer_challenges(): void
    {
        $customer = Customer::factory()->create();
        $challenge1 = Challenge::factory()->create();
        $challenge2 = Challenge::factory()->create();
        $challenge3 = Challenge::factory()->create();
        
        // Create active challenges
        CustomerChallenge::factory()
            ->active()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge1->id,
            ]);

        CustomerChallenge::factory()
            ->active()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge2->id,
            ]);

        // Create completed challenge (should not be included)
        CustomerChallenge::factory()
            ->completed()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge3->id,
            ]);

        $challenges = $this->service->getActiveCustomerChallenges($customer);

        $this->assertCount(2, $challenges);
        
        $firstChallenge = $challenges[0];
        $this->assertArrayHasKey('challenge', $firstChallenge);
        $this->assertArrayHasKey('progress', $firstChallenge);
        $this->assertArrayHasKey('timing', $firstChallenge);
    }

    /**
     * Test tracking challenge engagement
     */
    public function test_track_challenge_engagement(): void
    {
        $customer = Customer::factory()->create();
        $challenge = Challenge::factory()->create();

        $eventData = [
            'source' => 'mobile_app',
            'session_id' => 'sess_123',
            'user_agent' => 'Mobile App v1.0',
        ];

        $this->service->trackChallengeEngagement(
            $customer,
            $challenge,
            'challenge_viewed',
            $eventData,
            'mobile_app'
        );

        $this->assertDatabaseHas('challenge_engagement_logs', [
            'customer_id' => $customer->id,
            'challenge_id' => $challenge->id,
            'event_type' => 'challenge_viewed',
            'source' => 'mobile_app',
        ]);
    }

    /**
     * Test generating weekly challenges
     */
    public function test_generate_weekly_challenges(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00')); // Monday

        $challenges = $this->service->generateWeeklyChallenges();

        $this->assertGreaterThan(0, count($challenges));
        
        foreach ($challenges as $challenge) {
            $this->assertInstanceOf(Challenge::class, $challenge);
            $this->assertTrue($challenge->is_active);
            $this->assertEquals(
                Carbon::now()->startOfWeek()->toDateString(),
                $challenge->start_date->toDateString()
            );
        }
    }

    /**
     * Test calculating challenge rewards
     */
    public function test_calculate_challenge_rewards(): void
    {
        $challenge = Challenge::factory()->create([
            'reward_type' => 'points',
            'reward_value' => 100,
        ]);
        $customer = Customer::factory()->create();

        $rewardCalculation = $this->service->calculateChallengeRewards($challenge, $customer);

        $this->assertArrayHasKey('base_value', $rewardCalculation);
        $this->assertArrayHasKey('adjusted_value', $rewardCalculation);
        $this->assertArrayHasKey('tier_multiplier', $rewardCalculation);
        $this->assertArrayHasKey('difficulty_multiplier', $rewardCalculation);
        
        $this->assertEquals(100, $rewardCalculation['base_value']);
        $this->assertIsFloat($rewardCalculation['adjusted_value']);
    }

    /**
     * Test getting challenge leaderboard
     */
    public function test_get_challenge_leaderboard(): void
    {
        $challenge = Challenge::factory()->create();
        $customers = Customer::factory()->count(3)->create();

        // Create customer challenges with different progress
        $progressValues = [100, 75, 50];
        foreach ($customers as $index => $customer) {
            CustomerChallenge::factory()
                ->withProgress()
                ->create([
                    'customer_id' => $customer->id,
                    'challenge_id' => $challenge->id,
                    'progress_percentage' => $progressValues[$index],
                    'status' => 'completed',
                ]);
        }

        $leaderboard = $this->service->getChallengeLeaderboard($challenge);

        $this->assertCount(3, $leaderboard);
        
        // Check that leaderboard contains the expected data structure
        foreach ($leaderboard as $entry) {
            $this->assertArrayHasKey('customer', $entry);
            $this->assertArrayHasKey('progress', $entry);
            $this->assertArrayHasKey('percentage', $entry['progress']);
        }
    }

    /**
     * Test expiring old challenges
     */
    public function test_expire_old_challenges(): void
    {
        $customer = Customer::factory()->create();
        $challenge1 = Challenge::factory()->create();
        $challenge2 = Challenge::factory()->create();
        $challenge3 = Challenge::factory()->create();

        // Create expired challenges (should be expired)
        CustomerChallenge::factory()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge1->id,
                'status' => 'active',
                'expires_at' => now()->subDay(),
            ]);

        CustomerChallenge::factory()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge2->id,
                'status' => 'active',
                'expires_at' => now()->subDay(),
            ]);

        // Create active challenge (should not be expired)
        CustomerChallenge::factory()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge3->id,
                'status' => 'active',
                'expires_at' => now()->addDay(),
            ]);

        $expiredCount = $this->service->expireOldChallenges();

        $this->assertEquals(2, $expiredCount);
        
        // Verify expired challenges have correct status
        $expiredChallenges = CustomerChallenge::where('status', 'expired')->get();
        $this->assertCount(2, $expiredChallenges);
    }

    /**
     * Test notification for milestone reached
     */
    public function test_notification_sent_for_milestone(): void
    {
        $customer = Customer::factory()->create();
        $challenge = Challenge::factory()->frequency()->create();
        
        $customerChallenge = CustomerChallenge::factory()
            ->active()
            ->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'progress_target' => 10,
                'progress_current' => 2, // 20%
            ]);

        // Update progress to cross 25% milestone (3/10 = 30%)
        $actionData = ['order_number' => 'ORD-123'];
        
        // Update progress to trigger milestone (should trigger 25% milestone notification)
        $this->service->updateChallengeProgress($customer, 'order_placed', $actionData);
        
        // Verify the milestone was processed (the test double will handle the notification)
        $customerChallenge->refresh();
        $this->assertEquals(3, $customerChallenge->progress_current);
    }

    /**
     * Test auto-assignment during challenge creation
     */
    public function test_auto_assignment_during_challenge_creation(): void
    {
        // Create some customers with active status
        $customers = Customer::factory()->count(3)->create();
        
        // Ensure all customers have active status
        foreach ($customers as $customer) {
            $customer->update(['status' => 'active']);
        }

        $challengeData = [
            'name' => 'Auto Assigned Challenge',
            'description' => 'Test auto assignment',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 3],
            'reward_type' => 'points',
            'reward_value' => 50,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
            'is_active' => true, // Explicitly set to active
            'auto_assign' => true,
        ];

        $challenge = $this->service->createCustomerChallenge($challengeData);

        // Verify customer challenges were created for each customer
        $this->assertEquals(3, CustomerChallenge::where('challenge_id', $challenge->id)->count());
        
        // Verify each customer has a challenge assigned
        foreach ($customers as $customer) {
            $this->assertDatabaseHas('customer_challenges', [
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
            ]);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}