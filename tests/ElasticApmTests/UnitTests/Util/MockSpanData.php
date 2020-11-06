<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;

final class MockSpanData extends SpanData
{
    use MockExecutionSegmentDataTrait;

    /**
     * @param MockSpanData[]        $childSpans
     * @param MockTransactionData[] $childTransactions
     */
    public function __construct(array $childSpans = [], array $childTransactions = [])
    {
        $this->constructMockExecutionSegmentDataTrait($childSpans, $childTransactions);
    }

    public function syncWithTransaction(TransactionData $transactionData): void
    {
        $this->traceId = $transactionData->traceId;
        $this->setTransactionId($transactionData->id);
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
        foreach ($this->childSpans as $child) {
            $child->setTransactionId($transactionId);
        }
    }

    public function getTreeSpansCount(): int
    {
        $result = 1;
        foreach ($this->childSpans as $child) {
            $result += $child->getTreeSpansCount();
        }
        return $result;
    }
}
