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
use PHPUnit\Framework\TestCase;

abstract class ExecutionSegmentDto
{
    use AssertValidTrait;

    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var string */
    public $id;

    /** @var string */
    public $traceId;

    /** @var float */
    public $timestamp;

    /** @var float */
    public $duration;

    /** @var ?string */
    public $outcome = null;

    /** @var ?float */
    public $sampleRate = null;

    /**
     * @param mixed $key
     * @param mixed $value
     * @param self  $result
     *
     * @return bool
     */
    public static function deserializeKeyValue($key, $value, self $result): bool
    {
        switch ($key) {
            case 'duration':
                $result->duration = self::assertValidDuration($value);
                return true;
            case 'id':
                $result->id = ExecutionSegmentDto::assertValidId($value);
                return true;
            case 'name':
                $result->name = self::assertValidKeywordString($value);
                return true;
            case 'outcome':
                $result->outcome = ExecutionSegmentDto::assertValidOutcome($value);
                return true;
            case 'sample_rate':
                $result->sampleRate = ExecutionSegmentDto::assertValidNullableSampleRate($value);
                return true;
            case 'timestamp':
                $result->timestamp = self::assertValidTimestamp($value);
                return true;
            case 'trace_id':
                $result->traceId = TraceValidator::assertValidId($value);
                return true;
            case 'type':
                $result->type = self::assertValidKeywordString($value);
                return true;
            default:
                return false;
        }
    }

    protected function assertMatchesExecutionSegment(ExecutionSegmentExpectations $expectations): void
    {
        self::assertSameKeywordStringExpectedOptional($expectations->name, $this->name);
        self::assertSameKeywordStringExpectedOptional($expectations->type, $this->type);

        self::assertValidId($this->id);
        TraceValidator::assertValidId($this->traceId);

        self::assertValidTimestamp($this->timestamp, $expectations);
        self::assertValidDuration($this->duration);
        self::assertValidTimestamp(TestCaseBase::calcEndTime($this), $expectations);

        self::assertValidOutcome($this->outcome);
        self::assertValidNullableSampleRate($this->sampleRate);
    }

    /**
     * @param mixed $executionSegmentId
     *
     * @return string
     */
    public static function assertValidId($executionSegmentId): string
    {
        return self::assertValidIdEx($executionSegmentId, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
    }

    /**
     * @param mixed $outcome
     *
     * @return ?string
     */
    public static function assertValidOutcome($outcome): ?string
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
    public static function assertValidNullableSampleRate($value): ?float
    {
        if ($value === null) {
            return null;
        }
        TestCaseBase::assertIsNumber($value);
        /** @var int|float $value */
        TestCaseBase::assertInClosedRange(0, $value, 1);
        return floatval($value);
    }
}
