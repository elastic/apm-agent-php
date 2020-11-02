<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\DbgUtil;

class RandomUtilTest extends TestCaseBase
{
    /**
     * @param array<mixed> $subSet
     * @param array<mixed> $largerSet
     */
    private static function assertIndexedArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        self::assertTrue(
            count(array_intersect($subSet, $largerSet)) === count($subSet),
            'array_diff: ' . DbgUtil::formatValue(array_diff($subSet, $largerSet)) . '.'
            . ' $subSet: ' . DbgUtil::formatValue($subSet) . '.'
            . ' $largerSet: ' . DbgUtil::formatValue($largerSet) . '.'
            . ' array_intersect: ' . DbgUtil::formatValue(array_intersect($subSet, $largerSet)) . '.'
        );
    }

    public function testArrayRand(): void
    {
        self::assertSame([], RandomUtilForTests::arrayRandValues([], 0));
        self::assertSame([], RandomUtilForTests::arrayRandValues(['a'], 0));
        self::assertSame(['a'], RandomUtilForTests::arrayRandValues(['a'], 1));

        $totalSet = ['a', 'b'];
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 1);
        self::assertTrue(
            $randSelectedSubSet == ['a'] || $randSelectedSubSet == ['b'],
            DbgUtil::formatValue($randSelectedSubSet)
        );
        self::assertIndexedArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        self::assertEqualsCanonicalizing($totalSet, RandomUtilForTests::arrayRandValues($totalSet, count($totalSet)));

        $totalSet = ['a', 'b', 'c'];
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 1);
        self::assertCount(1, $randSelectedSubSet);
        self::assertTrue(
            $randSelectedSubSet == ['a'] || $randSelectedSubSet == ['b'] || $randSelectedSubSet == ['c'],
            DbgUtil::formatValue($randSelectedSubSet)
        );
        self::assertIndexedArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 2);
        self::assertCount(2, $randSelectedSubSet);
        self::assertIndexedArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        self::assertEqualsCanonicalizing($totalSet, RandomUtilForTests::arrayRandValues($totalSet, count($totalSet)));
    }
}
