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

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\TimeUtil;
use PHPUnit\Framework\TestCase;

class TimeUtilTest extends TestCase
{
    /**
     * @return array<array<float|string>>
     */
    public function formatDurationInMicrosecondsTestDataProvider(): array
    {
        $buildDurationInMicroseconds = function (
            int $days,
            int $hours,
            int $minutes,
            int $seconds,
            int $milliseconds,
            float $microseconds
        ): float {
            $result = floatval($days * TimeUtil::NUMBER_OF_HOURS_IN_DAY);
            $result = ($result + $hours) * TimeUtil::NUMBER_OF_MINUTES_IN_HOUR;
            $result = ($result + $minutes) * TimeUtil::NUMBER_OF_SECONDS_IN_MINUTE;
            $result = ($result + $seconds) * TimeUtil::NUMBER_OF_MILLISECONDS_IN_SECOND;
            $result = ($result + $milliseconds) * TimeUtil::NUMBER_OF_MICROSECONDS_IN_MILLISECOND;
            $result += $microseconds;
            return $result;
        };

        return [
            [0, '0us'],
            [0.1, '0.1us'],
            [-0.1, '-0.1us'],
            [-1, '-1us'],
            [$buildDurationInMicroseconds(1, 2, 3, 4, 5, 6), '1d 2h 3m 4s 5ms 6us'],
            [$buildDurationInMicroseconds(1, 2, 3, 4, 5, 6.5), '1d 2h 3m 4s 5ms 6.5us'],
            [$buildDurationInMicroseconds(1, 0, 0, 0, 0, 0), '1d'],
            [$buildDurationInMicroseconds(0, 1, 0, 0, 0, 0), '1h'],
            [$buildDurationInMicroseconds(0, 0, 1, 0, 0, 0), '1m'],
            [$buildDurationInMicroseconds(0, 0, 0, 1, 0, 0), '1s'],
            [$buildDurationInMicroseconds(0, 0, 0, 0, 1, 0), '1ms'],
            [$buildDurationInMicroseconds(0, -1, 0, 0, 0, -1), '-1h 1us'],
            [$buildDurationInMicroseconds(0, 0, -1, 0, 0, -1.5), '-1m 1.5us'],
            [$buildDurationInMicroseconds(1, 0, 5, 0, 7, 0), '1d 5m 7ms'],
            [$buildDurationInMicroseconds(1, 0, 5, 0, 7, 0.5), '1d 5m 7ms 0.5us'],
            [$buildDurationInMicroseconds(0, 23, 59, 59, 999, 1000), '1d'],
            [$buildDurationInMicroseconds(0, -23, -59, -59, -999, -1000.5), '-1d 0.5us'],
            [$buildDurationInMicroseconds(1, 0, 0, 0, 0, 999), '1d 999us'],
            [$buildDurationInMicroseconds(0, 0, 0, 0, 6, 7), '6ms 7us'],
            [5006007.5, '5s 6ms 7.5us'],
        ];
    }

    /**
     * @dataProvider formatDurationInMicrosecondsTestDataProvider
     *
     * @param float  $durationInMicroseconds
     * @param string $expectedFormatted
     */
    public function testFormatDurationInMicroseconds(float $durationInMicroseconds, string $expectedFormatted): void
    {
        $this->assertSame($expectedFormatted, TimeUtil::formatDurationInMicroseconds($durationInMicroseconds));
    }
}
