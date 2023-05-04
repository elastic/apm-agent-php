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
     */
    public static function assertSequenceAsExpected(array $expected, array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['$expected' => $expected, '$actual' => $actual]);
        TestCaseBase::assertSameCount($expected, $actual);

        $actualSortedByStartTime = self::sortByStartTime($actual);
        $index = 0;
        /** @var ?SpanDto $prevActualSpan */
        $prevActualSpan = null;
        foreach (IterableUtilForTests::zip($expected, $actualSortedByStartTime) as [$expectedSpan, $actualSpan]) {
            /** @var SpanExpectations $expectedSpan */
            /** @var SpanDto $actualSpan */
            AssertMessageStack::newSubScope(/* ref */ $dbgCtx);
            $dbgCtx->add(['index' => $index, 'expectedSpan' => $expectedSpan, 'actualSpan' => $actualSpan]);
            if ($index != 0) {
                TestCaseBase::assertNotNull($prevActualSpan);
                TestCaseBase::assertLessThanOrEqualTimestamp($prevActualSpan->timestamp, $actualSpan->timestamp);
                $prevActualSpanEnd = TestCaseBase::calcEndTime($prevActualSpan);
                TestCaseBase::assertLessThanOrEqualTimestamp($prevActualSpanEnd, $actualSpan->timestamp);
            }
            $actualSpan->assertMatches($expectedSpan);
            $prevActualSpan = $actualSpan;
            ++$index;
            AssertMessageStack::popSubScope(/* ref */ $dbgCtx);
        }
    }
}
