<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MultiRestaurantService;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\EnhancedPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class MultiRestaurantServiceTest extends TestCase
{
    use RefreshDatabase;

    private MultiRestaurantService $multiRestaurantService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->multiRestaurantService = new MultiRestaurantService();
    }

    /**
     * Test restaurant creation
     */
    public function test_it_creates_restaurant_successfully(): void
    {
        $data = [
            'name' => 'Test Restaurant',
            'description' => 'A test restaurant',
            'cuisine_type' => 'Italian',
            'phone' => '123-456-7890',
            'email' => 'test@restaurant.com',
        ];

        $restaurant = $this->multiRestaurantService->createRestaurant($data);

        $this->assertInstanceOf(Restaurant::class, $restaurant);
        $this->assertEquals('Test Restaurant', $restaurant->name);
        $this->assertEquals('test-restaurant', $restaurant->slug);
        $this->assertEquals('Italian', $restaurant->cuisine_type);
        $this->assertEquals('active', $restaurant->status);
    }

    /**
     * Test restaurant creation validation
     */
    public function test_it_validates_restaurant_data(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Restaurant name is required');

        $this->multiRestaurantService->createRestaurant([]);
    }

    /**
     * Test restaurant creation with duplicate name
     */
    public function test_it_prevents_duplicate_restaurant_names(): void
    {
        Restaurant::factory()->create(['name' => 'Test Restaurant']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Restaurant with this name already exists');

        $this->multiRestaurantService->createRestaurant(['name' => 'Test Restaurant']);
    }

    /**
     * Test restaurant update
     */
    public function test_it_updates_restaurant_successfully(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Old Name']);

        $data = [
            'name' => 'New Name',
            'description' => 'Updated description',
        ];

        $updatedRestaurant = $this->multiRestaurantService->updateRestaurant($restaurant, $data);

        $this->assertEquals('New Name', $updatedRestaurant->name);
        $this->assertEquals('new-name', $updatedRestaurant->slug);
        $this->assertEquals('Updated description', $updatedRestaurant->description);
    }

    /**
     * Test restaurant deletion
     */
    public function test_it_deletes_restaurant_successfully(): void
    {
        $restaurant = Restaurant::factory()->create();

        $result = $this->multiRestaurantService->deleteRestaurant($restaurant);

        $this->assertTrue($result);
        $this->assertEquals('inactive', $restaurant->fresh()->status);
    }

    /**
     * Test restaurant deletion with active orders
     */
    public function test_it_prevents_deletion_with_active_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        // Create active orders for the restaurant
        $customer = \App\Models\Customer::factory()->create();
        $restaurant->orders()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'order_number' => 'ORD-' . time(),
            'restaurant_branch_id' => RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id])->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete restaurant with 1 active orders');

        $this->multiRestaurantService->deleteRestaurant($restaurant);
    }

    /**
     * Test restaurant details retrieval
     */
    public function test_it_gets_restaurant_details(): void
    {
        $restaurant = Restaurant::factory()->create();

        $details = $this->multiRestaurantService->getRestaurantDetails($restaurant->id);

        $this->assertInstanceOf(Restaurant::class, $details);
        $this->assertEquals($restaurant->id, $details->id);
    }

    /**
     * Test branch creation
     */
    public function test_it_creates_branch_successfully(): void
    {
        $restaurant = Restaurant::factory()->create();

        $data = [
            'name' => 'Downtown Branch',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'USA',
        ];

        $branch = $this->multiRestaurantService->createBranch($restaurant->id, $data);

        $this->assertInstanceOf(RestaurantBranch::class, $branch);
        $this->assertEquals('Downtown Branch', $branch->name);
        $this->assertEquals($restaurant->id, $branch->restaurant_id);
        $this->assertEquals('downtown-branch', $branch->slug);
    }

    /**
     * Test branch creation validation
     */
    public function test_it_validates_branch_data(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Branch name is required');

        $this->multiRestaurantService->createBranch($restaurant->id, []);
    }

    /**
     * Test branch update
     */
    public function test_it_updates_branch_successfully(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Old Branch Name',
        ]);

        $data = [
            'name' => 'New Branch Name',
            'address' => '456 Oak St',
        ];

        $updatedBranch = $this->multiRestaurantService->updateBranch($branch, $data);

        $this->assertEquals('New Branch Name', $updatedBranch->name);
        $this->assertEquals('456 Oak St', $updatedBranch->address);
    }

    /**
     * Test branch deletion
     */
    public function test_it_deletes_branch_successfully(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $result = $this->multiRestaurantService->deleteBranch($branch);

        $this->assertTrue($result);
        $this->assertEquals('inactive', $branch->fresh()->status);
    }

    /**
     * Test branch deletion with active orders
     */
    public function test_it_prevents_branch_deletion_with_active_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        // Create active orders for the branch
        $customer = \App\Models\Customer::factory()->create();
        $branch->orders()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'order_number' => 'ORD-' . time(),
            'restaurant_id' => $branch->restaurant_id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete branch with 1 active orders');

        $this->multiRestaurantService->deleteBranch($branch);
    }

    /**
     * Test user assignment to restaurant
     */
    public function test_it_assigns_user_to_restaurant(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();

        $this->multiRestaurantService->assignUserToRestaurant($user, $restaurant->id);

        $this->assertEquals($restaurant->id, $user->fresh()->restaurant_id);
        $this->assertNull($user->fresh()->restaurant_branch_id);
    }

    /**
     * Test user assignment to branch
     */
    public function test_it_assigns_user_to_branch(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->multiRestaurantService->assignUserToBranch($user, $branch->id);

        $this->assertEquals($restaurant->id, $user->fresh()->restaurant_id);
        $this->assertEquals($branch->id, $user->fresh()->restaurant_branch_id);
    }

    /**
     * Test user removal from restaurant
     */
    public function test_it_removes_user_from_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->multiRestaurantService->removeUserFromRestaurant($user, $restaurant->id);

        $this->assertNull($user->fresh()->restaurant_id);
        $this->assertNull($user->fresh()->restaurant_branch_id);
    }

    /**
     * Test user removal validation
     */
    public function test_it_validates_user_removal(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant1->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User is not assigned to this restaurant');

        $this->multiRestaurantService->removeUserFromRestaurant($user, $restaurant2->id);
    }

    /**
     * Test permission checking
     */
    public function test_it_checks_permissions_correctly(): void
    {
        $user = User::factory()->create(['role' => 'CASHIER']);

        // Test without specific permissions
        $result = $this->multiRestaurantService->hasPermission($user, 'read');
        $this->assertFalse($result);

        // Test with super admin
        $superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $result = $this->multiRestaurantService->hasPermission($superAdmin, 'read');
        $this->assertTrue($result);
    }
} 