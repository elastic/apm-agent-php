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
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdValidationUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use PHPUnit\Framework\TestCase;

trait AssertValidTrait
{
    /**
     * @param mixed $id
     * @param int   $expectedSizeInBytes
     *
     * @return string
     */
    protected static function assertValidIdEx($id, int $expectedSizeInBytes): string
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
    public static function assertValidString($stringValue, bool $isNullable, ?int $maxLength = null): ?string
    {
        if ($stringValue === null) {
            TestCase::assertTrue($isNullable);
            return null;
        }

        TestCase::assertIsString($stringValue);
        /** @var string $stringValue */

        if ($maxLength !== null) {
            TestCase::assertLessThanOrEqual($maxLength, strlen($stringValue));
        }
        return $stringValue;
    }

    /**
     * @param mixed $keywordString
     *
     * @return string
     */
    public static function assertValidNullableKeywordString($keywordString): ?string
    {
        return self::assertValidString($keywordString, /* isNullable: */ true, Constants::KEYWORD_STRING_MAX_LENGTH);
    }

    /**
     * @param mixed $keywordString
     *
     * @return string
     */
    public static function assertValidKeywordString($keywordString): string
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $value = self::assertValidString($keywordString, /* isNullable: */ false, Constants::KEYWORD_STRING_MAX_LENGTH);
        /** @var string $value */
        return $value;
    }

    /**
     * @param Optional<string> $expected
     * @param string           $actual
     */
    public static function assertSameKeywordStringExpectedOptional(Optional $expected, string $actual): void
    {
        self::assertValidKeywordString($actual);
        TestCaseBase::assertSameExpectedOptional($expected, $actual);
    }

    /**
     * @param Optional<?string> $expected
     * @param ?string           $actual
     */
    public static function assertSameNullableKeywordStringExpectedOptional(Optional $expected, ?string $actual): void
    {
        self::assertValidNullableKeywordString($actual);
        TestCaseBase::assertSameExpectedOptional($expected, $actual);
    }

    /**
     * @param Optional<?string> $expected
     * @param ?string           $actual
     */
    public static function assertSameNullableNonKeywordStringExpectedOptional(Optional $expected, ?string $actual): void
    {
        self::assertValidNullableNonKeywordString($actual);
        TestCaseBase::assertSameExpectedOptional($expected, $actual);
    }

    /**
     * @param mixed $nonKeywordString
     *
     * @return string
     */
    public static function assertValidNullableNonKeywordString($nonKeywordString): ?string
    {
        return self::assertValidString($nonKeywordString, /* isNullable: */ true);
    }

    /**
     * @param mixed              $timestamp
     * @param ?EventExpectations $expectationsArg
     *
     * @return float
     */
    public static function assertValidTimestamp($timestamp, ?EventExpectations $expectationsArg = null): float
    {
        TestCaseBase::assertIsNumber($timestamp);
        /** @var float|int $timestamp */
        $expectations = $expectationsArg ?? new EventExpectations();
        TestCaseBase::assertTimestampInRange($expectations->timestampBefore, $timestamp, $expectations->timestampAfter);
        return floatval($timestamp);
    }

    /**
     * @param mixed $duration
     *
     * @return float
     */
    public static function assertValidDuration($duration): float
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
    public static function assertValidStacktraceFrameFilename($filename): string
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
    public static function assertValidStacktraceFrameLineNumber($lineNumber): int
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
    public static function assertValidStacktraceFrameFunction($function): ?string
    {
        if ($function !== null) {
            TestCase::assertIsString($function);
            /** @var string $function */
            TestCase::assertTrue(!TextUtil::isEmptyString($function));
        }

        return $function;
    }

    public static function assertValidStacktraceFrame(StackTraceFrame $stacktraceFrame): void
    {
        self::assertValidStacktraceFrameFilename($stacktraceFrame->filename);
        self::assertValidStacktraceFrameLineNumber($stacktraceFrame->lineno);
        self::assertValidStacktraceFrameFunction($stacktraceFrame->function);
    }

    /**
     * @param StackTraceFrame[] $stacktrace
     */
    public static function assertValidStacktrace(array $stacktrace): void
    {
        foreach ($stacktrace as $stacktraceFrame) {
            self::assertValidStacktraceFrame($stacktraceFrame);
        }
    }

    /**
     * @param mixed $value
     *
     * @return int|null
     */
    public static function assertValidNullableHttpStatusCode($value): ?int
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
    public static function assertValidBool($value): bool
    {
        TestCase::assertIsBool($value);
        /** @var bool $value */
        return $value;
    }

    public static function assertTimeNested(ExecutionSegmentDto $nestedExecSeg, ExecutionSegmentDto $outerExecSeg): void
    {
        $outerBeginTimestamp = $outerExecSeg->timestamp;
        $outerEndTimestamp = TestCaseBase::calcEndTime($outerExecSeg);
        TestCaseBase::assertTimestampInRange($outerBeginTimestamp, $nestedExecSeg->timestamp, $outerEndTimestamp);
        $nestedEndTimestamp = TestCaseBase::calcEndTime($nestedExecSeg);
        TestCaseBase::assertTimestampInRange($outerBeginTimestamp, $nestedEndTimestamp, $outerEndTimestamp);
    }

    /**
     * @param mixed $original
     * @param mixed $dto
     */
    public static function assertEqualOriginalAndDto($original, $dto, string $dbgPath = ''): void
    {
        if (is_object($dto)) {
            TestCase::assertIsObject($original);
            $originalPropNameToVal = get_object_vars($original);
            foreach (get_object_vars($dto) as $dtoPropName => $dtoPropVal) {
                $ctx = LoggableToString::convert(
                    [
                        'dtoPropName'           => $dtoPropName,
                        'originalPropNameToVal' => $originalPropNameToVal,
                        'original'              => $original,
                        'original type'         => DbgUtil::getType($original),
                        'dto'                   => $dto,
                        'dto type'              => DbgUtil::getType($dto),
                        'dbgPath'               => $dbgPath,
                    ]
                );
                if ($dtoPropVal !== null) {
                    TestCase::assertArrayHasKey($dtoPropName, $originalPropNameToVal, $ctx);
                }
                if (array_key_exists($dtoPropName, $originalPropNameToVal)) {
                    self::assertEqualOriginalAndDto(
                        $originalPropNameToVal[$dtoPropName],
                        $dtoPropVal,
                        ($dbgPath === '' ? DbgUtil::getType($original) : $dbgPath) . '->' . $dtoPropName /* dbgPath */
                    );
                }
            }
            return;
        }

        if (is_array($dto)) {
            TestCase::assertIsArray($original);
            TestCase::assertSame(count($original), count($dto));
            foreach ($dto as $dtoArrayKey => $dtoArrayVal) {
                TestCase::assertArrayHasKey($dtoArrayKey, $original);
                self::assertEqualOriginalAndDto(
                    $original[$dtoArrayKey],
                    $dtoArrayVal,
                    ($dbgPath === '' ? DbgUtil::getType($original) : $dbgPath) . '[' . $dtoArrayKey . ']' /* dbgPath */
                );
            }
            return;
        }

        TestCase::assertSame($original, $dto);
    }
}
