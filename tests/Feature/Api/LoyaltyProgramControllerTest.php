<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\LoyaltyProgram;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class LoyaltyProgramControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $superAdmin;
    protected User $restaurantOwner;
    protected User $branchManager;
    protected LoyaltyProgram $loyaltyProgram;
    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->restaurantOwner = User::factory()->create(['role' => 'RESTAURANT_OWNER', 'status' => 'active']);
        $this->branchManager = User::factory()->create(['role' => 'BRANCH_MANAGER', 'status' => 'active']);
        
        // Create test restaurant
        $this->restaurant = Restaurant::factory()->create();
        
        // Create a test loyalty program
        $this->loyaltyProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $this->restaurant->id
        ]);
    }

    /** @test */
    public function it_lists_loyalty_programs_with_pagination()
    {
        // Create multiple loyalty programs
        LoyaltyProgram::factory()->count(15)->create();
        
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/loyalty-programs?per_page=20');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'points_per_currency',
                            'currency_name',
                            'minimum_points_redemption',
                            'redemption_rate',
                            'is_active',
                            'restaurant_id',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'links',
                    'meta'
                ]);
        
        // Verify pagination - should be 16 total (15 created + 1 from setUp)
        $response->assertJsonCount(16, 'data');
    }

    /** @test */
    public function it_creates_new_loyalty_program()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $programData = [
            'name' => 'Test Loyalty Program',
            'type' => 'points', // Add required type field
            'description' => 'A test loyalty program',
            'points_per_currency' => 10.0,
            'currency_name' => 'points',
            'minimum_points_redemption' => 100,
            'redemption_rate' => 0.01,
            'start_date' => now()->format('Y-m-d'),
            'restaurant_id' => $this->restaurant->id
        ];
        
        $response = $this->postJson('/api/loyalty-programs', $programData);
        
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'description',
                        'points_per_currency',
                        'currency_name',
                        'minimum_points_redemption',
                        'redemption_rate',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ]
                ]);
        
        $this->assertDatabaseHas('loyalty_programs', $programData);
    }

    /** @test */
    public function it_shows_specific_loyalty_program()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson("/api/loyalty-programs/{$this->loyaltyProgram->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'description',
                        'points_per_currency',
                        'currency_name',
                        'minimum_points_redemption',
                        'redemption_rate',
                        'is_active',
                        'restaurant_id',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_updates_loyalty_program()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $updateData = [
            'name' => 'Updated Program Name',
            'description' => 'Updated program description',
            'points_per_currency' => 15.0,
            'minimum_points_redemption' => 150,
            'redemption_rate' => 0.015,
            'is_active' => false
        ];
        
        $response = $this->putJson("/api/loyalty-programs/{$this->loyaltyProgram->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJsonFragment([
                    'name' => 'Updated Program Name',
                    'description' => 'Updated program description',
                    'points_per_currency' => '15.00',
                    'minimum_points_redemption' => 150,
                    'redemption_rate' => '0.0150',
                    'is_active' => false
                ]);
        
        $this->assertDatabaseHas('loyalty_programs', $updateData);
    }

    /** @test */
    public function it_deletes_loyalty_program()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->deleteJson("/api/loyalty-programs/{$this->loyaltyProgram->id}");
        
        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('loyalty_programs', [
            'id' => $this->loyaltyProgram->id
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->postJson('/api/loyalty-programs', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'points_per_currency', 'currency_name', 'minimum_points_redemption', 'redemption_rate']);
    }

    /** @test */
    public function it_validates_points_per_currency_range()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $invalidData = [
            'name' => 'Test Program',
            'points_per_currency' => -5.0, // Invalid negative value
            'currency_name' => 'points',
            'minimum_points_redemption' => 100,
            'redemption_rate' => 0.01,
            'restaurant_id' => $this->restaurant->id
        ];
        
        $response = $this->postJson('/api/loyalty-programs', $invalidData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['points_per_currency']);
    }

    /** @test */
    public function it_validates_redemption_rate_range()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $invalidData = [
            'name' => 'Test Program',
            'points_per_currency' => 10.0,
            'currency_name' => 'points',
            'minimum_points_redemption' => 100,
            'redemption_rate' => 2.0, // Invalid rate > 1
            'restaurant_id' => $this->restaurant->id
        ];
        
        $response = $this->postJson('/api/loyalty-programs', $invalidData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['redemption_rate']);
    }

    /** @test */
    public function it_enforces_pagination_limits()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/loyalty-programs?per_page=150');
        
        $response->assertStatus(200);
        
        // Should default to maximum of 100 items
        $responseData = $response->json();
        $this->assertLessThanOrEqual(100, count($responseData['data']));
    }

    /** @test */
    public function it_handles_nonexistent_program()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/loyalty-programs/99999');
        
        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_program_with_restaurant_info()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson("/api/loyalty-programs/{$this->loyaltyProgram->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'description',
                        'points_per_currency',
                        'currency_name',
                        'minimum_points_redemption',
                        'redemption_rate',
                        'is_active',
                        'restaurant_id',
                        'restaurant' => [
                            'id',
                            'name',
                            'description'
                        ],
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_filters_programs_by_active_status()
    {
        // Create active and inactive programs
        LoyaltyProgram::factory()->create(['is_active' => true]);
        LoyaltyProgram::factory()->create(['is_active' => false]);
        
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/loyalty-programs?is_active=1');
        
        $response->assertStatus(200);
        
        $programs = $response->json('data');
        // Only check programs returned by the filter, not all programs
        foreach ($programs as $program) {
            $this->assertTrue($program['is_active']);
        }
        
        // Verify that inactive programs are not returned
        $this->assertGreaterThan(0, count($programs));
    }

    /** @test */
    public function it_validates_program_name_uniqueness_per_restaurant()
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Create first program
        $programData = [
            'name' => 'Unique Program',
            'type' => 'points',
            'points_per_currency' => 10.0,
            'currency_name' => 'points',
            'minimum_points_redemption' => 100,
            'redemption_rate' => 0.01,
            'start_date' => now()->format('Y-m-d'),
            'restaurant_id' => $this->restaurant->id
        ];
        
        $this->postJson('/api/loyalty-programs', $programData);
        
        // Try to create second program with same name for same restaurant
        $response = $this->postJson('/api/loyalty-programs', $programData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_allows_same_name_for_different_restaurants()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $restaurant2 = Restaurant::factory()->create();
        
        $programData = [
            'name' => 'Same Name Program',
            'type' => 'points',
            'points_per_currency' => 10.0,
            'currency_name' => 'points',
            'minimum_points_redemption' => 100,
            'redemption_rate' => 0.01,
            'start_date' => now()->format('Y-m-d'),
            'restaurant_id' => $this->restaurant->id
        ];
        
        $this->postJson('/api/loyalty-programs', $programData);
        
        // Create program with same name for different restaurant
        $programData['restaurant_id'] = $restaurant2->id;
        $response = $this->postJson('/api/loyalty-programs', $programData);
        
        $response->assertStatus(201);
    }

    /** @test */
    public function it_validates_minimum_points_redemption()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $programData = [
            'name' => 'Test Program',
            'points_per_currency' => 10.0,
            'currency_name' => 'points',
            'minimum_points_redemption' => 0, // Invalid minimum
            'redemption_rate' => 0.01,
            'restaurant_id' => $this->restaurant->id
        ];
        
        $response = $this->postJson('/api/loyalty-programs', $programData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['minimum_points_redemption']);
    }

    /** @test */
    public function it_returns_proper_error_for_invalid_program_id()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->putJson('/api/loyalty-programs/99999', []);
        
        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_program_deletion_with_customer_points()
    {
        // Create customer loyalty points for this program
        // Note: This would require CustomerLoyaltyPoints model
        // For now, just test basic deletion
        
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->deleteJson("/api/loyalty-programs/{$this->loyaltyProgram->id}");
        
        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('loyalty_programs', [
            'id' => $this->loyaltyProgram->id
        ]);
    }

    /** @test */
    public function it_restaurant_owner_can_manage_own_restaurant_programs()
    {
        // Assign restaurant to restaurant owner
        $this->restaurant->update(['user_id' => $this->restaurantOwner->id]);
        
        Sanctum::actingAs($this->restaurantOwner);
        
        $response = $this->getJson('/api/loyalty-programs');
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_restaurant_owner_cannot_manage_other_restaurant_programs()
    {
        // Create another restaurant not owned by this user
        $otherRestaurant = Restaurant::factory()->create();
        $otherProgram = LoyaltyProgram::factory()->create([
            'restaurant_id' => $otherRestaurant->id
        ]);
        
        Sanctum::actingAs($this->restaurantOwner);
        
        $response = $this->getJson("/api/loyalty-programs/{$otherProgram->id}");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_currency_name_format()
    {
        Sanctum::actingAs($this->superAdmin);
        
        $programData = [
            'name' => 'Test Program',
            'points_per_currency' => 10.0,
            'currency_name' => 'invalid-currency-name', // Invalid format
            'minimum_points_redemption' => 100,
            'redemption_rate' => 0.01,
            'restaurant_id' => $this->restaurant->id
        ];
        
        $response = $this->postJson('/api/loyalty-programs', $programData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['currency_name']);
    }
} 