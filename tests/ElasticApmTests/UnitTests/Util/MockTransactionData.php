<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

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
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function setTimestamp(float $timestamp): void
    {
        $this->timestamp = $timestamp;
        foreach ($this->childSpans as $child) {
            $child->deriveStartOffsetFrom($this);
        }
    }
}
