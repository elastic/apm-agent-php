<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\DbgUtil;

class RandomUtilTest extends TestCaseBase
{
    public function testArrayRandValues(): void
    {
        self::assertSame([], RandomUtilForTests::arrayRandValues([], 0));
        self::assertSame([], RandomUtilForTests::arrayRandValues(['a'], 0));
        self::assertSame(['a'], RandomUtilForTests::arrayRandValues(['a'], 1));

        $totalSet = ['a', 'b'];
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 1);
        self::assertTrue(
            $randSelectedSubSet == ['a'] || $randSelectedSubSet == ['b'],
            LoggableToString::convert(['$randSelectedSubSet' => $randSelectedSubSet])
        );
        self::assertListArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        self::assertEqualsCanonicalizing($totalSet, RandomUtilForTests::arrayRandValues($totalSet, count($totalSet)));

        $totalSet = ['a', 'b', 'c'];
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 1);
        self::assertCount(1, $randSelectedSubSet);
        self::assertTrue(
            $randSelectedSubSet == ['a'] || $randSelectedSubSet == ['b'] || $randSelectedSubSet == ['c'],
            LoggableToString::convert(['$randSelectedSubSet' => $randSelectedSubSet])
        );
        self::assertListArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 2);
        self::assertCount(2, $randSelectedSubSet);
        self::assertListArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        self::assertEqualsCanonicalizing($totalSet, RandomUtilForTests::arrayRandValues($totalSet, count($totalSet)));
    }
}
