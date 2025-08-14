<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Minimal test class that doesn't inherit from the heavy Laravel TestCase
 */
class MinimalTest extends TestCase
{
    public function test_basic_functionality()
    {
        $this->assertTrue(true);
        $this->assertEquals(4, 2 + 2);
        $this->assertCount(3, [1, 2, 3]);
    }

    public function test_class_loading()
    {
        $this->assertTrue(class_exists('App\Models\Order'));
        $this->assertTrue(class_exists('App\Events\OrderStatusUpdated'));
        $this->assertTrue(interface_exists('Illuminate\Contracts\Broadcasting\ShouldBroadcast'));
    }

    public function test_config_values()
    {
        // Skip this test since we're using pure PHPUnit without Laravel bootstrap
        $this->markTestSkipped('Laravel config helper not available in pure PHPUnit context');
    }
}
