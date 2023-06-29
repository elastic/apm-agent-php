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

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionDefaultValues;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;

final class StackTraceLimitTestSharedCode
{
    public const EXPECTED_MAX_NUMBER_OF_FRAMES_KEY = 'expected_max_number_of_frames';
    public const BASE_STACK_TRACE_DEPTH_KEY = 'base_stack_trace_depth';

    /**
     * @param ?callable $adaptDataSets
     *
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestVariousConfigValues(?callable $adaptDataSets = null): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            foreach ([null, -5, -2, -1, 0, 1, 2, 3, 5, 10, OptionDefaultValues::STACK_TRACE_LIMIT, OptionDefaultValues::STACK_TRACE_LIMIT + 10] as $optVal) {
                /**
                 * stack_trace_limit
                 *      0 - stack trace collection should be disabled
                 *      any positive value - the value is the maximum number of frames to collect
                 *      any negative value - all frames should be collected
                 */

                if ($optVal === null) {
                    $expectedMaxNumberOfFrames = OptionDefaultValues::STACK_TRACE_LIMIT;
                } elseif ($optVal >= 0) {
                    $expectedMaxNumberOfFrames = $optVal;
                } else {
                    $expectedMaxNumberOfFrames = null;
                }
                yield [OptionNames::STACK_TRACE_LIMIT => $optVal === null ? null : strval($optVal), self::EXPECTED_MAX_NUMBER_OF_FRAMES_KEY => $expectedMaxNumberOfFrames];
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc(
            /**
             * @return iterable<array<string, mixed>>
             */
            function () use ($genDataSets, $adaptDataSets): iterable {
                $dataSets = $genDataSets();
                return $adaptDataSets === null ? $dataSets : $adaptDataSets($dataSets);
            }
        );
    }

    /**
     * @param ?int $expectedMaxNumberOfFrames
     * @param int  $baseStackTraceDepth
     *
     * @return positive-int[]
     *
     * @phpstan-param null|0|positive-int $expectedMaxNumberOfFrames
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    public static function additionalStackDepthVariants(?int $expectedMaxNumberOfFrames, int $baseStackTraceDepth): array
    {
        $result = [1];
        $addAdditionalDepthToGetTotal = function (int $totalDepth) use ($baseStackTraceDepth, &$result): void {
            if ($totalDepth > $baseStackTraceDepth) {
                ArrayUtilForTests::addToListIfNotAlreadyPresent($totalDepth - $baseStackTraceDepth, /* ref */ $result);
            }
        };

        foreach ([1, 2, 3, 5] as $totalDepth) {
            $addAdditionalDepthToGetTotal($totalDepth);
        }
        foreach ([0, 1, 2, 3, 5] as $diff) {
            $addAdditionalDepthToGetTotal(OptionDefaultValues::STACK_TRACE_LIMIT + $diff);
            $addAdditionalDepthToGetTotal(OptionDefaultValues::STACK_TRACE_LIMIT - $diff);
        }
        if ($expectedMaxNumberOfFrames !== null) {
            foreach ([0, 1, 2, 3, 5] as $diff) {
                $addAdditionalDepthToGetTotal($expectedMaxNumberOfFrames + $diff);
                $addAdditionalDepthToGetTotal($expectedMaxNumberOfFrames - $diff);
            }
        }
        return $result;
    }

    /**
     * @param bool         $isDummyCallToGetLineNumber
     * @param string       $spanName
     * @param string       $spanType
     * @param positive-int $additionalDepth
     *
     * @return int
     */
    private static function createSpanWithAdditionalStackDepth(bool $isDummyCallToGetLineNumber, string $spanName, string $spanType, int $additionalDepth): int
    {
        if ($additionalDepth > 1) {
            return self::createSpanWithAdditionalStackDepth($isDummyCallToGetLineNumber, $spanName, $spanType, $additionalDepth - 1);
        }

        $lineSpanEnd = __LINE__ + 3;
        if (!$isDummyCallToGetLineNumber) {
            TestCaseBase::assertSame(__LINE__ + 1, $lineSpanEnd);
            ElasticApm::getCurrentTransaction()->beginCurrentSpan($spanName, $spanType)->end();
        }
        return $lineSpanEnd;
    }

    /**
     * @param ?int $expectedMaxNumberOfFrames
     *
     * @return void
     *
     * @phpstan-param null|0|positive-int $expectedMaxNumberOfFrames
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    public static function implTestVariousConfigValuesActPart(?int $expectedMaxNumberOfFrames): void
    {
        $baseStackTraceDepth = count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        ElasticApm::getCurrentTransaction()->context()->setLabel(self::BASE_STACK_TRACE_DEPTH_KEY, $baseStackTraceDepth);

        $stackDepthVariants = self::additionalStackDepthVariants($expectedMaxNumberOfFrames, $baseStackTraceDepth);
        foreach (RangeUtil::generateUpTo(count($stackDepthVariants)) as $index) {
            self::createSpanWithAdditionalStackDepth(/* isDummyCallToGetLineNumber */ false, 'test_span_' . $index, 'test_span_' . $index . '_type', $stackDepthVariants[$index]);
        }
    }

    /**
     * @param ?int          $expectedMaxNumberOfFrames
     * @param DataFromAgent $dataFromAgent
     *
     * @return void
     *
     * @phpstan-param null|0|positive-int $expectedMaxNumberOfFrames
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    public static function implTestVariousConfigValuesAssertPart(?int $expectedMaxNumberOfFrames, DataFromAgent $dataFromAgent): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        TestCaseBase::assertCount(1, $dataFromAgent->idToTransaction);
        $baseStackTraceDepth = TestCaseBase::getLabel($dataFromAgent->singleTransaction(), self::BASE_STACK_TRACE_DEPTH_KEY);
        TestCaseBase::assertIsInt($baseStackTraceDepth);
        TestCaseBase::assertGreaterThan(0, $baseStackTraceDepth);

        $lineSpanEnd = self::createSpanWithAdditionalStackDepth(/* $isDummyCallToGetLineNumber */ true, /* spanName */ 'dummy', /* spanType */ 'dummy', /* depth */ 1);

        $additionalStackDepthVariants = self::additionalStackDepthVariants($expectedMaxNumberOfFrames, $baseStackTraceDepth);
        TestCaseBase::assertCount(count($additionalStackDepthVariants), $dataFromAgent->idToSpan);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($additionalStackDepthVariants)) as $additionalStackDepthVariantsIndex) {
            $dbgCtx->add(['additionalStackDepthVariantsIndex' => $additionalStackDepthVariantsIndex]);
            $additionalStackDepth = $additionalStackDepthVariants[$additionalStackDepthVariantsIndex];
            $dbgCtx->add(['additionalStackDepth' => $additionalStackDepth]);
            $span = $dataFromAgent->singleSpanByName('test_span_' . $additionalStackDepthVariantsIndex);
            $dbgCtx->add(['span' => $span]);
            TestCaseBase::assertSame('test_span_' . $additionalStackDepthVariantsIndex . '_type', $span->type);
            if ($expectedMaxNumberOfFrames === 0) {
                TestCaseBase::assertNull($span->stackTrace);
                continue;
            }
            TestCaseBase::assertNotNull($span->stackTrace);

            if ($expectedMaxNumberOfFrames !== null) {
                TestCaseBase::assertLessThanOrEqual($expectedMaxNumberOfFrames, count($span->stackTrace));
            }

            TestCaseBase::assertNotNull($span->stackTrace);
            TestCaseBase::assertCountableNotEmpty($span->stackTrace);

            $dbgCtx->pushSubScope();
            foreach (RangeUtil::generateUpTo(min($additionalStackDepth + 2, count($span->stackTrace))) as $additionalDepthCallIndex) {
                $dbgCtx->add(['additionalDepthCallIndex' => $additionalDepthCallIndex]);

                if ($additionalDepthCallIndex === 0) {
                    $frameExpectations = StackTraceFrameExpectations::fromLocationOnly(__FILE__, $lineSpanEnd);
                } elseif ($additionalDepthCallIndex === $additionalStackDepth + 1) {
                    $frameExpectations = StackTraceFrameExpectations::fromClassMethodUnknownLocation(__CLASS__, /* isStatic */ true, 'implTestVariousConfigValuesActPart');
                } else {
                    $frameExpectations = StackTraceFrameExpectations::fromClassMethodUnknownLine(__FILE__, __CLASS__, /* isStatic */ true, 'createSpanWithAdditionalStackDepth');
                }

                $frameExpectations->assertMatches($span->stackTrace[$additionalDepthCallIndex]);
            }
            $dbgCtx->popSubScope();
        }
        $dbgCtx->popSubScope();
    }
}
