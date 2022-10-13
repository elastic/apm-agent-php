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
use Elastic\Apm\Impl\Util\StaticClassTrait;
use PHPUnit\Framework\TestCase;

final class SpanSequenceValidator
{
    use StaticClassTrait;

    /**
     * @param SpanDto[] $spans
     *
     * @return SpanDto[]
     */
    private static function sortByStartTime(array $spans): array
    {
        usort(
            $spans,
            function (SpanDto $span_1, SpanDto $span_2): int {
                return TimeUtilForTests::compareTimestamps($span_1->timestamp, $span_2->timestamp);
            }
        );
        return $spans;
    }

    /**
     * @param SpanExpectations[] $expected
     *
     * @return void
     */
    public static function updateExpectationsEndTime(array $expected): void
    {
        $timestampAfter = (new EventExpectations())->timestampAfter;
        foreach ($expected as $spanExpectation) {
            $spanExpectation->timestampAfter = $timestampAfter;
        }
    }

    /**
     * @param SpanExpectations[] $expected
     * @param SpanDto[]          $actual
     *
     * @return void
     */
    public static function assertSequenceAsExpected(array $expected, array $actual): void
    {
        $dbgCtx = LoggableToString::convert(['$expected' => $expected, '$actual' => $actual,]);
        TestCase::assertSame(count($expected), count($actual), $dbgCtx);

        $actualSortedByStartTime = self::sortByStartTime($actual);
        for ($i = 0; $i < count($actual); ++$i) {
            $currentActualSpan = $actualSortedByStartTime[$i];
            if ($i != 0) {
                $prevActualSpan = $actualSortedByStartTime[$i - 1];
                TestCaseBase::assertLessThanOrEqualTimestamp($prevActualSpan->timestamp, $currentActualSpan->timestamp);
                $prevActualSpanEnd = TestCaseBase::calcEndTime($prevActualSpan);
                TestCaseBase::assertLessThanOrEqualTimestamp($prevActualSpanEnd, $currentActualSpan->timestamp);
            }
            $currentActualSpan->assertMatches($expected[$i]);
        }
    }
}
