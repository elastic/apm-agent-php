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

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use ElasticApmTests\Util\ExecutionSegmentDataValidator;
use ElasticApmTests\Util\FloatLimits;

trait MockExecutionSegmentDataTrait
{
    /** @var MockSpanData[] */
    protected $childSpans;

    /** @var MockTransactionData[] */
    protected $childTransactions;

    /**
     * @param MockSpanData[]        $childSpans
     * @param MockTransactionData[] $childTransactions
     */
    protected function constructMockExecutionSegmentDataTrait(array $childSpans, array $childTransactions): void
    {
        $this->childSpans = $childSpans;
        $this->childTransactions = $childTransactions;
        $prefix = 'dummy ' . ClassNameUtil::fqToShort(get_called_class()) . ' ';
        $this->id = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->name = $prefix . 'name';
        $this->type = $prefix . 'type';
        if (empty($this->childSpans) && empty($this->childTransactions)) {
            $this->setTimestamp(Clock::singletonInstance()->getSystemClockCurrentTime());
            $this->duration = mt_rand(0, 1);
        } else {
            $this->syncWithChildren();
        }
        $this->sampleRate = 1.0;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setTimestamp(float $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function setTraceId(string $traceId): void
    {
        $this->traceId = $traceId;
        foreach ($this->childSpans as $childSpan) {
            $childSpan->setTraceId($traceId);
        }
        foreach ($this->childTransactions as $childTransaction) {
            $childTransaction->setTraceId($traceId);
        }
    }

    private function syncWithChildren(): void
    {
        $minChildStartTimestamp = FloatLimits::MAX;
        $maxChildEndTimestamp = FloatLimits::MIN;

        foreach ($this->childSpans as $childSpan) {
            $childSpan->parentId = $this->id;
            $minChildStartTimestamp = min($minChildStartTimestamp, $childSpan->timestamp);
            $maxChildEndTimestamp
                = max($maxChildEndTimestamp, ExecutionSegmentDataValidator::calcEndTime($childSpan));
        }

        foreach ($this->childTransactions as $childTransaction) {
            $childTransaction->parentId = $this->id;
            $minChildStartTimestamp = min($minChildStartTimestamp, $childTransaction->timestamp);
            $maxChildEndTimestamp
                = max($maxChildEndTimestamp, ExecutionSegmentDataValidator::calcEndTime($childTransaction));
        }

        $this->setTimestamp($minChildStartTimestamp - mt_rand(0, 1));
        $this->duration = $maxChildEndTimestamp + mt_rand(0, 1) - $this->timestamp;
    }
}
