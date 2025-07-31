<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\CustomerLoyaltyPoint;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class CustomerLoyaltyPointsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $superAdmin;
    protected User $cashier;
    protected User $customerService;
    protected Customer $customer;
    protected LoyaltyProgram $loyaltyProgram;
    protected LoyaltyTier $bronzeTier;
    protected LoyaltyTier $silverTier;
    protected LoyaltyTier $goldTier;
    protected CustomerLoyaltyPoint $customerLoyaltyPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->cashier = User::factory()->create(['role' => 'CASHIER', 'status' => 'active']);
        $this->customerService = User::factory()->create(['role' => 'CUSTOMER_SERVICE', 'status' => 'active']);
        
        // Create test data
        $this->customer = Customer::factory()->create();
        $this->loyaltyProgram = LoyaltyProgram::factory()->create();
        
        // Create loyalty tiers
        $this->bronzeTier = LoyaltyTier::factory()->bronze()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
        ]);
        
        $this->silverTier = LoyaltyTier::factory()->silver()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
        ]);
        
        $this->goldTier = LoyaltyTier::factory()->gold()->create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
        ]);
        
        // Create customer loyalty points
        $this->customerLoyaltyPoint = CustomerLoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'loyalty_tier_id' => $this->bronzeTier->id,
            'current_points' => 500.00,
            'total_points_earned' => 1000.00,
            'total_points_redeemed' => 300.00,
            'total_points_expired' => 200.00,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_lists_customer_loyalty_points_with_pagination()
    {
        // Create multiple customer loyalty points - ensure all are active
        CustomerLoyaltyPoint::factory()->count(15)->create(['is_active' => true]);
        
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson('/api/customer-loyalty-points');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'customer_id',
                            'loyalty_program_id',
                            'loyalty_tier_id',
                            'current_points',
                            'total_points_earned',
                            'total_points_redeemed',
                            'total_points_expired',
                            'available_points',
                            'points_to_expire',
                            'is_expired',
                            'next_tier_progress',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'links',
                    'meta'
                ]);
        
        // Verify pagination - should show all records (15 created + 1 from setUp)
        $response->assertJsonCount(15, 'data');
    }

    #[Test]
    public function it_creates_new_customer_loyalty_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $newCustomer = Customer::factory()->create();
        
        $loyaltyPointsData = [
            'customer_id' => $newCustomer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'current_points' => 100.00,
            'total_points_earned' => 100.00,
            'is_active' => true,
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points', $loyaltyPointsData);
        
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id',
                        'customer_id',
                        'loyalty_program_id',
                        'current_points',
                        'total_points_earned',
                        'is_active',
                        'loyalty_tier_id',
                        'points_expiry_date',
                    ]
                ]);
        
        $this->assertDatabaseHas('customer_loyalty_points', [
            'customer_id' => $newCustomer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'current_points' => 100.00,
            'total_points_earned' => 100.00,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_shows_specific_customer_loyalty_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson("/api/customer-loyalty-points/{$this->customerLoyaltyPoint->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'customer_id',
                        'loyalty_program_id',
                        'loyalty_tier_id',
                        'current_points',
                        'total_points_earned',
                        'total_points_redeemed',
                        'total_points_expired',
                        'available_points',
                        'points_to_expire',
                        'is_expired',
                        'next_tier_progress',
                        'customer',
                        'loyalty_program',
                        'loyalty_tier',
                        'points_history',
                    ]
                ]);
    }

    #[Test]
    public function it_updates_customer_loyalty_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $updateData = [
            'current_points' => 1500.00,
            'total_points_earned' => 2000.00,
        ];
        
        $response = $this->putJson("/api/customer-loyalty-points/{$this->customerLoyaltyPoint->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id',
                        'current_points',
                        'total_points_earned',
                    ]
                ]);
        
        $this->assertDatabaseHas('customer_loyalty_points', [
            'id' => $this->customerLoyaltyPoint->id,
            'current_points' => 1500.00,
            'total_points_earned' => 2000.00,
        ]);
    }

    #[Test]
    public function it_deletes_customer_loyalty_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->deleteJson("/api/customer-loyalty-points/{$this->customerLoyaltyPoint->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Customer loyalty points deleted successfully'
                ]);
        
        $this->assertDatabaseMissing('customer_loyalty_points', [
            'id' => $this->customerLoyaltyPoint->id,
        ]);
    }

    #[Test]
    public function it_earns_points_for_customer()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 100.00,
            'source' => 'order',
            'order_id' => $order->id,
            'base_amount' => 50.00,
            'multiplier_applied' => 2.0,
            'description' => 'Points earned from order purchase',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'points_earned',
                        'new_balance',
                        'tier_upgraded',
                        'new_tier',
                    ]
                ]);
        
        // Verify points were added
        $this->assertDatabaseHas('customer_loyalty_points', [
            'id' => $this->customerLoyaltyPoint->id,
            'current_points' => 600.00, // 500 + 100
            'total_points_earned' => 1100.00, // 1000 + 100
        ]);
        
        // Verify transaction history was created
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'earned',
            'points_amount' => 100.00,
            'source' => 'order',
        ]);
    }

    #[Test]
    public function it_redeems_points_for_customer()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $redeemData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 50.00,
            'redemption_type' => 'discount',
            'order_id' => $order->id,
            'description' => 'Points redeemed for discount',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/redeem-points', $redeemData);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'points_redeemed',
                        'new_balance',
                        'redemption_type',
                    ]
                ]);
        
        // Verify points were deducted
        $this->assertDatabaseHas('customer_loyalty_points', [
            'id' => $this->customerLoyaltyPoint->id,
            'current_points' => 450.00, // 500 - 50
            'total_points_redeemed' => 350.00, // 300 + 50
        ]);
        
        // Verify transaction history was created
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'redeemed',
            'points_amount' => 50.00,
            'source' => 'discount',
        ]);
    }

    #[Test]
    public function it_prevents_redemption_with_insufficient_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $redeemData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 1000.00, // More than available
            'redemption_type' => 'discount',
            'order_id' => $order->id,
            'description' => 'Points redeemed for discount',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/redeem-points', $redeemData);
        
        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Insufficient points for redemption'
                ]);
    }

    #[Test]
    public function it_upgrades_tier_when_points_threshold_reached()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        // Customer has 500 points, needs 1000 for Silver tier
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 600.00, // This will push them to 1100 points
            'source' => 'order',
            'order_id' => $order->id,
            'base_amount' => 300.00,
            'multiplier_applied' => 2.0,
            'description' => 'Points earned from order purchase',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'tier_upgraded' => true,
                        'new_tier' => 'silver',
                    ]
                ]);
        
        // Verify tier was upgraded
        $this->assertDatabaseHas('customer_loyalty_points', [
            'id' => $this->customerLoyaltyPoint->id,
            'loyalty_tier_id' => $this->silverTier->id,
            'current_points' => 1100.00,
        ]);
    }

    #[Test]
    public function it_gets_customer_loyalty_points_summary()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson('/api/customer-loyalty-points/summary?' . http_build_query([
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
        ]));
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'current_points',
                        'total_points_earned',
                        'total_points_redeemed',
                        'total_points_expired',
                        'available_points',
                        'points_to_expire',
                        'current_tier',
                        'next_tier_progress',
                        'recent_transactions',
                        'loyalty_program',
                    ]
                ]);
    }

    #[Test]
    public function it_processes_points_expiration()
    {
        Sanctum::actingAs($this->cashier);
        
        // Create expired loyalty points with fresh data
        $expiredLoyaltyPoint = CustomerLoyaltyPoint::factory()->create([
            'current_points' => 200.00,
            'total_points_expired' => 0.00,
            'points_expiry_date' => now()->subDay(),
        ]);
        
        $response = $this->postJson('/api/customer-loyalty-points/process-expiration');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'customers_affected',
                        'total_points_expired',
                    ]
                ]);
        
        // Verify points were expired
        $this->assertDatabaseHas('customer_loyalty_points', [
            'id' => $expiredLoyaltyPoint->id,
            'current_points' => '0.00',
            'total_points_expired' => '200.00',
        ]);
        
        // Verify expiration history was created
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $expiredLoyaltyPoint->id,
            'transaction_type' => 'expired',
            'points_amount' => '200.00',
            'source' => 'expiration',
        ]);
    }

    #[Test]
    public function it_validates_required_fields_on_create()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->postJson('/api/customer-loyalty-points', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_id', 'loyalty_program_id']);
    }

    #[Test]
    public function it_validates_points_range()
    {
        Sanctum::actingAs($this->cashier);
        
        $newCustomer = Customer::factory()->create();
        
        $loyaltyPointsData = [
            'customer_id' => $newCustomer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'current_points' => -100.00, // Negative points
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points', $loyaltyPointsData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_points']);
    }

    #[Test]
    public function it_prevents_duplicate_loyalty_points_for_same_customer_and_program()
    {
        Sanctum::actingAs($this->cashier);
        
        $loyaltyPointsData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'current_points' => 100.00,
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points', $loyaltyPointsData);
        
        $response->assertStatus(409)
                ->assertJson([
                    'message' => 'Customer already has loyalty points for this program'
                ]);
    }

    #[Test]
    public function it_filters_loyalty_points_by_customer()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson("/api/customer-loyalty-points?customer_id={$this->customer->id}");
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $loyaltyPoint) {
            $this->assertEquals($this->customer->id, $loyaltyPoint['customer_id']);
        }
    }

    #[Test]
    public function it_filters_loyalty_points_by_loyalty_program()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson("/api/customer-loyalty-points?loyalty_program_id={$this->loyaltyProgram->id}");
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $loyaltyPoint) {
            $this->assertEquals($this->loyaltyProgram->id, $loyaltyPoint['loyalty_program_id']);
        }
    }

    #[Test]
    public function it_filters_loyalty_points_by_points_range()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson("/api/customer-loyalty-points?min_points=400&max_points=600");
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $loyaltyPoint) {
            $this->assertGreaterThanOrEqual(400, $loyaltyPoint['current_points']);
            $this->assertLessThanOrEqual(600, $loyaltyPoint['current_points']);
        }
    }

    #[Test]
    public function it_handles_birthday_bonus_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 300.00,
            'source' => 'birthday',
            'order_id' => $order->id,
            'base_amount' => 100.00,
            'multiplier_applied' => 3.0,
            'description' => 'Birthday bonus points',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(200);
        
        // Verify birthday bonus transaction
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'earned',
            'source' => 'birthday',
            'multiplier_applied' => 3.0,
        ]);
    }

    #[Test]
    public function it_handles_happy_hour_bonus_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 200.00,
            'source' => 'happy_hour',
            'order_id' => $order->id,
            'base_amount' => 100.00,
            'multiplier_applied' => 2.0,
            'description' => 'Happy hour bonus points',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(200);
        
        // Verify happy hour bonus transaction
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'earned',
            'source' => 'happy_hour',
            'multiplier_applied' => 2.0,
        ]);
    }

    #[Test]
    public function it_handles_first_order_bonus_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 500.00,
            'source' => 'first_order',
            'order_id' => $order->id,
            'base_amount' => 100.00,
            'multiplier_applied' => 5.0,
            'description' => 'First order bonus points',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(200);
        
        // Verify first order bonus transaction
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'earned',
            'source' => 'first_order',
            'multiplier_applied' => 5.0,
        ]);
    }

    #[Test]
    public function it_handles_referral_bonus_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 1000.00,
            'source' => 'referral',
            'order_id' => $order->id,
            'base_amount' => 100.00,
            'multiplier_applied' => 10.0,
            'description' => 'Referral bonus points',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(200);
        
        // Verify referral bonus transaction
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'earned',
            'source' => 'referral',
            'multiplier_applied' => 10.0,
        ]);
    }

    #[Test]
    public function it_handles_free_delivery_redemption()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $redeemData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 100.00,
            'redemption_type' => 'free_delivery',
            'order_id' => $order->id,
            'description' => 'Points redeemed for free delivery',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/redeem-points', $redeemData);
        
        $response->assertStatus(200);
        
        // Verify free delivery redemption
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'redeemed',
            'source' => 'free_delivery',
        ]);
    }

    #[Test]
    public function it_handles_free_item_redemption()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $redeemData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 150.00,
            'redemption_type' => 'free_item',
            'order_id' => $order->id,
            'description' => 'Points redeemed for free item',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/redeem-points', $redeemData);
        
        $response->assertStatus(200);
        
        // Verify free item redemption
        $this->assertDatabaseHas('loyalty_points_history', [
            'customer_loyalty_points_id' => $this->customerLoyaltyPoint->id,
            'transaction_type' => 'redeemed',
            'source' => 'free_item',
        ]);
    }

    #[Test]
    public function it_enforces_role_permissions()
    {
        // Test that unauthorized users cannot access loyalty points
        $unauthorizedUser = User::factory()->create(['role' => 'KITCHEN_STAFF', 'status' => 'active']);
        Sanctum::actingAs($unauthorizedUser);
        
        $response = $this->getJson('/api/customer-loyalty-points');
        
        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_nonexistent_customer_loyalty_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->getJson('/api/customer-loyalty-points/99999');
        
        $response->assertStatus(404);
    }

    #[Test]
    public function it_validates_earn_points_request()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_id', 'loyalty_program_id', 'points_amount', 'source', 'description']);
    }

    #[Test]
    public function it_validates_redeem_points_request()
    {
        Sanctum::actingAs($this->cashier);
        
        $response = $this->postJson('/api/customer-loyalty-points/redeem-points', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_id', 'loyalty_program_id', 'points_amount', 'redemption_type', 'description']);
    }

    #[Test]
    public function it_handles_customer_not_found_for_earn_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => 99999, // Non-existent customer
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'points_amount' => 100.00,
            'source' => 'order',
            'order_id' => $order->id,
            'description' => 'Points earned from order purchase',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Customer loyalty points not found for this program'
                ]);
    }

    #[Test]
    public function it_handles_loyalty_program_not_found_for_earn_points()
    {
        Sanctum::actingAs($this->cashier);
        
        $order = Order::factory()->create();
        
        $earnData = [
            'customer_id' => $this->customer->id,
            'loyalty_program_id' => 99999, // Non-existent program
            'points_amount' => 100.00,
            'source' => 'order',
            'order_id' => $order->id,
            'description' => 'Points earned from order purchase',
        ];
        
        $response = $this->postJson('/api/customer-loyalty-points/earn-points', $earnData);
        
        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Customer loyalty points not found for this program'
                ]);
    }
} 