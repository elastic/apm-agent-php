<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Tests\Util\TransactionTestDto;

final class MockTransaction extends TransactionTestDto
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
        $this->setTraceId(IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES));
        foreach ($this->childSpans as $child) {
            $child->syncWithTransaction($this);
            $this->setStartedSpansCount($this->getStartedSpansCount() + $child->getTreeSpansCount());
        }
    }

    public function setTimestamp(float $timestamp): void
    {
        parent::setTimestamp($timestamp);
        foreach ($this->childSpans as $child) {
            $child->deriveStartOffsetFrom($this);
        }
    }
}
