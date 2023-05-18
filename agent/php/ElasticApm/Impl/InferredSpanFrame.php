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

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use Elastic\Apm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InferredSpanFrame implements SpanToSendInterface, LoggableInterface
{
    use LoggableTrait;

    private const SPAN_TYPE = 'inferred';
    private const UNKNOWN_SPAN_NAME = 'unknown inferred span';

    /** @var float */
    public $timestamp;

    /** @var float Monotonic time since some unspecified starting point, in microseconds */
    private $monotonicBeginTime;

    /** @var ClassicFormatStackTraceFrame */
    public $stackFrame;

    /** @var ?float In milliseconds with 3 decimal points */
    public $duration = null;

    /** @var ?string */
    public $traceId = null;

    /** @var ?string */
    public $parentId = null;

    /** @var ?string */
    public $transactionId = null;

    /** @var ?string */
    public $id = null;

    /** @var ?string */
    public $name = null;

    /** @var ?string */
    public $type = null;

    /**
     * @var ?float
     *
     * @see ExecutionSegment::$sampleRate
     */
    public $sampleRate = null;

    /** @var null|StackTraceFrame[] */
    public $stackTrace = null;

    public function __construct(
        float $systemClockBeginTime,
        float $monotonicBeginTime,
        ClassicFormatStackTraceFrame $stackFrame
    ) {
        $this->timestamp = $systemClockBeginTime;
        $this->monotonicBeginTime = $monotonicBeginTime;
        $this->stackFrame = $stackFrame;
    }

    /**
     * @param ClassicFormatStackTraceFrame $stackFrame
     *
     * @return bool
     */
    public function canBeExtendedWith(ClassicFormatStackTraceFrame $stackFrame): bool
    {
        return $this->stackFrame->class === $stackFrame->class
               && $this->stackFrame->function === $stackFrame->function
               && $this->stackFrame->file === $stackFrame->file;
    }

    public function setEndTime(float $systemClockEndTime, float $monotonicEndTime, LoggerFactory $loggerFactory): void
    {
        $durationInMicroseconds = ExecutionSegment::calcDurationInMicroseconds(
            $this->timestamp /* <- systemClockBeginTime */,
            $this->monotonicBeginTime,
            $systemClockEndTime,
            $monotonicEndTime,
            $loggerFactory
        );
        $this->duration = TimeUtil::microsecondsToMilliseconds($durationInMicroseconds);
    }

    public function markAsAllocatedToBeSent(): string
    {
        if ($this->id === null) {
            $this->id = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        }
        return $this->id;
    }

    public function isAllocatedToBeSent(): bool
    {
        return $this->id !== null;
    }

    /**
     * @param Transaction       $transaction
     * @param string            $parentId
     * @param StackTraceFrame[] $stackTrace
     */
    public function prepareForSerialization(Transaction $transaction, string $parentId, array $stackTrace): void
    {
        $this->traceId = $transaction->getTraceId();
        $this->transactionId = $transaction->getId();
        $this->parentId = $parentId;

        $this->sampleRate = $transaction->sampleRate;

        $this->stackTrace = $stackTrace;
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        /**
         * ClassNameUtil::fqToShort requires class-string
         *
         * @phpstan-ignore-next-line
         */
        $shortClassName = $this->stackFrame->class === null ? null : ClassNameUtil::fqToShort($this->stackFrame->class);
        $this->name = StackTraceUtil::convertClassAndMethodToFunctionName(
            $shortClassName,
            $this->stackFrame->isStaticMethod,
            $this->stackFrame->function
        );
        if ($this->name === null) {
            if ($this->stackFrame->file !== null && $this->stackFrame->line !== null) {
                $this->name = $this->stackFrame->file . ':' . $this->stackFrame->line;
            } else {
                $this->name = self::UNKNOWN_SPAN_NAME;
            }
        }
        SerializationUtil::addNameValue('name', $this->name, /* ref */ $result);
        $this->type = self::SPAN_TYPE;
        SerializationUtil::addNameValue('type', $this->type, /* ref */ $result);

        SerializationUtil::addNameValueAssumeNotNull('id', $this->id, /* ref */ $result);
        SerializationUtil::addNameValueAssumeNotNull('trace_id', $this->traceId, /* ref */ $result);
        SerializationUtil::addNameValueAssumeNotNull('transaction_id', $this->transactionId, /* ref */ $result);
        SerializationUtil::addNameValueAssumeNotNull('parent_id', $this->parentId, /* ref */ $result);

        $timestamp = SerializationUtil::adaptTimestamp($this->timestamp);
        SerializationUtil::addNameValue('timestamp', $timestamp, /* ref */ $result);
        SerializationUtil::addNameValueAssumeNotNull('duration', $this->duration, /* ref */ $result);

        SerializationUtil::addNameValueIfNotNull('sample_rate', $this->sampleRate, /* ref */ $result);

        SerializationUtil::addNameValueAssumeNotNull('stacktrace', $this->stackTrace, /* ref */ $result);

        return $result;
    }
}
