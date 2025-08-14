<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BasicDiagnosticTest extends TestCase
{
    public function test_basic_php_functionality()
    {
        $this->assertTrue(true, 'Basic assertion should work');
    }

    public function test_math_operations()
    {
        $this->assertEquals(4, 2 + 2, 'Basic math should work');
    }

    public function test_string_operations()
    {
        $this->assertEquals('hello world', 'hello' . ' ' . 'world', 'String concatenation should work');
    }

    public function test_array_operations()
    {
        $array = [1, 2, 3];
        $this->assertCount(3, $array, 'Array count should work');
    }

    public function test_class_exists()
    {
        $this->assertTrue(class_exists('PHPUnit\Framework\TestCase'), 'PHPUnit should be available');
    }
}
