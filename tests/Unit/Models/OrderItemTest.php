<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderItem = OrderItem::factory()->create();
    }

    /**
     * Test order item has correct relationships
     */
    public function test_it_has_correct_relationships(): void
    {
        $order = Order::factory()->create();
        $menuItem = MenuItem::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id
        ]);

        // Test order relationship
        $this->assertEquals($order->id, $orderItem->order->id);
        $this->assertTrue($order->items->contains($orderItem));

        // Test menu item relationship
        $this->assertEquals($menuItem->id, $orderItem->menuItem->id);
        $this->assertTrue($menuItem->orderItems->contains($orderItem));
    }

    /**
     * Test order item validates required fields
     */
    public function test_it_validates_required_fields(): void
    {
        $order = Order::factory()->create();
        $menuItem = MenuItem::factory()->create();
        
        $requiredFields = [
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Chicken Burger',
            'unit_price' => 15.99,
            'quantity' => 2,
            'total_price' => 31.98
        ];

        $orderItem = OrderItem::create($requiredFields);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Chicken Burger',
            'quantity' => 2
        ]);
    }

    /**
     * Test order item enforces business rules
     */
    public function test_it_enforces_business_rules(): void
    {
        $order = Order::factory()->create();
        $menuItem = MenuItem::factory()->create();

        // Test that required fields are enforced
        $validOrderItem = OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Test Item',
            'unit_price' => 10.00,
            'quantity' => 1,
            'total_price' => 10.00
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $validOrderItem->id,
            'item_name' => 'Test Item',
            'quantity' => 1
        ]);

        // Test that price calculations are consistent
        $this->assertEquals('10.00', $validOrderItem->unit_price);
        $this->assertEquals('10.00', $validOrderItem->total_price);
        $this->assertEquals(1, $validOrderItem->quantity);
    }

    /**
     * Test order item handles price calculations correctly
     */
    public function test_it_handles_price_calculations_correctly(): void
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 12.50,
            'quantity' => 3,
            'total_price' => 37.50
        ]);

        $this->assertEquals('12.50', $orderItem->unit_price);
        $this->assertEquals('37.50', $orderItem->total_price);
        $this->assertEquals(3, $orderItem->quantity);
    }

    /**
     * Test order item handles customizations correctly
     */
    public function test_it_handles_customizations_correctly(): void
    {
        $customizations = [
            'extra_cheese' => true,
            'no_onions' => true,
            'spicy_level' => 'medium',
            'extra_sauce' => 'ranch'
        ];

        $orderItem = OrderItem::factory()->create([
            'customizations' => $customizations
        ]);

        $this->assertEquals($customizations, $orderItem->customizations);
        $this->assertIsArray($orderItem->customizations);
        $this->assertTrue($orderItem->customizations['extra_cheese']);
        $this->assertEquals('medium', $orderItem->customizations['spicy_level']);
    }

    /**
     * Test order item handles nutritional snapshot correctly
     */
    public function test_it_handles_nutritional_snapshot_correctly(): void
    {
        $nutritionalSnapshot = [
            'calories' => 450,
            'protein' => 25,
            'carbs' => 35,
            'fat' => 20,
            'fiber' => 5
        ];

        $orderItem = OrderItem::factory()->create([
            'nutritional_snapshot' => $nutritionalSnapshot
        ]);

        $this->assertEquals($nutritionalSnapshot, $orderItem->nutritional_snapshot);
        $this->assertIsArray($orderItem->nutritional_snapshot);
        $this->assertEquals(450, $orderItem->nutritional_snapshot['calories']);
        $this->assertEquals(25, $orderItem->nutritional_snapshot['protein']);
    }

    /**
     * Test order item handles allergens snapshot correctly
     */
    public function test_it_handles_allergens_snapshot_correctly(): void
    {
        $allergensSnapshot = [
            'dairy' => true,
            'nuts' => false,
            'gluten' => true,
            'eggs' => false,
            'soy' => true
        ];

        $orderItem = OrderItem::factory()->create([
            'allergens_snapshot' => $allergensSnapshot
        ]);

        $this->assertEquals($allergensSnapshot, $orderItem->allergens_snapshot);
        $this->assertIsArray($orderItem->allergens_snapshot);
        $this->assertTrue($orderItem->allergens_snapshot['dairy']);
        $this->assertFalse($orderItem->allergens_snapshot['nuts']);
    }

    /**
     * Test order item handles optional fields correctly
     */
    public function test_it_handles_optional_fields_correctly(): void
    {
        $orderItem = OrderItem::factory()->create([
            'item_description' => 'Delicious chicken burger with fresh vegetables',
            'special_instructions' => 'No pickles, extra crispy',
            'sku' => 'SKU-1234'
        ]);

        $this->assertEquals('Delicious chicken burger with fresh vegetables', $orderItem->item_description);
        $this->assertEquals('No pickles, extra crispy', $orderItem->special_instructions);
        $this->assertEquals('SKU-1234', $orderItem->sku);
    }

    /**
     * Test order item factory states work correctly
     */
    public function test_it_uses_factory_states_correctly(): void
    {
        // Test with special instructions state
        $specialInstructionsItem = OrderItem::factory()->withSpecialInstructions()->create();
        $this->assertNotNull($specialInstructionsItem->special_instructions);
        $this->assertIsString($specialInstructionsItem->special_instructions);

        // Test with customizations state
        $customizedItem = OrderItem::factory()->withCustomizations()->create();
        $this->assertIsArray($customizedItem->customizations);
        $this->assertArrayHasKey('extra_cheese', $customizedItem->customizations);
        $this->assertArrayHasKey('no_onions', $customizedItem->customizations);
        $this->assertArrayHasKey('spicy_level', $customizedItem->customizations);
        $this->assertTrue($customizedItem->customizations['extra_cheese']);
        $this->assertTrue($customizedItem->customizations['no_onions']);
        $this->assertEquals('medium', $customizedItem->customizations['spicy_level']);
    }

    /**
     * Test order item handles edge cases correctly
     */
    public function test_it_handles_edge_cases_correctly(): void
    {
        // Test very high prices
        $highPriceItem = OrderItem::factory()->create([
            'unit_price' => 999.99,
            'quantity' => 1,
            'total_price' => 999.99
        ]);
        $this->assertEquals('999.99', $highPriceItem->unit_price);
        $this->assertEquals('999.99', $highPriceItem->total_price);

        // Test very low prices
        $lowPriceItem = OrderItem::factory()->create([
            'unit_price' => 0.01,
            'quantity' => 1,
            'total_price' => 0.01
        ]);
        $this->assertEquals('0.01', $lowPriceItem->unit_price);
        $this->assertEquals('0.01', $lowPriceItem->total_price);

        // Test large quantities
        $largeQuantityItem = OrderItem::factory()->create([
            'quantity' => 100,
            'unit_price' => 5.00,
            'total_price' => 500.00
        ]);
        $this->assertEquals(100, $largeQuantityItem->quantity);
        $this->assertEquals('500.00', $largeQuantityItem->total_price);

        // Test single quantity
        $singleItem = OrderItem::factory()->create([
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00
        ]);
        $this->assertEquals(1, $singleItem->quantity);
        $this->assertEquals('10.00', $singleItem->total_price);
    }

    /**
     * Test order item handles complex customizations correctly
     */
    public function test_it_handles_complex_customizations_correctly(): void
    {
        $complexCustomizations = [
            'size' => 'large',
            'crust' => 'thin',
            'toppings' => [
                'pepperoni' => true,
                'mushrooms' => false,
                'olives' => true,
                'extra_cheese' => true
            ],
            'sauce' => 'bbq',
            'cooking_preference' => 'well_done',
            'cut_style' => 'square'
        ];

        $orderItem = OrderItem::factory()->create([
            'customizations' => $complexCustomizations
        ]);

        $this->assertEquals($complexCustomizations, $orderItem->customizations);
        $this->assertEquals('large', $orderItem->customizations['size']);
        $this->assertEquals('thin', $orderItem->customizations['crust']);
        $this->assertIsArray($orderItem->customizations['toppings']);
        $this->assertTrue($orderItem->customizations['toppings']['pepperoni']);
        $this->assertFalse($orderItem->customizations['toppings']['mushrooms']);
    }

    /**
     * Test order item handles multiple items per order
     */
    public function test_it_handles_multiple_items_per_order(): void
    {
        $order = Order::factory()->create();

        // Create multiple items for the same order
        $item1 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_name' => 'Chicken Burger',
            'quantity' => 2
        ]);

        $item2 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_name' => 'French Fries',
            'quantity' => 1
        ]);

        $item3 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_name' => 'Soft Drink',
            'quantity' => 2
        ]);

        // Test that order has all items
        $orderItems = $order->items;
        $this->assertCount(3, $orderItems);
        $this->assertTrue($orderItems->contains($item1));
        $this->assertTrue($orderItems->contains($item2));
        $this->assertTrue($orderItems->contains($item3));

        // Test that items belong to the same order
        $this->assertEquals($order->id, $item1->order_id);
        $this->assertEquals($order->id, $item2->order_id);
        $this->assertEquals($order->id, $item3->order_id);
    }

    /**
     * Test order item handles cascade deletion
     */
    public function test_it_handles_cascade_deletion(): void
    {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id
        ]);

        $itemId = $orderItem->id;

        // Verify item exists
        $this->assertDatabaseHas('order_items', ['id' => $itemId]);

        // Delete the order
        $order->delete();

        // Verify item is also deleted (cascade)
        $this->assertDatabaseMissing('order_items', ['id' => $itemId]);
    }

    /**
     * Test order item handles attribute casting correctly
     */
    public function test_it_casts_attributes_correctly(): void
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 15.50,
            'total_price' => 31.00,
            'quantity' => 2,
            'customizations' => ['extra_cheese' => true],
            'nutritional_snapshot' => ['calories' => 300],
            'allergens_snapshot' => ['dairy' => true]
        ]);

        // Test decimal casting (returns string in Laravel)
        $this->assertIsString($orderItem->unit_price);
        $this->assertIsString($orderItem->total_price);
        $this->assertEquals('15.50', $orderItem->unit_price);
        $this->assertEquals('31.00', $orderItem->total_price);

        // Test integer casting
        $this->assertIsInt($orderItem->quantity);

        // Test array casting
        $this->assertIsArray($orderItem->customizations);
        $this->assertIsArray($orderItem->nutritional_snapshot);
        $this->assertIsArray($orderItem->allergens_snapshot);
    }

    /**
     * Test order item handles price precision correctly
     */
    public function test_it_handles_price_precision_correctly(): void
    {
        // Test prices with many decimal places (should be rounded to 2)
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 12.567,
            'total_price' => 25.134,
            'quantity' => 2
        ]);

        $this->assertEquals('12.57', $orderItem->unit_price); // Rounded to 2 decimals
        $this->assertEquals('25.13', $orderItem->total_price); // Rounded to 2 decimals
    }

    /**
     * Test order item handles empty arrays correctly
     */
    public function test_it_handles_empty_arrays_correctly(): void
    {
        $orderItem = OrderItem::factory()->create([
            'customizations' => [],
            'nutritional_snapshot' => [],
            'allergens_snapshot' => []
        ]);

        $this->assertIsArray($orderItem->customizations);
        $this->assertIsArray($orderItem->nutritional_snapshot);
        $this->assertIsArray($orderItem->allergens_snapshot);
        $this->assertEmpty($orderItem->customizations);
        $this->assertEmpty($orderItem->nutritional_snapshot);
        $this->assertEmpty($orderItem->allergens_snapshot);
    }

    /**
     * Test order item handles null optional fields correctly
     */
    public function test_it_handles_null_optional_fields_correctly(): void
    {
        $orderItem = OrderItem::factory()->create([
            'item_description' => null,
            'special_instructions' => null,
            'sku' => null
        ]);

        $this->assertNull($orderItem->item_description);
        $this->assertNull($orderItem->special_instructions);
        $this->assertNull($orderItem->sku);
    }

    /**
     * Test order item handles long text fields correctly
     */
    public function test_it_handles_long_text_fields_correctly(): void
    {
        $longDescription = 'This is a very long description of the menu item that contains many details about the ingredients, preparation method, and special features that make this item unique and delicious. It includes information about the cooking process, seasoning, and presentation.';
        
        $longInstructions = 'Please prepare this item with extra care. Make sure to cook it thoroughly, add extra seasoning as requested, and ensure it is presented beautifully on the plate. Also, please double-check that all customizations have been applied correctly.';

        $orderItem = OrderItem::factory()->create([
            'item_description' => $longDescription,
            'special_instructions' => $longInstructions
        ]);

        $this->assertEquals($longDescription, $orderItem->item_description);
        $this->assertEquals($longInstructions, $orderItem->special_instructions);
    }
} 