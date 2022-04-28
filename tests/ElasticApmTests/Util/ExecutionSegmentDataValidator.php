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
use Elastic\Apm\Impl\ExecutionSegment;
use Elastic\Apm\Impl\ExecutionSegmentContext;
use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Util\TimeUtil;
use PHPUnit\Framework\TestCase;

abstract class ExecutionSegmentDataValidator extends DataValidatorBase
{
    /** @var ExecutionSegmentDataExpectations */
    protected $expectations;

    /** @var ExecutionSegmentData */
    protected $actual;

    protected function __construct(ExecutionSegmentDataExpectations $expectations, ExecutionSegmentData $actual)
    {
        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        self::validateKeywordString($this->actual->name);
        self::validateKeywordString($this->actual->type);
        self::validateId($this->actual->id);
        self::validateTraceId($this->actual->traceId);

        self::validateTimestampInsideEvent($this->actual->timestamp, $this->expectations);
        self::validateDuration($this->actual->duration);
        self::validateTimestampInsideEvent(TestCaseBase::calcEndTime($this->actual), $this->expectations);

        self::validateOutcome($this->actual->outcome);
        self::validateNullableSampleRate($this->actual->sampleRate);
    }

    /**
     * @param mixed $executionSegmentId
     *
     * @return string
     */
    public static function validateId($executionSegmentId): string
    {
        return self::validateIdEx($executionSegmentId, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $outcome
     *
     * @return ?string
     */
    public static function validateOutcome($outcome): ?string
    {
        TestCase::assertTrue($outcome === null || is_string($outcome));
        /** @var ?string $outcome */
        TestCase::assertTrue(ExecutionSegment::isValidOutcome($outcome));
        return $outcome;
    }

    /**
     * @param mixed $value
     *
     * @return ?float
     */
    public static function validateNullableSampleRate($value): ?float
    {
        if ($value === null) {
            return null;
        }
        TestCaseBase::assertIsNumber($value);
        /** @var int|float $value */
        TestCaseBase::assertInClosedRange(0, $value, 1);
        return floatval($value);
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

    public static function validateExecutionSegmentContextData(ExecutionSegmentContextData $ctxData): void
    {
        self::validateLabels($ctxData->labels);
    }

    public static function assertTimeNested(
        ExecutionSegmentData $nestedExecSeg,
        ExecutionSegmentData $outerExecSeg
    ): void {
        $outerBeginTimestamp = $outerExecSeg->timestamp;
        $outerEndTimestamp = TestCaseBase::calcEndTime($outerExecSeg);
        TestCaseBase::assertTimestampInRange($outerBeginTimestamp, $nestedExecSeg->timestamp, $outerEndTimestamp);
        $nestedEndTimestamp = TestCaseBase::calcEndTime($nestedExecSeg);
        TestCaseBase::assertTimestampInRange($outerBeginTimestamp, $nestedEndTimestamp, $outerEndTimestamp);
    }
}
