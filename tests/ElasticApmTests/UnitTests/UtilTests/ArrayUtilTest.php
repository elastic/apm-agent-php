<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\ArrayUtil;
use PHPUnit\Framework\TestCase;

class ArrayUtilTest extends TestCase
{
    public function testIsList(): void
    {
        $this->assertTrue(ArrayUtil::isList([]));
        $this->assertTrue(ArrayUtil::isList(['val_A']));
        $this->assertTrue(ArrayUtil::isList([0 => 'val_A']));
        $this->assertTrue(ArrayUtil::isList(['val_A', 'val_B']));
        $this->assertTrue(ArrayUtil::isList([0 => 'val_A', 1 => 'val_B']));
        $this->assertTrue(ArrayUtil::isList([0 => 'val_A', 'val_B']));
        $this->assertTrue(ArrayUtil::isList(['val_A', 1 => 'val_B']));

        $this->assertFalse(ArrayUtil::isList(['A' => 'val_A']));
        $this->assertFalse(ArrayUtil::isList([1 => 'val_B']));
        $this->assertFalse(ArrayUtil::isList([1 => 'val_B']));
        $this->assertFalse(ArrayUtil::isList([1 => 'val_B', 0 => 'val_A']));
        $this->assertFalse(ArrayUtil::isList(['A' => 'val_A', 1 => 'val_B']));
        $this->assertFalse(ArrayUtil::isList([0 => 'val_A', 'B' => 'val_B']));
        $this->assertFalse(ArrayUtil::isList(['A' => 'val_A', 'val_B']));
        $this->assertFalse(ArrayUtil::isList(['val_A', 'B' => 'val_B']));
    }
}
