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
use ElasticApmTests\Util\TestCaseBase;

final class StackTraceLimitTestSharedCode
{
    public const EXPECTED_MAX_NUMBER_OF_FRAMES_KEY = 'expected_max_number_of_frames';
    public const BASE_STACK_TRACE_DEPTH_KEY = 'base_stack_trace_depth';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestVariousConfigValues(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            foreach ([null, -5, -2, -1, 0, 1, 2, 3, 5, 10, OptionDefaultValues::STACK_TRACE_LIMIT, OptionDefaultValues::STACK_TRACE_LIMIT + 10] as $optVal) {
                /**
                 * stack_trace_limit
                 *      0 - stack trace collection should be disabled
                 *      any positive integer value - the value is the maximum number of frames to collect
                 *      -1  - all frames should be collected
                 */

                if ($optVal === null) {
                    $expectedMaxNumberOfFrames = OptionDefaultValues::STACK_TRACE_LIMIT;
                } elseif ($optVal >= 0) {
                    $expectedMaxNumberOfFrames = $optVal;
                } elseif ($optVal === -1) {
                    $expectedMaxNumberOfFrames = null;
                } else {
                    /**
                     * Any negative value other than -1 is invalid and the default value should be used
                     */
                    $expectedMaxNumberOfFrames = OptionDefaultValues::STACK_TRACE_LIMIT;
                }
                yield [OptionNames::STACK_TRACE_LIMIT => $optVal === null ? null : strval($optVal), self::EXPECTED_MAX_NUMBER_OF_FRAMES_KEY => $expectedMaxNumberOfFrames];
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($genDataSets);
    }

    /**
     * @param ?int $expectedMaxNumberOfFrames
     *
     * @return positive-int[]
     *
     * @phpstan-param null|0|positive-int $expectedMaxNumberOfFrames
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    private static function additionalStackDepthVariants(?int $expectedMaxNumberOfFrames): array
    {
        $result = [1];
        if ($expectedMaxNumberOfFrames !== null) {
            $result = [1, $expectedMaxNumberOfFrames, $expectedMaxNumberOfFrames + 1];
            if ($expectedMaxNumberOfFrames - 1 > 0) {
                ArrayUtilForTests::addToListIfNotAlreadyPresent($expectedMaxNumberOfFrames - 1, $result);
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
        ElasticApm::getCurrentTransaction()->context()->setLabel(self::BASE_STACK_TRACE_DEPTH_KEY, count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));

        $stackDepthVariants = self::additionalStackDepthVariants($expectedMaxNumberOfFrames);
        foreach (RangeUtil::generateUpTo(count($stackDepthVariants)) as $index) {
            self::createSpanWithAdditionalStackDepth(/* isDummyCallToGetLineNumber */ false, 'test_span_' . ($index + 1), 'test_span_' . ($index + 1) . '_type', $stackDepthVariants[$index]);
        }
    }

    /**
     * @param ?int $expectedMaxNumberOfFrames
     *
     * @return int
     *
     * @phpstan-param null|0|positive-int $expectedMaxNumberOfFrames
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    public static function implTestVariousConfigValuesExpectedSpanCount(?int $expectedMaxNumberOfFrames): int
    {
        return count(self::additionalStackDepthVariants($expectedMaxNumberOfFrames));
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

        $additionalStackDepthVariants = self::additionalStackDepthVariants($expectedMaxNumberOfFrames);
        TestCaseBase::assertCount(self::implTestVariousConfigValuesExpectedSpanCount($expectedMaxNumberOfFrames), $dataFromAgent->idToSpan);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($additionalStackDepthVariants)) as $index) {
            $dbgCtx->add(['$index' => $index, '$additionalStackDepth' => $additionalStackDepthVariants[$index]]);
            $span = $dataFromAgent->singleSpanByName('test_span_' . ($index + 1));
            TestCaseBase::assertSame('test_span_' . ($index + 1) . '_type', $span->type);
            if ($expectedMaxNumberOfFrames === 0) {
                TestCaseBase::assertNull($span->stackTrace);
                continue;
            }

            TestCaseBase::assertNotNull($span->stackTrace);
            TestCaseBase::assertCountAtLeast(1, $span->stackTrace);
            $fullStackTraceDepth =  $baseStackTraceDepth + $additionalStackDepthVariants[$index] + 1;
            $expectedStackTraceCount = $expectedMaxNumberOfFrames === null ? $fullStackTraceDepth : min($fullStackTraceDepth, $expectedMaxNumberOfFrames);
            TestCaseBase::assertCount($expectedStackTraceCount, $span->stackTrace);

            $topFrame = $span->stackTrace[0];
            TestCaseBase::assertSame(__FILE__, $topFrame->filename);
            TestCaseBase::assertSame($lineSpanEnd, $topFrame->lineno);
            TestCaseBase::assertNull($topFrame->function);
        }
        $dbgCtx->popSubScope();
    }
}
