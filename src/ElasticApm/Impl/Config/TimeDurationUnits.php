<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Tests\UnitTests\UtilTests\TimeDurationUnitsTest;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TimeDurationUnits
{
    use StaticClassTrait;

    public const MILLISECONDS = 0;
    public const SECONDS = self::MILLISECONDS + 1;
    public const MINUTES = self::SECONDS + 1;

    public const MILLISECONDS_SUFFIX = 'ms';
    public const SECONDS_SUFFIX = 's';
    public const MINUTES_SUFFIX = 'm';

    /**
     * @var array<array<string|int>> Array should be in descending order of suffix length
     *
     * @see TimeDurationUnitsTest::testSuffixAndIdIsInDescendingOrderOfSuffixLength
     */
    public static $suffixAndIdPairs
        = [
            [self::MILLISECONDS_SUFFIX, self::MILLISECONDS],
            [self::SECONDS_SUFFIX, self::SECONDS],
            [self::MINUTES_SUFFIX, self::MINUTES],
        ];
}
