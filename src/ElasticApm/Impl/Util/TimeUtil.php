<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TimeUtil
{
    use StaticClassTrait;

    public const NUMBER_OF_NANOSECONDS_IN_MICROSECOND = 1000;
    public const NUMBER_OF_MICROSECONDS_IN_MILLISECOND = 1000;
    public const NUMBER_OF_MILLISECONDS_IN_SECOND = 1000;
    public const NUMBER_OF_MICROSECONDS_IN_SECOND
        = self::NUMBER_OF_MILLISECONDS_IN_SECOND * self::NUMBER_OF_MICROSECONDS_IN_MILLISECOND;
    public const NUMBER_OF_SECONDS_IN_MINUTE = 60;
    public const NUMBER_OF_MINUTES_IN_HOUR = 60;
    public const NUMBER_OF_HOURS_IN_DAY = 24;

    /**
     * @param float $beginTime Begin time in microseconds
     * @param float $endTime   End time in microseconds
     *
     * @return float Duration in microseconds
     *
     * @see ClockInterface::getMonotonicClockCurrentTime() For the description
     */
    public static function calcDurationInMicroseconds(float $beginTime, float $endTime): float
    {
        $diff = $endTime - $beginTime;
        $diff = $diff < 0 ? 0 : $diff;
        return $diff;
    }

    /**
     * @param float $beginTime Begin time in microseconds
     * @param float $endTime   End time in microseconds
     *
     * @return float Duration in milliseconds
     *
     * @see ClockInterface::getMonotonicClockCurrentTime() For the description
     */
    public static function calcDuration(float $beginTime, float $endTime): float
    {
        return self::microsecondsToMilliseconds(self::calcDurationInMicroseconds($beginTime, $endTime));
    }

    public static function microsecondsToMilliseconds(float $microseconds): float
    {
        return $microseconds / self::NUMBER_OF_MICROSECONDS_IN_MILLISECOND;
    }

    public static function millisecondsToMicroseconds(float $milliseconds): float
    {
        return $milliseconds * self::NUMBER_OF_MICROSECONDS_IN_MILLISECOND;
    }

    public static function nanosecondsToMicroseconds(float $nanoseconds): float
    {
        return $nanoseconds / self::NUMBER_OF_NANOSECONDS_IN_MICROSECOND;
    }

    public static function secondsToMicroseconds(float $seconds): float
    {
        return $seconds * self::NUMBER_OF_MICROSECONDS_IN_SECOND;
    }
}
