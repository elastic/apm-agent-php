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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\TransactionContext;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\InferredSpanExpectationsBuilder;
use ElasticApmTests\Util\SpanSequenceValidator;
use ElasticApmTests\Util\TransactionExpectations;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class InferredSpansComponentTest extends ComponentTestCaseBase
{
    private const IS_INFERRED_SPANS_ENABLED_KEY = 'IS_INFERRED_SPANS_ENABLED';
    private const IS_TRANSACTION_SAMPLED_KEY = 'IS_TRANSACTION_SAMPLED';
    private const CAPTURE_SLEEPS_KEY = 'CAPTURE_SLEEPS';

    private const SLEEP_DURATION_SECONDS = 5;
    private const INFERRED_MIN_DURATION_SECONDS_TO_CAPTURE_SLEEPS = self::SLEEP_DURATION_SECONDS - 2;
    private const INFERRED_MIN_DURATION_SECONDS_TO_OMIT_SLEEPS = self::SLEEP_DURATION_SECONDS * 3 - 1;

    private const SLEEP_FUNC_NAME = 'sleep';
    private const USLEEP_FUNC_NAME = 'usleep';
    private const TIME_NANOSLEEP_FUNC_NAME = 'time_nanosleep';
    private const SLEEP_FUNC_NAMES = [self::SLEEP_FUNC_NAME, self::USLEEP_FUNC_NAME, self::TIME_NANOSLEEP_FUNC_NAME];

    private const STACK_TRACES_LABEL_KEY = 'STACK_TRACES_LABEL';
    private const STACK_TRACES_CRC_LABEL_KEY = 'STACK_TRACES_CRC_LABEL';
    private const APP_CODE_SPAN_STACK_TRACE = 'app_code_span_stack_trace';

    private const NON_KEYWORD_STRING_MAX_LENGTH = 100 * 1024;

    /**
     * @return iterable<array{array<string, mixed>}>
     */
    public function dataProviderForTestInferredSpans(): iterable
    {
        /** @var iterable<array{array<string, mixed>}> $result */
        $result = (new DataProviderForTestBuilder())
            // TODO: Sergey Kleyman: Implement: test with PROFILING_INFERRED_SPANS_ENABLED set to true
            // ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::IS_INFERRED_SPANS_ENABLED_KEY)
            // TODO: Sergey Kleyman: Remove addSingleValueKeyedDimension(self::IS_INFERRED_SPANS_ENABLED_KEY, false)
            ->addSingleValueKeyedDimension(self::IS_INFERRED_SPANS_ENABLED_KEY, false)
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::IS_TRANSACTION_SAMPLED_KEY)
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::CAPTURE_SLEEPS_KEY)
            ->wrapResultIntoArray()
            ->build();

        return self::adaptToSmoke($result);
    }

    private static function usleep(int $secondsToSleep): void
    {
        $microsecondsInSecond = 1000 * 1000;
        $microsecondsInEachSleep = $microsecondsInSecond / 5;
        $numberOfSleeps = intval(($secondsToSleep * $microsecondsInSecond) / $microsecondsInEachSleep);
        $lastSleep = $secondsToSleep % $microsecondsInEachSleep;
        for ($i = 0; $i < $numberOfSleeps; ++$i) {
            usleep($microsecondsInEachSleep);
        }
        usleep($lastSleep);
    }

    /**
     * @param int                              $secondsToSleep
     * @param string                           $sleepFuncToUse
     * @param array<string, StackTraceFrame[]> $stackTraces
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function mySleep(int $secondsToSleep, string $sleepFuncToUse, array &$stackTraces): void
    {
        switch ($sleepFuncToUse) {
            case self::SLEEP_FUNC_NAME:
                self::assertSame(0, sleep($secondsToSleep));
                $sleepCallLine = __LINE__ - 1;
                break;
            case self::USLEEP_FUNC_NAME:
                self::usleep($secondsToSleep);
                $sleepCallLine = __LINE__ - 1;
                break;
            case self::TIME_NANOSLEEP_FUNC_NAME:
                self::assertTrue(time_nanosleep($secondsToSleep, /* nanoseconds */ 0));
                $sleepCallLine = __LINE__ - 1;
                break;
            default:
                self::fail('Unknown sleepFuncToUse: `' . $sleepFuncToUse . '\'');
        }
        $stackTraceClassic = StackTraceUtil::captureInClassicFormat(AmbientContextForTests::loggerFactory());
        $stackTraceClassic[0]->line = $sleepCallLine;
        $stackTraces[$sleepFuncToUse] = StackTraceUtil::convertClassicToApmFormat($stackTraceClassic);
    }

    public static function appCodeForTestInferredSpans(): void
    {
        $stackTraces = [];

        self::mySleep(self::SLEEP_DURATION_SECONDS, self::SLEEP_FUNC_NAME, /* ref */ $stackTraces);
        self::mySleep(self::SLEEP_DURATION_SECONDS, self::USLEEP_FUNC_NAME, /* ref */ $stackTraces);
        self::mySleep(self::SLEEP_DURATION_SECONDS, self::TIME_NANOSLEEP_FUNC_NAME, /* ref */ $stackTraces);
        $sleepCallLine = __LINE__ - 1;
        $stackTraceClassic = StackTraceUtil::captureInClassicFormat(AmbientContextForTests::loggerFactory());
        $stackTraceClassic[0]->line = $sleepCallLine;
        $stackTraces[self::APP_CODE_SPAN_STACK_TRACE] = StackTraceUtil::convertClassicToApmFormat($stackTraceClassic);

        $txCtx = ElasticApm::getCurrentTransaction()->context();
        if ($txCtx instanceof TransactionContext) {
            $stackTracesSerialized = base64_encode(serialize($stackTraces));
            $nonKeywordStringMaxLength = self::getTracerFromAppCode()->getConfig()->nonKeywordStringMaxLength();
            self::assertLessThanOrEqual($nonKeywordStringMaxLength, strlen($stackTracesSerialized));
            $stackTracesSerializedCrc = crc32($stackTracesSerialized);
            $txCtx->setCustom(self::STACK_TRACES_LABEL_KEY, $stackTracesSerialized);
            $txCtx->setCustom(self::STACK_TRACES_CRC_LABEL_KEY, $stackTracesSerializedCrc);
        }
    }

    /**
     * @dataProvider dataProviderForTestInferredSpans
     *
     * @param array<string, mixed> $testArgs
     */
    public function testInferredSpans(array $testArgs): void
    {
        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['$testArgs' => $testArgs]);

        $isInferredSpansEnabled = self::getBoolFromMap(self::IS_INFERRED_SPANS_ENABLED_KEY, $testArgs);
        $isTransactionSampled = self::getBoolFromMap(self::IS_TRANSACTION_SAMPLED_KEY, $testArgs);
        $shouldCaptureSleeps = self::getBoolFromMap(self::CAPTURE_SLEEPS_KEY, $testArgs);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (
                AppCodeHostParams $appCodeParams
            ) use (
                $isInferredSpansEnabled,
                $isTransactionSampled,
                $shouldCaptureSleeps
            ): void {
                $appCodeParams->setAgentOption(OptionNames::PROFILING_INFERRED_SPANS_ENABLED, $isInferredSpansEnabled);
                $inferredMinDuration = $shouldCaptureSleeps
                    ? self::INFERRED_MIN_DURATION_SECONDS_TO_CAPTURE_SLEEPS
                    : self::INFERRED_MIN_DURATION_SECONDS_TO_OMIT_SLEEPS;
                $appCodeParams->setAgentOption(
                    OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION,
                    $inferredMinDuration . 's'
                );
                $appCodeParams->setAgentOption(
                    OptionNames::TRANSACTION_SAMPLE_RATE,
                    $isTransactionSampled ? '1' : '0'
                );
                $appCodeParams->setAgentOption(
                    OptionNames::NON_KEYWORD_STRING_MAX_LENGTH,
                    self::NON_KEYWORD_STRING_MAX_LENGTH
                );
            }
        );
        TransactionExpectations::$defaultIsSampled = $isTransactionSampled;

        $appCodeTarget = AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestInferredSpans']);
        $appCodeMethod = $appCodeTarget->appCodeMethod;
        self::assertIsString($appCodeMethod);
        $appCodeHost->sendRequest($appCodeTarget);
        // Total number of spans is 3 sleep spans + 1 span for appCode method
        $expectedSpanCount = ($isInferredSpansEnabled && $isTransactionSampled)
            ? ($shouldCaptureSleeps ? 3 : 0) + 1
            : 0;
        $expectedEventCounts = (new ExpectedEventCounts())->transactions(1)->spans($expectedSpanCount);
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent($expectedEventCounts);
        $ctx = ['dataFromAgent' => $dataFromAgent, 'testArgs' => $testArgs];
        $ctxStr = LoggableToString::convert($ctx);

        if ($expectedSpanCount === 0) {
            return;
        }

        $tx = $dataFromAgent->singleTransaction();
        self::assertNotNull($tx->context);
        self::assertNotNull($tx->context->custom);
        $stackTracesSerialized = $tx->context->custom[self::STACK_TRACES_LABEL_KEY];
        self::assertIsString($stackTracesSerialized);
        $receivedCrc = $tx->context->custom[self::STACK_TRACES_CRC_LABEL_KEY];
        $calculatedCrc = crc32($stackTracesSerialized);
        self::assertSame($receivedCrc, $calculatedCrc);
        /** @var array<string, StackTraceFrame[]> $stackTraces */
        $stackTraces = unserialize(base64_decode($stackTracesSerialized));

        $expectationsBuilder = new InferredSpanExpectationsBuilder(InferredSpanExpectationsBuilder::default());

        $appCodeSpanExpectations = $expectationsBuilder->fromClassMethodNamesAndStackTrace(
            ClassNameUtil::fqToShort(__CLASS__),
            $appCodeMethod,
            true /* <- isStatic */,
            $stackTraces[self::APP_CODE_SPAN_STACK_TRACE],
            true /* <- allowExpectedStackTraceToBePrefix */
        );
        $appCodeSpan = $dataFromAgent->singleSpanByName($appCodeSpanExpectations->name->getValue());
        self::assertSame($tx->id, $appCodeSpan->parentId, $ctxStr);
        $appCodeSpan->assertMatches($appCodeSpanExpectations);

        if (!$shouldCaptureSleeps) {
            return;
        }

        $expectedSleepSpans = [];
        $actualSleepSpans = [];
        foreach (self::SLEEP_FUNC_NAMES as $sleepFunc) {
            $stackTrace = $stackTraces[$sleepFunc];
            $expectedSleepSpans[] = $expectationsBuilder->fromFuncNameAndStackTrace(
                $sleepFunc,
                $stackTrace,
                true /* <- allowExpectedStackTraceToBePrefix */
            );
            $sleepSpan = $dataFromAgent->singleSpanByName($sleepFunc);
            $actualSleepSpans[] = $sleepSpan;
            self::assertSame($appCodeSpan->id, $sleepSpan->parentId);
        }

        SpanSequenceValidator::assertSequenceAsExpected($expectedSleepSpans, $actualSleepSpans);
    }
}
