<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\StampCard;
use App\Models\StampHistory;
use App\Services\StampCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StampCardServiceTest extends TestCase
{
    use RefreshDatabase;

    private StampCardService $stampCardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stampCardService = new StampCardService();
    }

    #[Test]
    public function it_checks_stamp_card_completion_correctly()
    {
        $card = StampCard::factory()->create([
            'stamps_earned' => 10,
            'stamps_required' => 10,
        ]);

        $this->assertTrue($this->stampCardService->checkStampCardCompletion($card));

        $card->update(['stamps_earned' => 9]);
        $this->assertFalse($this->stampCardService->checkStampCardCompletion($card));
    }

    #[Test]
    public function it_adds_stamps_to_general_card_for_any_order()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $card = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_GENERAL,
            'stamps_earned' => 0,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 25.00,
        ]);

        // Add order item
        $menuItem = MenuItem::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'total_price' => 25.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $card->refresh();
        $this->assertGreaterThan(0, $card->stamps_earned);
        $this->assertTrue(StampHistory::where('stamp_card_id', $card->id)->exists());
    }

    #[Test]
    public function it_adds_stamps_to_beverage_card_only_for_beverage_orders()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $restaurant = Restaurant::factory()->create();
        
        // Create beverage category
        $beverageCategory = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);

        $beverageItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $beverageCategory->id,
            'name' => 'Coffee',
        ]);

        $card = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_BEVERAGES,
            'stamps_earned' => 0,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 15.00,
        ]);

        // Add beverage item to order
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $beverageItem->id,
            'item_name' => $beverageItem->name,
            'total_price' => 5.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $card->refresh();
        $this->assertGreaterThan(0, $card->stamps_earned);
    }

    #[Test]
    public function it_does_not_add_stamps_to_beverage_card_for_non_beverage_orders()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $restaurant = Restaurant::factory()->create();
        
        // Create main course category
        $mainCategory = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main Courses',
            'slug' => 'main-courses',
        ]);

        $mainItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $mainCategory->id,
            'name' => 'Burger',
        ]);

        $card = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_BEVERAGES,
            'stamps_earned' => 0,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 15.00,
        ]);

        // Add main course item to order
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $mainItem->id,
            'item_name' => $mainItem->name,
            'total_price' => 15.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $card->refresh();
        $this->assertEquals(0, $card->stamps_earned);
    }

    #[Test]
    public function it_completes_stamp_card_when_required_stamps_are_earned()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $card = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_GENERAL,
            'stamps_earned' => 9,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 25.00,
        ]);

        // Add order item
        $menuItem = MenuItem::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'total_price' => 25.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $card->refresh();
        $this->assertTrue($card->is_completed);
        $this->assertNotNull($card->completed_at);
        
        // Check that completion history was created
        $completionHistory = StampHistory::where('stamp_card_id', $card->id)
            ->where('action_type', StampHistory::ACTION_CARD_COMPLETED)
            ->first();
        $this->assertNotNull($completionHistory);
    }

    #[Test]
    public function it_creates_stamp_card_with_correct_defaults()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();

        $card = $this->stampCardService->createStampCard(
            $customer,
            $loyaltyProgram,
            StampCard::TYPE_BEVERAGES
        );

        $this->assertEquals($customer->id, $card->customer_id);
        $this->assertEquals($loyaltyProgram->id, $card->loyalty_program_id);
        $this->assertEquals(StampCard::TYPE_BEVERAGES, $card->card_type);
        $this->assertEquals(10, $card->stamps_required);
        $this->assertEquals(0, $card->stamps_earned);
        $this->assertFalse($card->is_completed);
        $this->assertTrue($card->is_active);
        $this->assertNotNull($card->reward_description);
        $this->assertNotNull($card->reward_value);
    }

    #[Test]
    public function it_does_not_process_inactive_or_completed_cards()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        
        $inactiveCard = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_GENERAL,
            'stamps_earned' => 0,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => false,
        ]);

        $completedCard = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_GENERAL,
            'stamps_earned' => 10,
            'stamps_required' => 10,
            'is_completed' => true,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 25.00,
        ]);

        // Add order item
        $menuItem = MenuItem::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'total_price' => 25.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $inactiveCard->refresh();
        $completedCard->refresh();
        
        $this->assertEquals(0, $inactiveCard->stamps_earned);
        $this->assertEquals(10, $completedCard->stamps_earned); // Should remain unchanged
    }

    #[Test]
    public function it_calculates_stamps_based_on_order_amount()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $card = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_GENERAL,
            'stamps_earned' => 0,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 20.00,
        ]);

        // Add order item
        $menuItem = MenuItem::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'total_price' => 20.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $card->refresh();
        $this->assertEquals(2, $card->stamps_earned);
    }

    #[Test]
    public function it_identifies_healthy_items_correctly()
    {
        $customer = Customer::factory()->create();
        $loyaltyProgram = LoyaltyProgram::factory()->create();
        $restaurant = Restaurant::factory()->create();
        
        // Create healthy item
        $healthyItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Salad',
            'dietary_tags' => ['vegetarian', 'healthy'],
        ]);

        $card = StampCard::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => StampCard::TYPE_HEALTHY,
            'stamps_earned' => 0,
            'stamps_required' => 10,
            'is_completed' => false,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 15.00,
        ]);

        // Add healthy item to order
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $healthyItem->id,
            'item_name' => $healthyItem->name,
            'total_price' => 15.00,
        ]);

        $this->stampCardService->addStampToCard($order);

        $card->refresh();
        $this->assertGreaterThan(0, $card->stamps_earned);
    }
} 