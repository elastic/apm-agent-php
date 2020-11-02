<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Elastic\Apm\Tests\Util\IterableUtilForTests;
use Elastic\Apm\Tests\Util\RangeUtilForTests;
use PHPUnit\Framework\TestCase;

class RangeUtilTest extends TestCase
{
    public function testGenerate(): void
    {
        /**
         * @param int $begin
         * @param int $end
         * @param int $step
         *
         * @return array<int>
         */
        $generateAsArray = function (int $begin, int $end, int $step = 1): array {
            return IterableUtilForTests::toArray(RangeUtilForTests::generate($begin, $end, $step));
        };

        self::assertEquals([], $generateAsArray(0, 0));
        self::assertEquals([], $generateAsArray(0, 0, 0));
        self::assertEquals([], $generateAsArray(0, 0, 1));
        self::assertEquals([], $generateAsArray(0, 0, -1));

        self::assertEquals([], $generateAsArray(100, 100));
        self::assertEquals([], $generateAsArray(100, 100, 0));
        self::assertEquals([], $generateAsArray(100, 100, 1));
        self::assertEquals([], $generateAsArray(100, 100, -1));

        self::assertEquals([0], $generateAsArray(0, 1));
        self::assertEquals([0, 1], $generateAsArray(0, 2));

        self::assertEquals([-1], $generateAsArray(-1, 0));
        self::assertEquals([-2, -1], $generateAsArray(-2, 0));
        self::assertEquals([-2], $generateAsArray(-2, 0, 2));

        self::assertEquals([], $generateAsArray(0, -1));
        self::assertEquals([], $generateAsArray(0, -1, 1));
        self::assertEquals([], $generateAsArray(0, -1, -1));

        self::assertEquals([], $generateAsArray(0, -2));
        self::assertEquals([], $generateAsArray(0, -2, 1));
        self::assertEquals([], $generateAsArray(0, -2, -1));
        self::assertEquals([], $generateAsArray(0, -2, 2));
        self::assertEquals([], $generateAsArray(0, -2, -2));

        self::assertEquals([101, 102], $generateAsArray(101, 103));
        self::assertEquals([-103, -102], $generateAsArray(-103, -101));
        self::assertEquals([], $generateAsArray(-101, -103));

        self::assertTrue(IterableUtilForTests::isEmpty(RangeUtilForTests::generate(1000, 1000)));
        self::assertFalse(IterableUtilForTests::isEmpty(RangeUtilForTests::generate(1000, 1001)));

        self::assertSame(0, IterableUtilForTests::count(RangeUtilForTests::generate(1000, 1000)));
        self::assertSame(1, IterableUtilForTests::count(RangeUtilForTests::generate(1000, 1001)));
    }

    public function testGenerateUpTo(): void
    {
        /**
         * @param int $count
         *
         * @return array<int>
         */
        $generateUpToAsArray = function (int $count): array {
            return IterableUtilForTests::toArray(RangeUtilForTests::generateUpTo($count));
        };

        self::assertEquals([], $generateUpToAsArray(0));
        self::assertEquals([0], $generateUpToAsArray(1));
        self::assertEquals([0, 1], $generateUpToAsArray(2));
    }

    public function testGenerateFromToIncluding(): void
    {
        /**
         * @param int $count
         *
         * @return array<int>
         */
        $generateFromToIncludingAsArray = function (int $begin, int $end): array {
            return IterableUtilForTests::toArray(RangeUtilForTests::generateFromToIncluding($begin, $end));
        };

        self::assertEquals([0], $generateFromToIncludingAsArray(0, 0));
        self::assertEquals([0, 1], $generateFromToIncludingAsArray(0, 1));
        self::assertEquals([0, 1, 2], $generateFromToIncludingAsArray(0, 2));
    }
}
