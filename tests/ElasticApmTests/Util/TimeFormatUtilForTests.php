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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TimeFormatUtilForTests
{
    use StaticClassTrait;

    private static function calcWholeTimesAndRemainderForFloat(
        float $largeVal,
        int $smallVal,
        float &$wholeTimes,
        float &$remainder
    ): void {
        $wholeTimes = floor($largeVal / $smallVal);
        $remainder = $largeVal - ($smallVal * $wholeTimes);
    }

    public static function formatDurationInMicroseconds(float $durationInMicroseconds): string
    {
        if ($durationInMicroseconds === 0.0) {
            return '0us';
        }

        $isNegative = ($durationInMicroseconds < 0);
        $microsecondsTotalFloat = abs($durationInMicroseconds);
        $microsecondsTotalWhole = floor($microsecondsTotalFloat);
        $microsecondsFraction = $microsecondsTotalFloat - $microsecondsTotalWhole;

        $millisecondsTotalWhole = 0.0;
        $microsecondsRemainder = 0.0;
        self::calcWholeTimesAndRemainderForFloat(
            $microsecondsTotalWhole,
            TimeUtil::NUMBER_OF_MICROSECONDS_IN_MILLISECOND,
            /* ref */ $millisecondsTotalWhole,
            /* ref */ $microsecondsRemainder
        );

        $secondsTotalWhole = 0.0;
        $millisecondsRemainder = 0.0;
        self::calcWholeTimesAndRemainderForFloat(
            $millisecondsTotalWhole,
            TimeUtil::NUMBER_OF_MILLISECONDS_IN_SECOND,
            /* ref */ $secondsTotalWhole,
            /* ref */ $millisecondsRemainder
        );

        $minutesTotalWhole = 0.0;
        $secondsRemainder = 0.0;
        self::calcWholeTimesAndRemainderForFloat(
            $secondsTotalWhole,
            TimeUtil::NUMBER_OF_SECONDS_IN_MINUTE,
            /* ref */ $minutesTotalWhole,
            /* ref */ $secondsRemainder
        );

        $hoursTotalWhole = 0.0;
        $minutesRemainder = 0.0;
        self::calcWholeTimesAndRemainderForFloat(
            $minutesTotalWhole,
            TimeUtil::NUMBER_OF_MINUTES_IN_HOUR,
            /* ref */ $hoursTotalWhole,
            /* ref */ $minutesRemainder
        );

        $hoursRemainder = 0.0;
        $daysTotalWhole = 0.0;
        self::calcWholeTimesAndRemainderForFloat(
            $hoursTotalWhole,
            TimeUtil::NUMBER_OF_HOURS_IN_DAY,
            /* ref */ $daysTotalWhole,
            /* ref */ $hoursRemainder
        );

        $appendRemainder = function (string $appendTo, float $remainder, string $units): string {
            if ($remainder === 0.0) {
                return $appendTo;
            }

            $remainderAsString = ($remainder === floor($remainder)) ? strval(intval($remainder)) : strval($remainder);
            return $appendTo . (TextUtil::isEmptyString($appendTo) ? '' : ' ') . $remainderAsString . $units;
        };

        $result = '';
        $result = $appendRemainder($result, $daysTotalWhole, 'd');
        $result = $appendRemainder($result, $hoursRemainder, 'h');
        $result = $appendRemainder($result, $minutesRemainder, 'm');
        $result = $appendRemainder($result, $secondsRemainder, 's');
        $result = $appendRemainder($result, $millisecondsRemainder, 'ms');
        $result = $appendRemainder($result, $microsecondsRemainder + $microsecondsFraction, 'us');

        return ($isNegative ? '-' : '') . $result;
    }
}
