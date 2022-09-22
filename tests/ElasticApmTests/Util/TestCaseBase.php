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

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\NoopLogSink;
use Elastic\Apm\Impl\NoopEventSink;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
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

    public static function assertTransactionEquals(TransactionData $expected, TransactionData $actual): void
    {
        self::assertEquals($expected, $actual);
    }

    public static function assertSpanEquals(SpanData $expected, SpanData $actual): void
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

    public static function getExecutionSegmentContext(ExecutionSegmentData $execSegData): ?ExecutionSegmentContextData
    {
        if ($execSegData instanceof SpanData) {
            return $execSegData->context;
        }

        self::assertInstanceOf(TransactionData::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->context;
    }

    /**
     * @param ExecutionSegmentData $execSegData
     * @param string               $key
     *
     * @return bool
     */
    public static function hasLabel(ExecutionSegmentData $execSegData, string $key): bool
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null) {
            return false;
        }
        return array_key_exists($key, $context->labels);
    }

    /**
     * @param int                  $expectedCount
     * @param ExecutionSegmentData $execSegData
     *
     * @return void
     */
    public static function assertLabelsCount(int $expectedCount, ExecutionSegmentData $execSegData): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null) {
            self::assertSame(0, $expectedCount, LoggableToString::convert($execSegData));
            return;
        }
        self::assertCount($expectedCount, $context->labels);
    }

    /**
     * @param ExecutionSegmentData $execSegData
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function getLabels(ExecutionSegmentData $execSegData): array
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null) {
            return [];
        }
        return $context->labels;
    }

    /**
     * @param ExecutionSegmentData $execSegData
     * @param string               $key
     *
     * @return string|bool|int|float|null
     */
    public static function getLabel(ExecutionSegmentData $execSegData, string $key)
    {
        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context);
        self::assertArrayHasKey($key, $context->labels);
        return $context->labels[$key];
    }

    public static function assertHasLabel(ExecutionSegmentData $execSegData, string $key, string $message = ''): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context, LoggableToString::convert(['execSegData' => $execSegData]) . '. ' . $message);
        self::assertArrayHasKey(
            $key,
            $context->labels,
            LoggableToString::convert(['key' => $key, 'execSegData' => $execSegData]) . '. ' . $message
        );
    }

    public static function assertNotHasLabel(ExecutionSegmentData $execSegData, string $key): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null) {
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
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualLists(array $expected, array $actual, string $message = ''): void
    {
        self::assertTrue(sort(/* ref */ $expected));
        self::assertTrue(sort(/* ref */ $actual));
        self::assertEqualsCanonicalizing($expected, $actual, $message);
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

    public static function getParentId(ExecutionSegmentData $execSegData): ?string
    {
        if ($execSegData instanceof SpanData) {
            return $execSegData->parentId;
        }

        self::assertInstanceOf(TransactionData::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->parentId;
    }

    /** @noinspection PhpIfWithCommonPartsInspection */
    public static function setParentId(ExecutionSegmentData $execSegData, ?string $newParentId): void
    {
        if ($execSegData instanceof SpanData) {
            self::assertNotNull($newParentId);
            $execSegData->parentId = $newParentId;
        } else {
            self::assertInstanceOf(TransactionData::class, $execSegData, DbgUtil::getType($execSegData));
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
     * @return iterable<array<bool>>
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

    public static function calcEndTime(ExecutionSegmentData $timedData): float
    {
        return $timedData->timestamp + TimeUtil::millisecondsToMicroseconds($timedData->duration);
    }

    /**
     * @param TransactionData         $transaction
     * @param array<string, SpanData> $idToSpan
     * @param bool                    $forceEnableFlakyAssertions
     */
    protected static function assertValidTransactionAndSpans(
        TransactionData $transaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        TraceDataValidator::validate(
            new TraceDataActual([$transaction->id => $transaction], $idToSpan),
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
}
