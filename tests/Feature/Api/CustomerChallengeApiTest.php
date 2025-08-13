<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Challenge;
use App\Models\CustomerChallenge;
use App\Services\CustomerChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class CustomerChallengeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);
        
        $this->customer = Customer::factory()->create();

        Sanctum::actingAs($this->user);
    }

    /**
     * Test creating a challenge via API
     */
    public function test_create_challenge_with_valid_data(): void
    {
        $challengeData = [
            'name' => 'API Test Challenge',
            'description' => 'A challenge created via API',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 5],
            'reward_type' => 'points',
            'reward_value' => 100,
            'start_date' => now()->addDay()->toISOString(),
            'end_date' => now()->addWeek()->toISOString(),
            'is_active' => true,
        ];

        $response = $this->postJson('/api/challenges', $challengeData);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Challenge created successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'challenge' => [
                            'id',
                            'name',
                            'description',
                            'challenge_type',
                            'reward_type',
                            'reward_value',
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('challenges', [
            'name' => 'API Test Challenge',
            'challenge_type' => 'frequency',
        ]);
    }

    /**
     * Test creating challenge with invalid data returns validation errors
     */
    public function test_create_challenge_with_invalid_data_returns_validation_errors(): void
    {
        $invalidData = [
            'name' => '', // Empty name
            'challenge_type' => 'invalid_type',
            'reward_value' => -10, // Negative value
        ];

        $response = $this->postJson('/api/challenges', $invalidData);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Validation failed',
                ])
                ->assertJsonStructure(['errors']);
    }

    /**
     * Test getting all challenges
     */
    public function test_get_all_challenges(): void
    {
        Challenge::factory()->count(3)->create(['is_active' => true]);
        Challenge::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/challenges');

        $response->assertStatus(200)
                ->assertJson(['status' => 'success'])
                ->assertJsonStructure([
                    'data' => [
                        'challenges' => [
                            '*' => [
                                'id',
                                'name',
                                'challenge_type',
                                'reward_type',
                                'completion_rate',
                            ]
                        ],
                        'total_count'
                    ]
                ]);
    }

    /**
     * Test filtering challenges by type
     */
    public function test_get_challenges_filtered_by_type(): void
    {
        Challenge::factory()->count(2)->create(['challenge_type' => 'frequency']);
        Challenge::factory()->count(3)->create(['challenge_type' => 'variety']);

        $response = $this->getJson('/api/challenges?challenge_type=frequency');

        $response->assertStatus(200);
        
        $challenges = $response->json('data.challenges');
        $this->assertCount(2, $challenges);
        
        foreach ($challenges as $challenge) {
            $this->assertEquals('frequency', $challenge['challenge_type']);
        }
    }

    /**
     * Test assigning challenge to customer
     */
    public function test_assign_challenge_to_customer(): void
    {
        $challenge = Challenge::factory()->active()->create();

        $response = $this->postJson("/api/challenges/{$challenge->id}/assign", [
            'customer_id' => $this->customer->id,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Challenge assigned successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'customer_challenge' => [
                            'id',
                            'challenge_id',
                            'customer_id',
                            'status',
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('customer_challenges', [
            'customer_id' => $this->customer->id,
            'challenge_id' => $challenge->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test assigning challenge to ineligible customer
     */
    public function test_assign_challenge_to_ineligible_customer(): void
    {
        $challenge = Challenge::factory()->inactive()->create();

        $response = $this->postJson("/api/challenges/{$challenge->id}/assign", [
            'customer_id' => $this->customer->id,
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Customer is not eligible for this challenge',
                ]);
    }

    /**
     * Test getting customer's challenges
     */
    public function test_get_customer_challenges(): void
    {
        $challenges = Challenge::factory()->count(3)->create();
        
        foreach ($challenges as $index => $challenge) {
            CustomerChallenge::factory()->create([
                'customer_id' => $this->customer->id,
                'challenge_id' => $challenge->id,
                'status' => $index === 0 ? 'completed' : 'active',
            ]);
        }

        $response = $this->getJson("/api/customers/{$this->customer->id}/challenges");

        $response->assertStatus(200)
                ->assertJson(['status' => 'success'])
                ->assertJsonStructure([
                    'data' => [
                        'challenges' => [
                            '*' => [
                                'id',
                                'challenge',
                                'progress',
                                'timing',
                                'status',
                            ]
                        ],
                        'total_count'
                    ]
                ]);

        // Should only return active challenges (2 out of 3)
        $this->assertCount(2, $response->json('data.challenges'));
    }

    /**
     * Test checking customer challenge progress
     */
    public function test_check_customer_challenge_progress(): void
    {
        $challenges = Challenge::factory()->count(2)->create();
        
        foreach ($challenges as $challenge) {
            CustomerChallenge::factory()->active()->create([
                'customer_id' => $this->customer->id,
                'challenge_id' => $challenge->id,
            ]);
        }

        $response = $this->getJson("/api/customers/{$this->customer->id}/challenges/progress");

        $response->assertStatus(200)
                ->assertJson(['status' => 'success'])
                ->assertJsonStructure([
                    'data' => [
                        'progress' => [
                            '*' => [
                                'challenge_name',
                                'progress_percentage',
                                'days_remaining',
                                'is_close_to_completion',
                            ]
                        ],
                        'total_active_challenges'
                    ]
                ]);

        $this->assertEquals(2, $response->json('data.total_active_challenges'));
    }

    /**
     * Test updating challenge progress
     */
    public function test_update_challenge_progress(): void
    {
        $progressData = [
            'customer_id' => $this->customer->id,
            'action_type' => 'order_placed',
            'action_data' => [
                'order_number' => 'ORD-123',
                'order_total' => 75.50,
            ],
        ];

        $response = $this->postJson('/api/challenges/progress/update', $progressData);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Challenge progress updated successfully',
                ]);
    }

    /**
     * Test getting challenge leaderboard
     */
    public function test_get_challenge_leaderboard(): void
    {
        $challenge = Challenge::factory()->create();
        $customers = Customer::factory()->count(3)->create();

        foreach ($customers as $index => $customer) {
            CustomerChallenge::factory()->create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'progress_percentage' => 90 - ($index * 20), // 90%, 70%, 50%
            ]);
        }

        $response = $this->getJson("/api/challenges/{$challenge->id}/leaderboard");

        $response->assertStatus(200)
                ->assertJson(['status' => 'success'])
                ->assertJsonStructure([
                    'data' => [
                        'challenge' => [
                            'id',
                            'name',
                            'challenge_type',
                        ],
                        'leaderboard' => [
                            '*' => [
                                'rank',
                                'customer',
                                'progress',
                                'status',
                            ]
                        ],
                        'total_participants'
                    ]
                ]);

        $leaderboard = $response->json('data.leaderboard');
        $this->assertCount(3, $leaderboard);
        
        // Verify ranking order (highest progress first)
        $this->assertEquals(1, $leaderboard[0]['rank']);
        $this->assertEquals(90, $leaderboard[0]['progress']['percentage']);
    }

    /**
     * Test tracking engagement
     */
    public function test_track_engagement(): void
    {
        $challenge = Challenge::factory()->create();
        
        $engagementData = [
            'customer_id' => $this->customer->id,
            'challenge_id' => $challenge->id,
            'event_type' => 'challenge_viewed',
            'event_data' => ['page' => 'challenges_list'],
            'source' => 'mobile_app',
        ];

        $response = $this->postJson('/api/challenges/engagement/track', $engagementData);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Engagement tracked successfully',
                ]);

        $this->assertDatabaseHas('challenge_engagement_logs', [
            'customer_id' => $this->customer->id,
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
        $response = $this->postJson('/api/challenges/generate-weekly');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Weekly challenges generated successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'challenges' => [
                            '*' => [
                                'id',
                                'name',
                                'challenge_type',
                                'start_date',
                                'end_date',
                            ]
                        ],
                        'total_generated'
                    ]
                ]);

        $this->assertGreaterThan(0, $response->json('data.total_generated'));
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

        $response = $this->getJson("/api/challenges/{$challenge->id}/rewards/{$this->customer->id}");

        $response->assertStatus(200)
                ->assertJson(['status' => 'success'])
                ->assertJsonStructure([
                    'data' => [
                        'challenge' => [
                            'id',
                            'name',
                            'reward_type',
                        ],
                        'customer' => [
                            'id',
                            'name',
                        ],
                        'reward_calculation' => [
                            'base_value',
                            'adjusted_value',
                            'tier_multiplier',
                            'difficulty_multiplier',
                        ]
                    ]
                ]);
    }

    /**
     * Test completing challenge
     */
    public function test_complete_challenge(): void
    {
        $challenge = Challenge::factory()->active()->create();
        $customerChallenge = CustomerChallenge::factory()->completed()->create([
            'customer_id' => $this->customer->id,
            'challenge_id' => $challenge->id,
            'reward_claimed' => false,
        ]);

        $response = $this->postJson("/api/customer-challenges/{$customerChallenge->id}/complete");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Challenge completed and reward awarded successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'customer_challenge' => [
                            'id',
                            'status',
                            'completed_at',
                            'reward_claimed',
                        ]
                    ]
                ]);
    }

    /**
     * Test completing invalid challenge
     */
    public function test_complete_invalid_challenge(): void
    {
        $challenge = Challenge::factory()->inactive()->create();
        $customerChallenge = CustomerChallenge::factory()->create([
            'customer_id' => $this->customer->id,
            'challenge_id' => $challenge->id,
            'status' => 'active',
            'progress_current' => 2,
            'progress_target' => 5, // Not completed
        ]);

        $response = $this->postJson("/api/customer-challenges/{$customerChallenge->id}/complete");

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Challenge completion validation failed',
                ]);
    }

    /**
     * Test expiring old challenges
     */
    public function test_expire_old_challenges(): void
    {
        // Create some expired customer challenges
        CustomerChallenge::factory()->count(2)->create([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/challenges/expire-old');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Old challenges expired successfully',
                ])
                ->assertJsonStructure([
                    'data' => ['expired_count']
                ]);

        $this->assertEquals(2, $response->json('data.expired_count'));
    }

    /**
     * Test authentication required for protected endpoints
     */
    public function test_authentication_required(): void
    {
        // Test without authentication
        $this->app->make('auth')->forgetGuards();
        
        $response = $this->getJson('/api/challenges');

        $response->assertStatus(401);
    }

    /**
     * Test invalid customer ID returns 404
     */
    public function test_invalid_customer_id_returns_404(): void
    {
        $response = $this->getJson('/api/customers/99999/challenges');

        $response->assertStatus(404); // Model not found
    }

    /**
     * Test invalid challenge ID returns 404
     */
    public function test_invalid_challenge_id_returns_404(): void
    {
        $response = $this->getJson('/api/challenges/99999/leaderboard');

        $response->assertStatus(404); // Model not found
    }

    /**
     * Test validation errors for engagement tracking
     */
    public function test_track_engagement_validation_errors(): void
    {
        $invalidData = [
            'customer_id' => 99999, // Non-existent customer
            'event_type' => 'invalid_event',
        ];

        $response = $this->postJson('/api/challenges/engagement/track', $invalidData);

        $response->assertStatus(422)
                ->assertJsonStructure(['errors']);
    }
}