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
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\SpanInterface;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\StackTraceExpectations;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;

use function ElasticApmTests\dummyFuncForTestsWithNamespace;

use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME;
use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER;
use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE;

final class SpanStackTraceTestSharedCode
{
    public const SPAN_CREATING_API_LABEL_KEY = 'stacktrace / span creating API';

    private static function lineNumberKey(string $methodName): string
    {
        return 'stacktrace / ' . $methodName . ' / line number';
    }

    private static function lineNumberForSpanCreatingApiKey(string $spanCreatingApi): string
    {
        return 'stacktrace / span creating API: ' . $spanCreatingApi . ' / line number';
    }

    /**
     * @param callable             $createSpan
     * @param array<string, mixed> $expectedData
     *
     * @phpstan-param callable(): void $createSpan
     */
    public static function myStaticMethod(callable $createSpan, array &$expectedData): void
    {
        $func = function () use ($createSpan, &$expectedData): void {
            $expectedData[self::lineNumberKey('$createSpan')] = __LINE__ + 1;
            $createSpan();
        };
        dummyFuncForTestsWithNamespace($func);
        $expectedData[self::lineNumberKey('dummyFuncForTestsWithNamespace')] = __LINE__ - 1;
    }

    /**
     * @param callable             $createSpan
     * @param array<string, mixed> $expectedData
     *
     * @phpstan-param callable(): void $createSpan
     */
    public function myInstanceMethod(callable $createSpan, array &$expectedData): void
    {
        $func = function () use ($createSpan, &$expectedData): void {
            $expectedData[self::lineNumberKey('myStaticMethod')] = __LINE__ + 1;
            self::myStaticMethod($createSpan, /* ref */ $expectedData);
        };
        dummyFuncForTestsWithoutNamespace($func);
        $expectedData[self::lineNumberKey('dummyFuncForTestsWithoutNamespace')] = __LINE__ - 1;
    }

    /**
     * @param callable             $createSpan
     * @param array<string, mixed> $expectedData
     *
     * @phpstan-param callable(): void $createSpan
     */
    public function actPartImpl(callable $createSpan, array &$expectedData): void
    {
        $expectedData[self::lineNumberKey('myInstanceMethod')] = __LINE__ + 1;
        $this->myInstanceMethod($createSpan, /* ref */ $expectedData);
    }

    /**
     * @param array<string, mixed> $expectedData
     * @param string               $key
     *
     * @return int
     */
    private static function getIntFromExpectedData(array $expectedData, string $key): int
    {
        $value = $expectedData[$key];
        TestCaseBase::assertIsInt($value, LoggableToString::convert(['$key' => $key]));
        return $value;
    }

    /**
     * @param array<string, mixed> $expectedData
     * @param SpanDto              $span
     */
    private static function assertPartImplOneSpan(array $expectedData, SpanDto $span): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        /** @var string $spanCreatingApi */
        $spanCreatingApi = TestCaseBase::getLabel($span, self::SPAN_CREATING_API_LABEL_KEY);

        /** @var StackTraceFrameExpectations[] $frameExpectations */
        $frameExpectations = [];
        $frameExpectations[] = StackTraceFrameExpectations::fromLocationOnly(__FILE__, self::getIntFromExpectedData($expectedData, self::lineNumberForSpanCreatingApiKey($spanCreatingApi)));
        $frameExpectations[] = StackTraceFrameExpectations::fromClosure(__FILE__, self::getIntFromExpectedData($expectedData, self::lineNumberKey('$createSpan')), __NAMESPACE__, __CLASS__, true);
        $frameExpectations[] = StackTraceFrameExpectations::fromClosure(
            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME,
            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER,
            __NAMESPACE__,
            __CLASS__,
            true /* <- isStatic */
        );
        $frameExpectations[] = StackTraceFrameExpectations::fromStandaloneFunction(
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('dummyFuncForTestsWithNamespace')),
            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE . '\\' . 'dummyFuncForTestsWithNamespace'
        );
        $frameExpectations[] = StackTraceFrameExpectations::fromClassMethod(
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('myStaticMethod')),
            __CLASS__,
            true /* <- isStatic */,
            'myStaticMethod'
        );
        $frameExpectations[] = StackTraceFrameExpectations::fromClosure(
            DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME,
            DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER,
            __NAMESPACE__,
            __CLASS__,
            false /* <- isStatic */
        );
        $frameExpectations[] = StackTraceFrameExpectations::fromStandaloneFunction(
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('dummyFuncForTestsWithoutNamespace')),
            'dummyFuncForTestsWithoutNamespace'
        );
        $frameExpectations[] = StackTraceFrameExpectations::fromClassMethod(
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('myInstanceMethod')),
            __CLASS__,
            false /* <- isStatic */,
            'myInstanceMethod'
        );
        $dbgCtx->add(['frameExpectations' => $frameExpectations]);

        $actualStacktrace = $span->stackTrace;
        $dbgCtx->add(['actualStacktrace' => $actualStacktrace]);
        TestCaseBase::assertNotNull($actualStacktrace);
        StackTraceExpectations::fromFramesExpectations($frameExpectations, /* allowToBePrefixOfActual */ true)->assertMatches($actualStacktrace);
    }

    /**
     * @param int                    $expectedSpansToCheckCount
     * @param array<string, mixed>   $expectedData
     * @param array<string, SpanDto> $idToSpan
     */
    public static function assertPartImpl(int $expectedSpansToCheckCount, array $expectedData, array $idToSpan): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $checkedSpansCount = 0;
        $dbgCtx->pushSubScope();
        foreach ($idToSpan as $span) {
            $dbgCtx->clearCurrentSubScope(['checkedSpansCount' => $checkedSpansCount, 'span' => $span]);
            $labels = $span->context === null ? [] : $span->context->labels;
            TestCaseBase::assertNotNull($labels);
            if (array_key_exists(self::SPAN_CREATING_API_LABEL_KEY, $labels)) {
                self::assertPartImplOneSpan($expectedData, $span);
                ++$checkedSpansCount;
            }
        }
        $dbgCtx->popSubScope();
        TestCaseBase::assertSame($expectedSpansToCheckCount, $checkedSpansCount);
    }

    /**
     * @param array<string, array<string, mixed>> $expectedData
     *
     * @return array<callable>
     *
     * @phpstan-return array<callable(): void>
     */
    public static function allSpanCreatingApis(array &$expectedData): array
    {
        return [
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::beginCurrentSpan';
                $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan('test_span_name', 'test_span_type');
                $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);

                $expectedData[self::lineNumberForSpanCreatingApiKey($spanCreatingApi)] = __LINE__ + 1;
                $span->end();
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::captureCurrentSpan';
                $func = function (SpanInterface $span) use ($spanCreatingApi): void {
                    $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                };
                ElasticApm::getCurrentTransaction()->captureCurrentSpan('test_span_name', 'test_span_type', $func);
                $expectedData[self::lineNumberForSpanCreatingApiKey($spanCreatingApi)] = __LINE__ - 1;
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::beginChildSpan';
                $span = ElasticApm::getCurrentTransaction()->beginChildSpan('test_span_name', 'test_span_type');
                $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);

                $expectedData[self::lineNumberForSpanCreatingApiKey($spanCreatingApi)] = __LINE__ + 1;
                $span->end();
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::captureChildSpan';
                $func = function (SpanInterface $span) use ($spanCreatingApi): void {
                    $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                };
                ElasticApm::getCurrentTransaction()->captureChildSpan('test_span_name', 'test_span_type', $func);
                $expectedData[self::lineNumberForSpanCreatingApiKey($spanCreatingApi)] = __LINE__ - 1;
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Span::beginChildSpan';
                $parentSpan = ElasticApm::getCurrentTransaction()->beginChildSpan('parent_span_name', 'parent_span_type');
                $span = $parentSpan->beginChildSpan('test_span_name', 'test_span_type');
                $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);

                $expectedData[self::lineNumberForSpanCreatingApiKey($spanCreatingApi)] = __LINE__ + 1;
                $span->end();
                $parentSpan->end();
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Span::captureChildSpan';
                $parentSpan = ElasticApm::getCurrentTransaction()->beginChildSpan('parent_span_name', 'parent_span_type');
                $func = function (SpanInterface $span) use ($spanCreatingApi): void {
                    $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                };
                $parentSpan->captureChildSpan('test_span_name', 'test_span_type', $func);
                $expectedData[self::lineNumberForSpanCreatingApiKey($spanCreatingApi)] = __LINE__ - 1;
                $parentSpan->end();
            },
        ];
    }
}
