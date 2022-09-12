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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use PHPUnit\Framework\TestCase;

final class SpanSequenceValidator extends ExecutionSegmentDataValidator
{
    use StaticClassTrait;

    /**
     * @param SpanData[] $spans
     *
     * @return SpanData[]
     */
    private static function sortByStartTime(array $spans): array
    {
        usort(
            $spans,
            function (SpanData $span_1, SpanData $span_2): int {
                return TimeUtilForTests::compareTimestamps($span_1->timestamp, $span_2->timestamp);
            }
        );
        return $spans;
    }

    /**
     * @param SpanDataExpectations[] $expected
     *
     * @return void
     */
    public static function updateExpectationsEndTime(array $expected): void
    {
        $timestampAfter = (new EventDataExpectations())->timestampAfter;
        foreach ($expected as $spanExpectation) {
            $spanExpectation->timestampAfter = $timestampAfter;
        }
    }

    /**
     * @param SpanDataExpectations[] $expected
     * @param SpanData[] $actual
     *
     * @return void
     */
    public static function assertSequenceAsExpected(array $expected, array $actual): void
    {
        $dbgCtx = LoggableToString::convert(['$expected' => $expected, '$actual' => $actual,]);
        TestCase::assertSame(count($expected), count($actual), $dbgCtx);

        $actualSortedByStartTime = self::sortByStartTime($actual);
        for ($i = 0; $i < count($actual); ++$i) {
            SpanDataValidator::validate($actualSortedByStartTime[$i], $expected[$i]);
        }
    }
}
