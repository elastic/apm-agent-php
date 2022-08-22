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
use Elastic\Apm\Impl\ExecutionSegmentContext;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\StacktraceFrame;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use PHPUnit\Framework\TestCase;

abstract class DataValidator implements LoggableInterface
{
    use LoggableTrait;

    /**
     * @param mixed $id
     * @param int   $expectedSizeInBytes
     *
     * @return string
     */
    protected static function validateIdEx($id, int $expectedSizeInBytes): string
    {
        TestCase::assertIsString($id);
        /** @var string $id */
        TestCase::assertTrue(
            IdValidationUtil::isValidHexNumberString($id, $expectedSizeInBytes),
            LoggableToString::convert(['$id' => $id, '$expectedSizeInBytes' => $expectedSizeInBytes])
        );
        return $id;
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
            TestCase::assertTrue($isNullable);
            return null;
        }

        TestCase::assertIsString($stringValue);
        /** @var string $stringValue */

        TestCase::assertTrue(strlen($stringValue) <= $maxLength);
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
     * @param mixed                  $timestamp
     * @param ?EventDataExpectations $expectationsArg
     *
     * @return float
     */
    public static function validateTimestamp($timestamp, ?EventDataExpectations $expectationsArg = null): float
    {
        TestCaseBase::assertIsNumber($timestamp);
        /** @var float|int $timestamp */
        $expectations = $expectationsArg ?? new EventDataExpectations();
        TestCaseBase::assertTimestampInRange($expectations->timestampBefore, $timestamp, $expectations->timestampAfter);
        return floatval($timestamp);
    }

    /**
     * @param mixed $duration
     *
     * @return float
     */
    public static function validateDuration($duration): float
    {
        TestCaseBase::assertIsNumber($duration);
        /** @var float|int $duration */

        TestCase::assertGreaterThanOrEqual(0, $duration);
        return floatval($duration);
    }

    /**
     * @param mixed $filename
     *
     * @return string
     */
    public static function validateStacktraceFrameFilename($filename): string
    {
        TestCase::assertIsString($filename);
        /** @var string $filename */
        TestCase::assertTrue(!TextUtil::isEmptyString($filename));

        return $filename;
    }

    /**
     * @param mixed $lineNumber
     *
     * @return int
     */
    public static function validateStacktraceFrameLineNumber($lineNumber): int
    {
        TestCase::assertTrue(is_int($lineNumber));
        /** @var int $lineNumber */
        TestCase::assertTrue($lineNumber >= 0);

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
            TestCase::assertIsString($function);
            /** @var string $function */
            TestCase::assertTrue(!TextUtil::isEmptyString($function));
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
        if ($value === null) {
            return null;
        }

        TestCase::assertTrue(is_int($value));
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
        TestCase::assertIsBool($value);
        /** @var bool $value */
        return $value;
    }

    public static function setCommonProperties(object $src, object $dst): int
    {
        $count = 0;
        foreach (get_object_vars($src) as $propName => $propValue) {
            if (!property_exists($dst, $propName)) {
                continue;
            }
            $dst->$propName = $propValue;
            ++$count;
        }
        return $count;
    }

    /**
     * @param mixed $labels
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function validateLabels($labels): array
    {
        TestCase::assertTrue(is_array($labels));
        /** @var array<mixed, mixed> $labels */
        foreach ($labels as $key => $value) {
            self::validateKeywordString($key);
            TestCase::assertTrue(ExecutionSegmentContext::doesValueHaveSupportedLabelType($value));
            if (is_string($value)) {
                self::validateKeywordString($value);
            }
        }
        /** @var array<string, string|bool|int|float|null> $labels */
        return $labels;
    }
}
