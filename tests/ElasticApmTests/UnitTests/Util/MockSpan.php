<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\Tests\Util\SpanTestDto;
use Elastic\Apm\TransactionInterface;

final class MockSpan extends SpanTestDto
{
    use MockExecutionSegmentTrait;

    /**
     * @param MockSpan[]        $childSpans
     * @param MockTransaction[] $childTransactions
     */
    public function __construct(array $childSpans = [], array $childTransactions = [])
    {
        parent::__construct();
        $this->constructMockExecutionSegmentTrait($childSpans, $childTransactions);
    }

    public function syncWithTransaction(TransactionInterface $transaction): void
    {
        $this->setTraceId($transaction->getTraceId());
        $this->setTransactionId($transaction->getId());
        $this->deriveStartOffsetFrom($transaction);
    }

    public function setTransactionId(string $transactionId): void
    {
        parent::setTransactionId($transactionId);
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

    public function deriveStartOffsetFrom(TransactionInterface $transaction): void
    {
        $this->setStart(TimeUtil::microsecondsToMilliseconds($this->getTimestamp() - $transaction->getTimestamp()));
        foreach ($this->childSpans as $child) {
            $child->deriveStartOffsetFrom($transaction);
        }
    }
}
