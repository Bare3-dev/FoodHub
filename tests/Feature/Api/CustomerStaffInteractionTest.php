<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\BranchMenuItem;
use App\Models\SecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

class CustomerStaffInteractionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected User $cashier;
    protected User $customerService;
    protected Customer $customer;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Order $order;
    protected MenuCategory $category;
    protected MenuItem $menuItem;
    protected BranchMenuItem $branchMenuItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with ACTIVE status
        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'active'
        ]);
        
        $this->restaurantOwner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'status' => 'active'
        ]);
        
        $this->branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'status' => 'active'
        ]);
        
        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'active'
        ]);
        
        $this->customerService = User::factory()->create([
            'role' => 'CUSTOMER_SERVICE',
            'status' => 'active'
        ]);
        
        // Create test customer
        $this->customer = Customer::factory()->create([
            'status' => 'active'
        ]);
        
        // Create test restaurant and branch with unique names to avoid conflicts
        $this->restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant ' . uniqid(),
            'slug' => 'test-restaurant-' . uniqid()
        ]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Branch ' . uniqid()
        ]);
        
        // Assign branch manager to the branch
        $this->branchManager->update(['restaurant_branch_id' => $this->branch->id]);
        
        // Create menu items
        $this->category = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'price' => 15.00
        ]);
        
        $this->branchMenuItem = BranchMenuItem::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'menu_item_id' => $this->menuItem->id,
            'price' => 15.00,
            'is_available' => true
        ]);
        
        // Create test order
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 30.00
        ]);
    }

    /** @test */
    public function it_handles_customer_complaint_processing()
    {
        Sanctum::actingAs($this->customerService);

        $complaintData = [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'complaint_type' => 'food_quality',
            'severity' => 'medium',
            'description' => 'Food was cold when delivered',
            'requested_resolution' => 'refund',
            'contact_preference' => 'email'
        ];

        $response = $this->postJson('/api/customer-service/complaints', $complaintData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'customer_id',
                        'complaint_type',
                        'severity',
                        'description',
                        'status',
                        'assigned_to',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        // Verify complaint is logged
        $this->assertDatabaseHas('customer_complaints', [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'complaint_type' => 'food_quality',
            'severity' => 'medium',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_processes_special_requests_correctly()
    {
        Sanctum::actingAs($this->cashier);

        $specialRequestData = [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'request_type' => 'dietary_restriction',
            'description' => 'No onions due to allergy',
            'priority' => 'high',
            'requires_kitchen_attention' => true
        ];

        $response = $this->postJson('/api/orders/special-requests', $specialRequestData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'request_type',
                        'description',
                        'priority',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        // Verify special request is created
        $this->assertDatabaseHas('order_special_requests', [
            'order_id' => $this->order->id,
            'request_type' => 'dietary_restriction',
            'priority' => 'high',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_handles_customer_feedback_integration()
    {
        Sanctum::actingAs($this->customer);

        $feedbackData = [
            'order_id' => $this->order->id,
            'rating' => 4,
            'feedback_type' => 'overall',
            'comments' => 'Great service, food was delicious',
            'categories' => ['food_quality', 'delivery_speed', 'customer_service'],
            'would_recommend' => true
        ];

        $response = $this->postJson('/api/customer/feedback', $feedbackData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'rating',
                        'feedback_type',
                        'comments',
                        'created_at'
                    ]
                ]);

        // Verify feedback is recorded
        $this->assertDatabaseHas('customer_feedback', [
            'order_id' => $this->order->id,
            'rating' => 4,
            'feedback_type' => 'overall',
        ]);
        
        // Check that would_recommend is stored in feedback_details JSON
        $feedback = \App\Models\CustomerFeedback::where('order_id', $this->order->id)->first();
        $this->assertNotNull($feedback);
        $this->assertTrue($feedback->feedback_details['would_recommend']);
    }

    /** @test */
    public function it_escalates_high_priority_complaints()
    {
        Sanctum::actingAs($this->customerService);

        $highPriorityComplaint = [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'complaint_type' => 'food_safety',
            'severity' => 'critical',
            'description' => 'Found foreign object in food',
            'requested_resolution' => 'immediate_refund',
            'contact_preference' => 'phone'
        ];

        $response = $this->postJson('/api/customer-service/complaints', $highPriorityComplaint);

        $response->assertStatus(201);

        // Verify complaint is escalated
        $this->assertDatabaseHas('customer_complaints', [
            'order_id' => $this->order->id,
            'severity' => 'critical',
            'status' => 'escalated'
        ]);

        // Verify escalation notification is sent
        $this->assertDatabaseHas('notifications', [
            'type' => 'App\Notifications\ComplaintEscalationNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->branchManager->id
        ]);
    }

    /** @test */
    public function it_tracks_customer_service_interactions()
    {
        Sanctum::actingAs($this->customerService);

        $interactionData = [
            'customer_id' => $this->customer->id,
            'interaction_type' => 'phone_call',
            'duration_minutes' => 15,
            'topic' => 'order_modification',
            'resolution' => 'order_updated',
            'satisfaction_rating' => 5,
            'notes' => 'Customer wanted to add extra items to order'
        ];

        $response = $this->postJson('/api/customer-service/interactions', $interactionData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'customer_id',
                        'interaction_type',
                        'duration_minutes',
                        'topic',
                        'resolution',
                        'satisfaction_rating',
                        'created_at'
                    ]
                ]);

        // Verify interaction is logged
        $this->assertDatabaseHas('customer_service_interactions', [
            'customer_id' => $this->customer->id,
            'interaction_type' => 'phone_call',
            'topic' => 'order_modification',
            'resolution' => 'order_updated'
        ]);
    }

    /** @test */
    public function it_handles_customer_refund_requests()
    {
        Sanctum::actingAs($this->branchManager);

        $refundRequest = [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'refund_reason' => 'food_quality_issue',
            'refund_amount' => 15.00,
            'refund_type' => 'partial',
            'refund_method' => 'original_payment',
            'description' => 'Customer reported cold food',
            'evidence_provided' => true
        ];

        $response = $this->postJson('/api/customer-service/refunds', $refundRequest);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'refund_reason',
                        'refund_amount',
                        'refund_type',
                        'approval_status',
                        'created_at'
                    ]
                ]);

        // Verify refund request is created
        $this->assertDatabaseHas('customer_refunds', [
            'order_id' => $this->order->id,
            'refund_reason' => 'food_quality_issue',
            'refund_amount' => 15.00,
            'approval_status' => 'pending'
        ]);
    }

    /** @test */
    public function it_manages_customer_preferences()
    {
        Sanctum::actingAs($this->customerService);

        $preferencesData = [
            'customer_id' => $this->customer->id,
            'dietary_restrictions' => ['vegetarian', 'gluten_free'],
            'allergies' => ['nuts', 'shellfish'],
            'preferred_contact_method' => 'email',
            'delivery_preferences' => [
                'contactless' => true,
                'instructions' => 'Leave at door, no contact'
            ],
            'marketing_preferences' => [
                'email' => true,
                'sms' => false,
                'push' => true
            ]
        ];

        $response = $this->putJson("/api/customers/{$this->customer->id}/preferences", $preferencesData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'dietary_restrictions',
                        'allergies',
                        'preferred_contact_method',
                        'marketing_preferences',
                        'delivery_preferences'
                    ]
                ]);

        // Verify preferences are updated
        $this->customer->refresh();
        $this->assertEquals(['vegetarian', 'gluten_free'], $this->customer->preferences['dietary_restrictions']);
        $this->assertEquals(['nuts', 'shellfish'], $this->customer->preferences['allergies']);
    }

    /** @test */
    public function it_handles_customer_support_ticket_creation()
    {
        Sanctum::actingAs($this->customer);

        $ticketData = [
            'customer_id' => $this->customer->id,
            'ticket_type' => 'technical',
            'priority' => 'medium',
            'subject' => 'App not loading properly',
            'description' => 'The app crashes when I try to place an order',
            'contact_preference' => 'email'
        ];

        $response = $this->postJson('/api/customer/support-tickets', $ticketData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'ticket_number',
                        'ticket_type',
                        'priority',
                        'subject',
                        'status',
                        'created_at'
                    ]
                ]);

        // Verify support ticket is created
        $this->assertDatabaseHas('customer_support_tickets', [
            'customer_id' => $this->customer->id,
            'ticket_type' => 'technical',
            'priority' => 'medium',
            'status' => 'open'
        ]);
    }

    /** @test */
    public function it_tracks_customer_satisfaction_metrics()
    {
        Sanctum::actingAs($this->customerService);

        // Clean up any existing feedback for this restaurant to ensure clean test
        DB::table('customer_feedback')->where('restaurant_id', $this->restaurant->id)->delete();

        // Create multiple feedback entries
        $feedbacks = [
            ['rating' => 5, 'feedback_type' => 'overall'],
            ['rating' => 4, 'feedback_type' => 'delivery'],
            ['rating' => 3, 'feedback_type' => 'food_quality'],
            ['rating' => 5, 'feedback_type' => 'service']
        ];

        foreach ($feedbacks as $feedback) {
            $this->postJson('/api/customer/feedback', [
                'order_id' => $this->order->id,
                'rating' => $feedback['rating'],
                'feedback_type' => $feedback['feedback_type'],
                'comments' => 'Test feedback'
            ]);
        }

        // Get satisfaction metrics for this specific restaurant
        $response = $this->getJson('/api/customer-service/satisfaction-metrics?restaurant_id=' . $this->restaurant->id);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'average_rating',
                        'total_feedback_count',
                        'rating_distribution',
                        'feedback_by_type',
                        'trend_over_time'
                    ]
                ]);

        $responseData = $response->json('data');
        $this->assertEquals(4.25, $responseData['average_rating']); // (5+4+3+5)/4
        $this->assertEquals(4, $responseData['total_feedback_count']);
    }

    /** @test */
    public function it_handles_customer_compensation_requests()
    {
        Sanctum::actingAs($this->branchManager);

        $compensationData = [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'compensation_type' => 'discount',
            'reason' => 'delivery_delay',
            'compensation_value' => '5.00',
            'valid_until' => now()->addDays(30),
            'terms_conditions' => 'Valid for next order only'
        ];

        $response = $this->postJson('/api/customer-service/compensations', $compensationData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'compensation_type',
                        'reason',
                        'compensation_value',
                        'approval_status',
                        'created_at'
                    ]
                ]);

        // Verify compensation is created
        $this->assertDatabaseHas('customer_compensations', [
            'order_id' => $this->order->id,
            'compensation_type' => 'discount',
            'compensation_value' => '5.00',
            'approval_status' => 'pending'
        ]);
    }

    /** @test */
    public function it_logs_customer_service_activities()
    {
        Sanctum::actingAs($this->customerService);

        $activityData = [
            'customer_id' => $this->customer->id,
            'activity_type' => 'complaint_resolution',
            'description' => 'Resolved food quality complaint with refund',
            'duration_minutes' => 20,
            'outcome' => 'customer_satisfied',
            'follow_up_required' => false,
            'internal_notes' => 'Customer was very understanding'
        ];

        $response = $this->postJson('/api/customer-service/activities', $activityData);

        $response->assertStatus(201);

        // Verify activity is logged
        $this->assertDatabaseHas('customer_service_activities', [
            'customer_id' => $this->customer->id,
            'activity_type' => 'complaint_resolution',
            'outcome' => 'customer_satisfied'
        ]);

        // Verify security log entry
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'customer_service_activity',
            'user_id' => $this->customerService->id,
            'target_type' => 'App\Models\Customer',
            'target_id' => $this->customer->id
        ]);
    }
} 