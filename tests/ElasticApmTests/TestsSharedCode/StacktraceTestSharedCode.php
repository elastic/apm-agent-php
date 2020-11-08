<?php

declare(strict_types=1);

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\StacktraceFrame;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;

use function ElasticApmTests\dummyFuncForTestsWithNamespace;

use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME;
use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER;
use const ElasticApmTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE;

final class StacktraceTestSharedCode
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
        dummyFuncForTestsWithNamespace(
            function () use ($createSpan, &$expectedData): void {
                $expectedData[self::lineNumberKey('$createSpan')] = __LINE__ + 1;
                $createSpan();
            } // <- this line's number is used for dummyFuncForTestsWithNamespace() call stack's frame
        );
        $expectedData[self::lineNumberKey('dummyFuncForTestsWithNamespace')] = __LINE__ - 2;
    }

    /**
     * @param callable             $createSpan
     * @param array<string, mixed> $expectedData
     *
     * @phpstan-param callable(): void $createSpan
     */
    public function myInstanceMethod(callable $createSpan, array &$expectedData): void
    {
        dummyFuncForTestsWithoutNamespace(
            function () use ($createSpan, &$expectedData): void {
                $expectedData[self::lineNumberKey('myStaticMethod')] = __LINE__ + 1;
                self::myStaticMethod($createSpan, /* ref */ $expectedData);
            } // <- this line's number is used for dummyFuncForTestsWithoutNamespace() call stack's frame
        );
        $expectedData[self::lineNumberKey('dummyFuncForTestsWithoutNamespace')] = __LINE__ - 2;
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
     * @param SpanData             $span
     */
    private static function assertPartImplOneSpan(array $expectedData, SpanData $span): void
    {
        $buildFuncName = function (string $funcName): string {
            return $funcName . '()';
        };

        $buildStaticMethodName = function (string $fullClassName, string $funcName): string {
            return $fullClassName . '::' . $funcName . '()';
        };

        $buildStacktraceFrame = function (string $function, string $fileName, int $lineNumber): StacktraceFrame {
            $result = new StacktraceFrame($fileName, $lineNumber);
            $result->function = $function;
            return $result;
        };

        $funcNameForClosureWithThisCaptured = self::buildMethodName(__CLASS__, __NAMESPACE__ . '\{closure}');
        $funcNameForClosureWithoutThisCaptured = $buildStaticMethodName(__CLASS__, __NAMESPACE__ . '\{closure}');

        /** @var string */
        $spanCreatingApi = TestCaseBase::getLabel($span, self::SPAN_CREATING_API_LABEL_KEY);

        /** @var StacktraceFrame[] */
        $expectedStacktrace = [];
        $expectedStacktrace[] = $buildStacktraceFrame(
            $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')],
            __FILE__,
            $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')]
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $funcNameForClosureWithoutThisCaptured,
            __FILE__,
            $expectedData[self::lineNumberKey('$createSpan')]
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
            $expectedData[self::lineNumberKey('dummyFuncForTestsWithNamespace')]
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $buildStaticMethodName(__CLASS__, 'myStaticMethod'),
            __FILE__,
            $expectedData[self::lineNumberKey('myStaticMethod')]
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $funcNameForClosureWithThisCaptured,
            DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME,
            DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            $buildFuncName('dummyFuncForTestsWithoutNamespace'),
            __FILE__,
            $expectedData[self::lineNumberKey('dummyFuncForTestsWithoutNamespace')]
        );
        $expectedStacktrace[] = $buildStacktraceFrame(
            self::buildMethodName(__CLASS__, 'myInstanceMethod'),
            __FILE__,
            $expectedData[self::lineNumberKey('myInstanceMethod')]
        );

        $actualStacktrace = $span->stacktrace;
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
     * @param array<string, SpanData> $idToSpan
     */
    public static function assertPartImpl(int $expectedSpansToCheckCount, array $expectedData, array $idToSpan): void
    {
        $checkedSpansCount = 0;
        /** @var SpanData $span */
        foreach ($idToSpan as $span) {
            $labels = is_null($span->context) ? [] : $span->context->labels;
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
                ElasticApm::getCurrentTransaction()->captureCurrentSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $span) use ($spanCreatingApi): void {
                        $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                    } // <- this line's number is used for captureCurrentSpan() call stack's frame
                );
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ - 2;
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
                ElasticApm::getCurrentTransaction()->captureChildSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $span) use ($spanCreatingApi): void {
                        $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                    } // <- this line's number is used for captureCurrentSpan() call stack's frame
                );
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ - 2;
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
                $parentSpan->captureChildSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $span) use ($spanCreatingApi): void {
                        $span->context()->setLabel(self::SPAN_CREATING_API_LABEL_KEY, $spanCreatingApi);
                    } // <- this line's number is used for captureCurrentSpan() call stack's frame
                );
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'line number')] = __LINE__ - 2;
                $expectedData[self::spanCreatingApiKey($spanCreatingApi, 'function')]
                    = self::buildMethodName(SpanInterface::class, 'captureChildSpan');
                $parentSpan->end();
            },
        ];
    }
}
