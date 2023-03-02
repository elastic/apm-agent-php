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

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\OptionWithDefaultValueMetadata;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\NoopLogSink;
use Elastic\Apm\Impl\NoopEventSink;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use PHPUnit\Framework\Constraint\Exception as ConstraintException;
use PHPUnit\Framework\Constraint\GreaterThan;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Throwable;

class TestCaseBase extends TestCase
{
    /**
     * 10 milliseconds (10000 microseconds) precision
     */
    public const TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS = 10000;

    public const DURATION_COMPARISON_PRECISION_MILLISECONDS = self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS / 1000;

    /** @var ?LoggerFactory */
    private static $noopLoggerFactory = null;

    /** @var ?Logger */
    private $logger = null;

    public static function assertTransactionEquals(TransactionDto $expected, TransactionDto $actual): void
    {
        self::assertEquals($expected, $actual);
    }

    public static function assertSpanEquals(SpanDto $expected, SpanDto $actual): void
    {
        self::assertEquals($expected, $actual);
    }

    /**
     * Asserts that the callable throws a specified throwable.
     * If successful and the inspection callable is not null
     * then it is called and the caught exception is passed as argument.
     *
     * @param string        $class   The exception type expected to be thrown.
     * @param callable      $execute The callable.
     * @param string        $message
     * @param callable|null $inspect [optional] The inspector.
     */
    public static function assertThrows(
        string $class,
        callable $execute,
        string $message = '',
        callable $inspect = null
    ): void {
        try {
            $execute();
        } catch (ExpectationFailedException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            static::assertThat($ex, new ConstraintException($class), $message);

            if ($inspect !== null) {
                $inspect($ex);
            }

            return;
        }

        static::assertThat(null, new ConstraintException($class), $message);
    }

    /**
     * @param array<mixed> $subSet
     * @param array<mixed> $largerSet
     */
    public static function assertListArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        self::assertTrue(
            count(array_intersect($subSet, $largerSet)) === count($subSet),
            LoggableToString::convert(
                [
                    'array_diff'             => array_diff($subSet, $largerSet),
                    'count(array_intersect)' => count(array_intersect($subSet, $largerSet)),
                    'count($subSet)'         => count($subSet),
                    'array_intersect'        => array_intersect($subSet, $largerSet),
                    '$subSet'                => $subSet,
                    '$largerSet'             => $largerSet,
                ]
            )
        );
    }

    /**
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $message
     */
    public static function assertSameEx($expected, $actual, string $message = ''): void
    {
        /**
         * @param mixed $value
         *
         * @return bool
         */
        $isNumeric = function ($value): bool {
            return is_float($value) || is_int($value);
        };
        if ($isNumeric($expected) && $isNumeric($actual) && (is_float($expected) !== is_float($actual))) {
            self::assertSame(floatval($expected), floatval($actual), $message);
        } else {
            self::assertSame($expected, $actual, $message);
        }
    }

    /**
     * @param array<mixed, mixed> $subSet
     * @param array<mixed, mixed> $largerSet
     */
    public static function assertMapArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        foreach ($subSet as $key => $value) {
            $ctx = LoggableToString::convert(
                [
                    '$key'       => $key,
                    '$value'     => $value,
                    '$subSet'    => $subSet,
                    '$largerSet' => $largerSet,
                ]
            );
            self::assertArrayHasKey($key, $largerSet, $ctx);
            self::assertSameEx($value, $largerSet[$key], $ctx);
        }
    }

    public static function getExecutionSegmentContext(ExecutionSegmentDto $execSegData): ?ExecutionSegmentContextDto
    {
        if ($execSegData instanceof SpanDto) {
            return $execSegData->context;
        }

        self::assertInstanceOf(TransactionDto::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->context;
    }

    /**
     * @param ExecutionSegmentDto $execSegData
     * @param string               $key
     *
     * @return bool
     */
    public static function hasLabel(ExecutionSegmentDto $execSegData, string $key): bool
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null) {
            return false;
        }
        return $context->labels !== null && array_key_exists($key, $context->labels);
    }

    /**
     * @param int                  $expectedCount
     * @param ExecutionSegmentDto $execSegData
     *
     * @return void
     */
    public static function assertLabelsCount(int $expectedCount, ExecutionSegmentDto $execSegData): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null || $context->labels === null) {
            self::assertSame(0, $expectedCount, LoggableToString::convert($execSegData));
            return;
        }
        self::assertCount($expectedCount, $context->labels);
    }

    /**
     * @param ExecutionSegmentDto $execSegData
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function getLabels(ExecutionSegmentDto $execSegData): array
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null || $context->labels === null) {
            return [];
        }
        return $context->labels;
    }

    /**
     * @param ExecutionSegmentDto $execSegData
     * @param string               $key
     *
     * @return string|bool|int|float|null
     */
    public static function getLabel(ExecutionSegmentDto $execSegData, string $key)
    {
        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context);
        self::assertNotNull($context->labels);
        self::assertArrayHasKey($key, $context->labels);
        return $context->labels[$key];
    }

    public static function assertHasLabel(ExecutionSegmentDto $execSegData, string $key, string $message = ''): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        $dbgCtx = ['key' => $key, 'execSegData' => $execSegData, 'message' => $message];
        $dbgCtxStr = LoggableToString::convert($dbgCtx);
        self::assertNotNull($context, $dbgCtxStr);
        self::assertNotNull($context->labels, $dbgCtxStr);
        self::assertArrayHasKey($key, $context->labels, $dbgCtxStr);
    }

    public static function assertNotHasLabel(ExecutionSegmentDto $execSegData, string $key): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null || $context->labels === null) {
            return;
        }
        self::assertArrayNotHasKey($key, $context->labels);
    }

    /**
     * @param array<mixed, mixed> $actual
     */
    public static function assertArrayIsList(array $actual): void
    {
        TestCase::assertTrue(ArrayUtil::isList($actual), LoggableToString::convert(['$actual' => $actual]));
    }

    /**
     * @param mixed[]              $expected
     * @param mixed[]              $actual
     * @param array<string, mixed> $dbgCtxOuter
     */
    public static function assertEqualLists(array $expected, array $actual, array $dbgCtxOuter = []): void
    {
        $dbgCtxTop = array_merge(['expected' => $expected, 'actual' => $actual], $dbgCtxOuter);
        self::assertSame(count($expected), count($actual), LoggableToString::convert($dbgCtxTop));
        foreach (RangeUtil::generateUpTo(count($expected)) as $i) {
            $dbgCtxPerIndex = array_merge(['i' => $i], $dbgCtxTop);
            self::assertSame($expected[$i], $actual[$i], LoggableToString::convert($dbgCtxPerIndex));
        }
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualAsSets(array $expected, array $actual, string $message = ''): void
    {
        self::assertTrue(sort(/* ref */ $expected));
        self::assertTrue(sort(/* ref */ $actual));
        self::assertEqualsCanonicalizing($expected, $actual, $message);
    }

    /**
     * @param array<mixed, mixed> $subsetMap
     * @param array<mixed, mixed> $containingMap
     */
    public static function assertMapIsSubsetOf(array $subsetMap, array $containingMap, string $message = ''): void
    {
        $ctx = $message === ''
            ? LoggableToString::convert(['subsetMap' => $subsetMap, 'containingMap' => $containingMap])
            : $message;
        self::assertGreaterThanOrEqual(count($subsetMap), count($containingMap), $ctx);
        foreach ($subsetMap as $subsetMapKey => $subsetMapVal) {
            self::assertArrayHasKey($subsetMapKey, $containingMap, $ctx);
            self::assertEquals($subsetMapVal, $containingMap[$subsetMapKey], $ctx);
        }
    }

    /**
     * @param array<mixed, mixed> $expected
     * @param array<mixed, mixed> $actual
     */
    public static function assertEqualMaps(array $expected, array $actual, string $message = ''): void
    {
        self::assertMapIsSubsetOf($expected, $actual, $message);
        self::assertMapIsSubsetOf($actual, $expected, $message);
    }

    /**
     * @param array<string|int, mixed> $idToXyzMap
     *
     * @return string[]
     */
    public static function getIdsFromIdToMap(array $idToXyzMap): array
    {
        /** @var string[] $result */
        $result = [];
        foreach ($idToXyzMap as $id => $_) {
            $result[] = strval($id);
        }
        return $result;
    }

    public static function buildTracerForTests(?EventSinkInterface $eventSink = null): TracerBuilderForTests
    {
        return TracerBuilderForTests::startNew()
                                    ->withClock(AmbientContextForTests::clock())
                                    ->withLogSink(NoopLogSink::singletonInstance())
                                    ->withEventSink($eventSink ?? NoopEventSink::singletonInstance());
    }

    public static function noopLoggerFactory(): LoggerFactory
    {
        if (self::$noopLoggerFactory === null) {
            self::$noopLoggerFactory = new LoggerFactory(
                new LogBackend(LogLevel::OFF, NoopLogSink::singletonInstance())
            );
        }
        return self::$noopLoggerFactory;
    }

    public static function getParentId(ExecutionSegmentDto $execSegData): ?string
    {
        if ($execSegData instanceof SpanDto) {
            return $execSegData->parentId;
        }

        self::assertInstanceOf(TransactionDto::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->parentId;
    }

    /** @noinspection PhpIfWithCommonPartsInspection */
    public static function setParentId(ExecutionSegmentDto $execSegData, ?string $newParentId): void
    {
        if ($execSegData instanceof SpanDto) {
            self::assertNotNull($newParentId);
            $execSegData->parentId = $newParentId;
        } else {
            self::assertInstanceOf(TransactionDto::class, $execSegData, DbgUtil::getType($execSegData));
            $execSegData->parentId = $newParentId;
        }

        self::assertSame($newParentId, self::getParentId($execSegData));
    }

    public static function generateDummyMaxKeywordString(string $prefix = ''): string
    {
        $halfLen = Constants::KEYWORD_STRING_MAX_LENGTH / 2;
        return
            $prefix
            . '['
            . str_repeat('V', $halfLen - strlen($prefix) - 4)
            . ','
            . ';'
            . str_repeat('W', $halfLen)
            . ']';
    }

    /**
     * @return iterable<array{bool}>
     */
    public function boolDataProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     *
     * @return Logger
     */
    protected function getLogger(string $namespace, string $fqClassName, string $srcCodeFile): Logger
    {
        if ($this->logger === null) {
            $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
                LogCategoryForTests::TEST,
                $namespace,
                $fqClassName,
                $srcCodeFile
            )->addContext('this', $this);
        }
        self::assertNotNull($this->logger);

        return $this->logger;
    }

    /**
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     *
     * @return Logger
     */
    public static function getLoggerStatic(string $namespace, string $fqClassName, string $srcCodeFile): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST,
            $namespace,
            $fqClassName,
            $srcCodeFile
        );
    }

    public static function dummyAssert(): void
    {
        self::assertTrue(true);
    }

    /**
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $message
     */
    public static function assertSameNullness($expected, $actual, string $message = ''): void
    {
        TestCase::assertThat(
            $actual,
            ($expected === null) ? TestCase::isNull() : TestCase::logicalNot(TestCase::isNull()),
            LoggableToString::convert(
                [
                    '$expected' => $expected,
                    '$actual' => $actual,
                    $message
                ]
            )
        );
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert int|float $actual
     */
    public static function assertIsNumber($actual, string $message = ''): void
    {
        TestCase::assertThat(
            $actual,
            TestCase::logicalOr(new IsType(IsType::TYPE_INT), new IsType(IsType::TYPE_FLOAT)),
            $message
        );
    }

    public static function assertGreaterThanZero(int $actual, string $message = ''): void
    {
        TestCase::assertGreaterThan(0, $actual, $message);
    }

    /**
     * @param int|float $rangeBegin
     * @param int|float $actual
     * @param int|float $rangeEnd
     * @param string    $message
     */
    public static function assertInClosedRange($rangeBegin, $actual, $rangeEnd, string $message = ''): void
    {
        TestCase::assertThat(
            $actual,
            TestCase::logicalAnd(
                TestCase::logicalOr(new IsEqual($rangeBegin), new GreaterThan($rangeBegin)),
                TestCase::logicalOr(new IsEqual($rangeEnd), new LessThan($rangeEnd))
            ),
            $message
        );
    }

    public static function assertLessThanOrEqualTimestamp(float $before, float $after): void
    {
        TestCase::assertThat(
            $before,
            TestCase::logicalOr(
                new IsEqual($after, /* delta: */ self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS),
                new LessThan($after)
            ),
            LoggableToString::convert(
                [
                    'before as duration' => TimeUtil::formatDurationInMicroseconds($before),
                    'after as duration'  => TimeUtil::formatDurationInMicroseconds($after),
                    'after - before'     => TimeUtil::formatDurationInMicroseconds($after - $before),
                    'before as number'   => number_format($before),
                    'after as number'    => number_format($after),
                ]
            )
        );
    }

    public static function assertTimestampInRange(
        float $pastTimestamp,
        float $timestamp,
        float $futureTimestamp
    ): void {
        TestCaseBase::assertLessThanOrEqualTimestamp($pastTimestamp, $timestamp);
        TestCaseBase::assertLessThanOrEqualTimestamp($timestamp, $futureTimestamp);
    }

    public static function calcEndTime(ExecutionSegmentDto $timedData): float
    {
        return $timedData->timestamp + TimeUtil::millisecondsToMicroseconds($timedData->duration);
    }

    /**
     * @param TransactionDto         $transaction
     * @param array<string, SpanDto> $idToSpan
     * @param bool                    $forceEnableFlakyAssertions
     */
    protected static function assertValidTransactionAndSpans(
        TransactionDto $transaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        TraceValidator::validate(
            new TraceActual([$transaction->id => $transaction], $idToSpan),
            null /* <- expected */,
            $forceEnableFlakyAssertions
        );
    }

    /**
     * @template T
     *
     * @param Optional<T> $expected
     * @param T           $actual
     */
    public static function assertSameExpectedOptional(Optional $expected, $actual): void
    {
        if ($expected->isValueSet()) {
            self::assertSame($expected->getValue(), $actual);
        }
    }

    /**
     * @param string|int           $expectedKey
     * @param mixed                $expectedVal
     * @param array<string, mixed> $actualArray
     */
    public static function assertSameValueInArray(
        $expectedKey,
        $expectedVal,
        array $actualArray,
        string $message = ''
    ): void {
        $ctx = (!empty($message)) ? $message : LoggableToString::convert(
            [
                'expectedKey' => $expectedKey,
                'expectedVal' => $expectedVal,
                'actualArray' => $actualArray,
            ]
        );
        self::assertArrayHasKey($expectedKey, $actualArray, $ctx);
        self::assertSame($expectedVal, $actualArray[$expectedKey], $ctx);
    }

    /**
     * @param string               $expectedKey
     * @param mixed                $expectedVal
     * @param array<string, mixed> $actualArray
     */
    public static function assertEqualValueInArray(string $expectedKey, $expectedVal, array $actualArray): void
    {
        self::assertArrayHasKey($expectedKey, $actualArray);
        self::assertEquals($expectedVal, $actualArray[$expectedKey]);
    }

    /**
     * @template T
     *
     * @param T $rangeBegin
     * @param T $val
     * @param T $rangeInclusiveEnd
     *
     * @return void
     */
    public static function assertInRangeInclusive($rangeBegin, $val, $rangeInclusiveEnd): void
    {
        self::assertGreaterThanOrEqual($rangeBegin, $val);
        self::assertLessThanOrEqual($rangeInclusiveEnd, $val);
    }

    /**
     * @param string $optName
     * @param mixed  $val
     *
     * @return bool
     */
    public static function equalsConfigDefaultValue(string $optName, $val): bool
    {
        $optMeta = AllOptionsMetadata::get()[$optName];
        if (!$optMeta instanceof OptionWithDefaultValueMetadata) {
            return false;
        }
        return $val == $optMeta->defaultValue();
    }

    /**
     * @param string|int          $key
     * @param mixed               $expectedValue
     * @param array<mixed, mixed> $array
     * @param string              $message
     *
     * @return void
     */
    public static function assertArrayHasKeyWithValue($key, $expectedValue, array $array, string $message = ''): void
    {
        self::assertArrayHasKey($key, $array, $message);
        self::assertSame($expectedValue, $array[$key], $message);
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }
}
