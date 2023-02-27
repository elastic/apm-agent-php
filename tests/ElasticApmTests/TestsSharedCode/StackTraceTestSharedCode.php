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
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;

use function ElasticApmTests\dummyFuncForTestsWithNamespace;

use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME;
use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER;
use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE;

final class StackTraceTestSharedCode
{
    public const SPAN_CREATING_API_LABEL_KEY = 'stacktrace / span creating API';

    private static function lineNumberKey(string $methodName): string
    {
        return 'stacktrace / ' . $methodName . ' / line number';
    }

    private static function spanCreatingApiKey(string $spanCreatingApi, string $suffix): string
    {
        return 'stacktrace / span creating API: ' . $spanCreatingApi . ' / ' . $suffix;
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
     * @return string
     */
    private static function getStringFromExpectedData(array $expectedData, string $key): string
    {
        $value = $expectedData[$key];
        TestCase::assertIsString($value, LoggableToString::convert(['$key' => $key]));
        return $value;
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
        TestCase::assertIsInt($value, LoggableToString::convert(['$key' => $key]));
        return $value;
    }

    /**
     * @param array<string, mixed> $expectedData
     * @param SpanDto              $span
     */
    private static function assertPartImplOneSpan(array $expectedData, SpanDto $span): void
    {
        $buildFuncName = function (string $funcName): string {
            return $funcName . '()';
        };

        $buildStaticMethodName = function (string $fullClassName, string $funcName): string {
            return $fullClassName . '::' . $funcName . '()';
        };

        $buildStacktraceFrame = function (string $function, string $fileName, int $lineNumber): StackTraceFrame {
            $result = new StackTraceFrame($fileName, $lineNumber);
            $result->function = $function;
            return $result;
        };

        $funcNameForClosureWithThisCaptured = self::buildMethodName(__CLASS__, __NAMESPACE__ . '\{closure}');
        $funcNameForClosureWithoutThisCaptured = $buildStaticMethodName(__CLASS__, __NAMESPACE__ . '\{closure}');

        /** @var string $spanCreatingApi */
        $spanCreatingApi = TestCaseBase::getLabel($span, self::SPAN_CREATING_API_LABEL_KEY);

        /** @var StackTraceFrame[] $expectedStacktrace */
        $expectedStacktrace = [];
        $expectedStacktrace[] = $buildStacktraceFrame(
            self::getStringFromExpectedData($expectedData, self::spanCreatingApiKey($spanCreatingApi, 'function')),
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::spanCreatingApiKey($spanCreatingApi, 'line number'))
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $funcNameForClosureWithoutThisCaptured,
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('$createSpan'))
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $funcNameForClosureWithoutThisCaptured,
            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME,
            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $buildFuncName(
                DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE . '\\' . 'dummyFuncForTestsWithNamespace'
            ),
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('dummyFuncForTestsWithNamespace'))
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $buildStaticMethodName(__CLASS__, 'myStaticMethod'),
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('myStaticMethod'))
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $funcNameForClosureWithThisCaptured,
            DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME,
            DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $buildFuncName('dummyFuncForTestsWithoutNamespace'),
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('dummyFuncForTestsWithoutNamespace'))
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            self::buildMethodName(__CLASS__, 'myInstanceMethod'),
            __FILE__,
            self::getIntFromExpectedData($expectedData, self::lineNumberKey('myInstanceMethod'))
        );

        $actualStacktrace = $span->stackTrace;
        TestCase::assertNotNull($actualStacktrace);
        for ($i = 0; $i < count($expectedStacktrace); ++$i) {
            $infoMsg = LoggableToString::convert(
                ['expected' => $expectedStacktrace[$i], 'actual' => $actualStacktrace[$i]]
            );
            TestCase::assertSame($expectedStacktrace[$i]->function, $actualStacktrace[$i]->function, $infoMsg);
            TestCase::assertSame($expectedStacktrace[$i]->filename, $actualStacktrace[$i]->filename, $infoMsg);
            TestCase::assertSame($expectedStacktrace[$i]->lineno, $actualStacktrace[$i]->lineno, $infoMsg);
        }
    }

    /**
     * @param int                     $expectedSpansToCheckCount
     * @param array<string, mixed>    $expectedData
     * @param array<string, SpanDto> $idToSpan
     */
    public static function assertPartImpl(int $expectedSpansToCheckCount, array $expectedData, array $idToSpan): void
    {
        $checkedSpansCount = 0;
        foreach ($idToSpan as $span) {
            $labels = $span->context === null ? [] : $span->context->labels;
            TestCase::assertNotNull($labels);
            if (array_key_exists(self::SPAN_CREATING_API_LABEL_KEY, $labels)) {
                self::assertPartImplOneSpan($expectedData, $span);
                ++$checkedSpansCount;
            }
        }
        TestCase::assertSame($expectedSpansToCheckCount, $checkedSpansCount);
    }

    public static function buildMethodName(string $fullClassName, string $funcName): string
    {
        return $fullClassName . '->' . $funcName . '()';
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

                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(SpanInterface::class, 'end');
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ + 1;
                $span->end();
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::captureCurrentSpan';
                $func = function (SpanInterface $span) use ($spanCreatingApi): void {
                    $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                };
                ElasticApm::getCurrentTransaction()->captureCurrentSpan('test_span_name', 'test_span_type', $func);
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ - 1;
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(TransactionInterface::class, 'captureCurrentSpan');
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::beginChildSpan';
                $span = ElasticApm::getCurrentTransaction()->beginChildSpan('test_span_name', 'test_span_type');
                $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);

                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(SpanInterface::class, 'end');
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ + 1;
                $span->end();
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Transaction::captureChildSpan';
                $func = function (SpanInterface $span) use ($spanCreatingApi): void {
                    $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                };
                ElasticApm::getCurrentTransaction()->captureChildSpan('test_span_name', 'test_span_type', $func);
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ - 1;
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(TransactionInterface::class, 'captureChildSpan');
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Span::beginChildSpan';
                $parentSpan = ElasticApm::getCurrentTransaction()
                                        ->beginChildSpan('parent_span_name', 'parent_span_type');
                $span = $parentSpan->beginChildSpan('test_span_name', 'test_span_type');
                $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);

                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(SpanInterface::class, 'end');
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ + 1;
                $span->end();
                $parentSpan->end();
            },
            function () use (&$expectedData): void {
                $spanCreatingApi = 'Span::captureChildSpan';
                $parentSpan = ElasticApm::getCurrentTransaction()
                                        ->beginChildSpan('parent_span_name', 'parent_span_type');
                $func = function (SpanInterface $span) use ($spanCreatingApi): void {
                    $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                };
                $parentSpan->captureChildSpan('test_span_name', 'test_span_type', $func);
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ - 1;
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(SpanInterface::class, 'captureChildSpan');
                $parentSpan->end();
            },
        ];
    }
}
