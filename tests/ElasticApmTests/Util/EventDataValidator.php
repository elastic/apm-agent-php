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

use ArrayAccess;
use Countable;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\StacktraceFrame;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use PHPUnit\Framework\Constraint\GreaterThan;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase;

abstract class EventDataValidator implements LoggableInterface
{
    use LoggableTrait;

    /**
     * 10 milliseconds (10000 microseconds) precision
     */
    public const TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS = 10000;

    /**
     * 1 millisecond
     */
    public const DURATION_COMPARISON_PRECISION_MILLISECONDS = 1;

    /**
     * @psalm-assert true $condition
     */
    protected static function assertTrue(bool $condition, string $message = ''): void
    {
        TestCase::assertTrue($condition, $message);
    }

    /**
     * @psalm-template ExpectedType
     *
     * @param ExpectedType $expected
     * @param mixed        $actual
     * @param string       $message
     *
     * @psalm-assert   =ExpectedType $actual
     */
    protected static function assertSame($expected, $actual, string $message = ''): void
    {
        TestCase::assertSame($expected, $actual, $message);
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert null $actual
     */
    protected static function assertNull($actual, string $message = ''): void
    {
        TestCase::assertNull($actual, $message);
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert !null $actual
     */
    protected static function assertNotNull($actual, string $message = ''): void
    {
        TestCase::assertNotNull($actual, $message);
    }

    /**
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $message
     */
    protected static function assertSameNullness($expected, $actual, string $message = ''): void
    {
        TestCase::assertThat(
            $actual,
            ($expected === null) ? TestCase::isNull() : TestCase::logicalNot(TestCase::isNull()),
            $message
        );
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert string $actual
     */
    protected static function assertIsString($actual, string $message = ''): void
    {
        TestCase::assertIsString($actual, $message);
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert int $actual
     */
    protected static function assertIsInt($actual, string $message = ''): void
    {
        TestCase::assertIsInt($actual, $message);
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert int|float $actual
     */
    protected static function assertIsNumber($actual, string $message = ''): void
    {
        TestCase::assertThat(
            $actual,
            TestCase::logicalOr(new IsType(IsType::TYPE_INT), new IsType(IsType::TYPE_FLOAT)),
            $message
        );
    }

    /**
     * @param mixed  $actual
     * @param string $message
     *
     * @psalm-assert bool $actual
     */
    protected static function assertIsBool($actual, string $message = ''): void
    {
        TestCase::assertIsBool($actual, $message);
    }

    /**
     * @param int|float $expected
     * @param int|float $actual
     * @param string    $message
     */
    protected static function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        TestCase::assertGreaterThan($expected, $actual, $message);
    }

    /**
     * @param int|float $expected
     * @param int|float $actual
     * @param string    $message
     */
    protected static function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void
    {
        TestCase::assertGreaterThanOrEqual($expected, $actual, $message);
    }

    /**
     * @param int|float $rangeBegin
     * @param int|float $actual
     * @param int|float $rangeEnd
     * @param string    $message
     */
    protected static function assertInClosedRange($rangeBegin, $actual, $rangeEnd, string $message = ''): void
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

    protected static function assertEqualTimestamp(float $expected, float $actual): void
    {
        TestCase::assertEqualsWithDelta($expected, $actual, self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS);
    }

    public static function assertLessThanOrEqualTimestamp(float $before, float $after): void
    {
        TestCase::assertThat(
            $before,
            TestCase::logicalOr(
                new IsEqual($after, /* delta: */ self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS),
                new LessThan($after)
            ),
            ' before: ' . number_format($before) . ', after: ' . number_format($after)
        );
    }

    protected static function assertLessThanOrEqualDuration(float $less, float $more): void
    {
        TestCase::assertThat(
            $less,
            TestCase::logicalOr(
                new IsEqual($more, /* delta: */ self::DURATION_COMPARISON_PRECISION_MILLISECONDS),
                new LessThan($more)
            )
        );
    }

    protected static function assertTimestampInRange(
        float $pastTimestamp,
        float $timestamp,
        float $futureTimestamp
    ): void {
        self::assertLessThanOrEqualTimestamp($pastTimestamp, $timestamp);
        self::assertLessThanOrEqualTimestamp($timestamp, $futureTimestamp);
    }

    /**
     * @param int|string                                              $key
     * @param array<int|string, mixed>|ArrayAccess<int|string, mixed> $array
     * @param string                                                  $message
     */
    protected static function assertArrayHasKey($key, $array, string $message = ''): void
    {
        TestCase::assertArrayHasKey($key, $array, $message);
    }

    /**
     * @param int                       $expectedCount
     * @param Countable|iterable<mixed> $haystack
     * @param string                    $message
     */
    protected static function assertCount(int $expectedCount, $haystack, string $message = ''): void
    {
        TestCase::assertCount($expectedCount, $haystack, $message);
    }

    /**
     * @param mixed $id
     * @param int   $expectedSizeInBytes
     *
     * @return string
     */
    protected static function validateIdEx($id, int $expectedSizeInBytes): string
    {
        self::assertIsString($id);
        /** @var string $id */
        self::assertTrue(IdValidationUtil::isValidHexNumberString($id, $expectedSizeInBytes));
        return $id;
    }

    /**
     * @param mixed $traceId
     *
     * @return string
     */
    public static function validateTraceId($traceId): string
    {
        return self::validateIdEx($traceId, Constants::TRACE_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $stringValue
     * @param bool  $isNullable
     * @param int   $maxLength
     *
     * @return ?string
     */
    public static function validateString($stringValue, bool $isNullable, int $maxLength): ?string
    {
        if ($stringValue === null) {
            self::assertTrue($isNullable);
            return null;
        }

        self::assertIsString($stringValue);
        /** @var string $stringValue */

        self::assertTrue(strlen($stringValue) <= $maxLength);
        return $stringValue;
    }

    /**
     * @param mixed $keywordString
     *
     * @return string
     */
    public static function validateNullableKeywordString($keywordString): ?string
    {
        return self::validateString($keywordString, /* isNullable: */ true, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    /**
     * @param mixed $keywordString
     *
     * @return string
     */
    public static function validateKeywordString($keywordString): string
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $value = self::validateString($keywordString, /* isNullable: */ false, Constants::KEYWORD_STRING_MAX_LENGTH);
        /** @var string $value */
        return $value;
    }

    /**
     * @param mixed $nonKeywordString
     *
     * @return string
     */
    public static function validateNullableNonKeywordString($nonKeywordString): ?string
    {
        return self::validateString(
            $nonKeywordString,
            /* isNullable: */ true,
            Constants::NON_KEYWORD_STRING_MAX_LENGTH
        );
    }

    /**
     * @param mixed              $timestamp
     * @param ?EventDataExpected $expectedArg
     *
     * @return float
     */
    public static function validateTimestamp($timestamp, ?EventDataExpected $expectedArg = null): float
    {
        self::assertIsNumber($timestamp);
        /** @var float|int $timestamp */

        $expected = $expectedArg ?? new EventDataExpected();

        self::assertTimestampInRange($expected->timeBefore, $timestamp, $expected->timeAfter);

        return floatval($timestamp);
    }

    /**
     * @param mixed $duration
     *
     * @return float
     */
    public static function validateDuration($duration): float
    {
        self::assertIsNumber($duration);
        /** @var float|int $duration */

        self::assertGreaterThanOrEqual(0, $duration);
        return floatval($duration);
    }

    /**
     * @param mixed $filename
     *
     * @return string
     */
    public static function validateStacktraceFrameFilename($filename): string
    {
        self::assertIsString($filename);
        /** @var string $filename */
        self::assertTrue(!TextUtil::isEmptyString($filename));

        return $filename;
    }

    /**
     * @param mixed $lineNumber
     *
     * @return int
     */
    public static function validateStacktraceFrameLineNumber($lineNumber): int
    {
        self::assertTrue(is_int($lineNumber));
        /** @var int $lineNumber */
        self::assertTrue($lineNumber >= 0);

        return $lineNumber;
    }

    /**
     * @param mixed $function
     *
     * @return string|null
     */
    public static function validateStacktraceFrameFunction($function): ?string
    {
        if ($function !== null) {
            self::assertIsString($function);
            /** @var string $function */
            self::assertTrue(!TextUtil::isEmptyString($function));
        }

        return $function;
    }

    public static function validateStacktraceFrame(StacktraceFrame $stacktraceFrame): void
    {
        self::validateStacktraceFrameFilename($stacktraceFrame->filename);
        self::validateStacktraceFrameLineNumber($stacktraceFrame->lineno);
        self::validateStacktraceFrameFunction($stacktraceFrame->function);
    }

    /**
     * @param StacktraceFrame[] $stacktrace
     */
    public static function validateStacktrace(array $stacktrace): void
    {
        foreach ($stacktrace as $stacktraceFrame) {
            self::validateStacktraceFrame($stacktraceFrame);
        }
    }

    /**
     * @param mixed $value
     *
     * @return int|null
     */
    public static function validateNullableHttpStatusCode($value): ?int
    {
        if (is_null($value)) {
            return null;
        }

        self::assertTrue(is_int($value));
        assert(is_int($value));
        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function validateBool($value): bool
    {
        self::assertIsBool($value);
        /** @var bool $value */
        return $value;
    }
}
