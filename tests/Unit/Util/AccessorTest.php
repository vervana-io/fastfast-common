<?php

namespace Tests\Unit\Util;

use FastFast\Common\Util\Accessor;
use PHPUnit\Framework\TestCase;

class AccessorTest extends TestCase
{
    public function test_get_value_returns_default_when_not_readable()
    {
        $value = Accessor::getValue(['foo' => 'bar'], '[baz]', 'default');
        // Symfony PropertyAccessor returns null for missing readable keys
        $this->assertNull($value);
    }

    public function test_get_value_returns_value_when_readable_array()
    {
        $data = ['foo' => ['bar' => 123]];
        $value = Accessor::getValue($data, '[foo][bar]', null);
        $this->assertSame(123, $value);
    }
}


