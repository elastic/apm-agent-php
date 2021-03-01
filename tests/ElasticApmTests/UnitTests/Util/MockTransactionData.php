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

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\IdGenerator;

final class MockTransactionData extends TransactionData
{
    use MockExecutionSegmentDataTrait;

    /**
     * @param MockSpanData[]        $childSpans
     * @param MockTransactionData[] $childTransactions
     */
    public function __construct(array $childSpans = [], array $childTransactions = [])
    {
        $this->constructMockExecutionSegmentDataTrait($childSpans, $childTransactions);
        $this->setTraceId(IdGenerator::generateId(Constants::TRACE_ID_SIZE_IN_BYTES));
        foreach ($this->childSpans as $child) {
            $child->syncWithTransaction($this);
            $this->startedSpansCount += $child->getTreeSpansCount();
        }
        $this->isSampled = true;
    }

    public function setTimestamp(float $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
