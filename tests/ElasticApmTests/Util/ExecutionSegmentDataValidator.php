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

abstract class ExecutionSegmentDataValidator extends EventDataValidator
{
    /** @var ExecutionSegmentDataExpected */
    protected $expected;

    /** @var ExecutionSegmentData */
    protected $actual;

    protected function __construct(ExecutionSegmentDataExpected $expected, ExecutionSegmentData $actual)
    {
        $this->expected = $expected;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        self::validateKeywordString($this->actual->name);
        self::validateKeywordString($this->actual->type);
        self::validateId($this->actual->id);
        self::validateTraceId($this->actual->traceId);

        self::validateTimestamp($this->actual->timestamp, $this->expected);
        self::validateDuration($this->actual->duration);
        self::validateTimestamp(self::calcEndTime($this->actual), $this->expected);

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
        self::assertTrue($outcome === null || is_string($outcome));
        /** @var ?string $outcome */
        self::assertTrue(ExecutionSegment::isValidOutcome($outcome));
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
        self::assertIsNumber($value);
        /** @var int|float $value */
        self::assertInClosedRange(0, $value, 1);
        return floatval($value);
    }

    /**
     * @param mixed $labels
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function validateLabels($labels): array
    {
        self::assertTrue(is_array($labels));
        /** @var array<mixed, mixed> $labels */
        foreach ($labels as $key => $value) {
            self::validateKeywordString($key);
            self::assertTrue(ExecutionSegmentContext::doesValueHaveSupportedLabelType($value));
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

    public static function calcEndTime(ExecutionSegmentData $timedData): float
    {
        return $timedData->timestamp + TimeUtil::millisecondsToMicroseconds($timedData->duration);
    }

    public static function assertNested(ExecutionSegmentData $nestedExecSeg, ExecutionSegmentData $outerExecSeg): void
    {
        $outerBeginTimestamp = $outerExecSeg->timestamp;
        $outerEndTimestamp = self::calcEndTime($outerExecSeg);
        self::assertTimestampInRange($outerBeginTimestamp, $nestedExecSeg->timestamp, $outerEndTimestamp);
        self::assertTimestampInRange($outerBeginTimestamp, self::calcEndTime($nestedExecSeg), $outerEndTimestamp);
    }
}
