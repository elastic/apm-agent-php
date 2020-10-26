<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Config\TimeDurationUnits;
use Elastic\Apm\Impl\Util\TextUtil;
use PHPUnit\Framework\TestCase;

class TimeDurationUnitsTest extends TestCase
{
    public function testSuffixAndIdIsInDescendingOrderOfSuffixLength(): void
    {
        /** @var int|null */
        $prevSuffixLength = null;
        foreach (TimeDurationUnits::$suffixAndIdPairs as $suffixAndIdPair) {
            /** @var string */
            $suffix = $suffixAndIdPair[0];
            $suffixLength = strlen($suffix);
            if (!is_null($prevSuffixLength)) {
                self::assertLessThanOrEqual($prevSuffixLength, $suffixLength);
            }
            $prevSuffixLength = $suffixLength;
        }
    }
}
