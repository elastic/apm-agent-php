<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

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
        $this->setTraceId(IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES));
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
