<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\TimeUtil;

final class MockSpanData extends SpanData
{
    use MockExecutionSegmentDataTrait;

    /**
     * @param MockSpanData[] $childSpans
     * @param MockTransactionData[] $childTransactions
     */
    public function __construct(array $childSpans = [], array $childTransactions = [])
    {
        $this->constructMockExecutionSegmentDataTrait($childSpans, $childTransactions);
    }

    public function syncWithTransaction(TransactionData $transaction): void
    {
        $this->setTraceId($transaction->getTraceId());
        $this->setTransactionId($transaction->getId());
        $this->deriveStartOffsetFrom($transaction);
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
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

    public function setStart(float $offsetFromTransactionStart): void
    {
        $this->start = $offsetFromTransactionStart;
    }

    public function deriveStartOffsetFrom(TransactionData $transaction): void
    {
        $this->start = TimeUtil::microsecondsToMilliseconds($this->getTimestamp() - $transaction->getTimestamp());
        foreach ($this->childSpans as $child) {
            $child->deriveStartOffsetFrom($transaction);
        }
    }
}
