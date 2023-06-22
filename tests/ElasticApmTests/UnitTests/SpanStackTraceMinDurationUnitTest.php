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

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\Config\OptionDefaultValues;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\UnitTests\Util\MockClock;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

final class SpanStackTraceMinDurationUnitTest extends TracerUnitTestCaseBase
{
    public const EXPECTED_SPAN_STACK_TRACE_MIN_DURATION_KEY = 'expected_span_stack_trace_min_duration';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestVariousConfigValues(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            foreach ([null, -5, -2, -1, 0, 1, 2, 3, OptionDefaultValues::SPAN_STACK_TRACE_MIN_DURATION, OptionDefaultValues::SPAN_STACK_TRACE_MIN_DURATION + 10] as $optVal) {
                yield [
                    OptionNames::SPAN_STACK_TRACE_MIN_DURATION       => $optVal === null ? null : strval($optVal),
                    self::EXPECTED_SPAN_STACK_TRACE_MIN_DURATION_KEY => floatval($optVal === null ? OptionDefaultValues::SPAN_STACK_TRACE_MIN_DURATION : $optVal),
                ];
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($genDataSets);
    }

    /**
     * @param float $expectedSpanStackTraceMinDuration
     *
     * @return float[]
     */
    public static function genSpanDurations(float $expectedSpanStackTraceMinDuration): array
    {
        $spanDurations = [0];
        $addToSpanDurations = function (float $spanDuration) use (&$spanDurations): void {
            if ($spanDuration >= 0) {
                ArrayUtilForTests::addToListIfNotAlreadyPresent($spanDuration, /* ref */ $spanDurations);
            }
        };

        foreach ([0, 0.123, 0.5, 1, 2] as $diff) {
            $addToSpanDurations($expectedSpanStackTraceMinDuration + $diff);
            $addToSpanDurations($expectedSpanStackTraceMinDuration - $diff);
        }

        return $spanDurations;
    }

    /**
     * @dataProvider dataProviderForTestVariousConfigValues
     */
    public function testVariousConfigValuesWithMockClock(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $expectedSpanStackTraceMinDuration = $testArgs->getFloat(self::EXPECTED_SPAN_STACK_TRACE_MIN_DURATION_KEY);

        /**
         * Arrange
         */

        $spanDurations = self::genSpanDurations($expectedSpanStackTraceMinDuration);
        $dbgCtx->add(['spanDurations' => $spanDurations]);

        $mockClock = new MockClock();
        $this->setUpTestEnv(
            function (TracerBuilderForTests $tracerBuilder) use ($testArgs, $mockClock): void {
                $tracerBuilder->withClock($mockClock);
                if (($optVal = $testArgs->getNullableString(OptionNames::SPAN_STACK_TRACE_MIN_DURATION)) !== null) {
                    $tracerBuilder->withConfig(OptionNames::SPAN_STACK_TRACE_MIN_DURATION, $optVal);
                }
            }
        );

        /**
         * Act
         */

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        $produceSpanWithDuration = function (int $spanIndex) use ($spanDurations, $mockClock): void {
            $span = $this->tracer->getCurrentTransaction()->beginCurrentSpan('test_span_' . $spanIndex, 'test_span_' . $spanIndex . '_type');
            $mockClock->fastForwardMilliseconds($spanDurations[$spanIndex]);
            $span->end();
        };

        foreach (RangeUtil::generateUpTo(count($spanDurations)) as $spanIndex) {
            $produceSpanWithDuration($spanIndex);
        }

        $tx->end();

        /**
         * Assert
         */

        $dbgCtx->add(['dataFromAgent' => $this->mockEventSink->dataFromAgent]);
        TestCaseBase::assertCount(count($spanDurations), $this->mockEventSink->dataFromAgent->idToSpan);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($spanDurations)) as $spanIndex) {
            $dbgCtx->add(['spanIndex' => $spanIndex, '$spanDurations[$spanIndex]' => $spanDurations[$spanIndex]]);
            $span = $this->mockEventSink->dataFromAgent->singleSpanByName('test_span_' . $spanIndex);
            self::assertEquals($spanDurations[$spanIndex], $span->duration);
            /**
             * span_stack_trace_min_duration
             *      0 - collect stack traces for spans with any duration
             *      any positive value - it limits stack trace collection to spans with duration equal to or greater than
             *      any negative value - it disable stack trace collection for spans completely
             */
            if ($expectedSpanStackTraceMinDuration < 0) {
                self::assertNull($span->stackTrace);
            } elseif ($expectedSpanStackTraceMinDuration === 0.0) {
                self::assertNotNull($span->stackTrace);
            } elseif ($expectedSpanStackTraceMinDuration <= $span->duration) {
                self::assertNotNull($span->stackTrace);
            } else {
                self::assertNull($span->stackTrace);
            }
        }
        $dbgCtx->popSubScope();
    }
}
