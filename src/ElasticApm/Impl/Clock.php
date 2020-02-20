<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\CachedSingletonInstanceTrait;
use ElasticApm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Clock implements ClockInterface
{
    use CachedSingletonInstanceTrait;

    /** @inheritDoc */
    public function getSystemClockCurrentTime(): float
    {
        // Return value should be in microseconds
        // while microtime(/* get_as_float: */ true) returns in seconds with microseconds being the fractional part
        return round(TimeUtil::secondsToMicroseconds(microtime(/* get_as_float: */ true)));
    }

    /** @inheritDoc */
    public function getMonotonicClockCurrentTime(): float
    {
        return function_exists('hrtime') ? self::getHighResolutionCurrentTime() : $this->getSystemClockCurrentTime();
    }

    private static function getHighResolutionCurrentTime(): float
    {
        // the nanoseconds are returned as integer (64bit platforms) or float (32bit platforms)
        return round(TimeUtil::nanosecondsToMicroseconds((float)(hrtime(/* get_as_number */ true))));
    }
}
