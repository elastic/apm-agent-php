<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Tests\Util\TestCaseBase;

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
        $prefix = 'dummy ' . DbgUtil::fqToShortClassName(get_called_class()) . ' ';
        $this->id = IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->name = $prefix . 'name';
        $this->type = $prefix . 'type';
        if (empty($this->childSpans) && empty($this->childTransactions)) {
            $this->setTimestamp(Clock::singletonInstance()->getSystemClockCurrentTime());
            $this->setDuration(mt_rand(0, 1));
        } else {
            $this->syncWithChildren();
        }
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
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
        $minChildStartTimestamp = PHP_FLOAT_MAX;
        $maxChildEndTimestamp = PHP_FLOAT_MIN;

        foreach ($this->childSpans as $childSpan) {
            $childSpan->setParentId($this->getId());
            $minChildStartTimestamp = min($minChildStartTimestamp, $childSpan->getTimestamp());
            $maxChildEndTimestamp = max($maxChildEndTimestamp, TestCaseBase::calcEndTime($childSpan));
        }

        foreach ($this->childTransactions as $childTransaction) {
            $childTransaction->setParentId($this->getId());
            $minChildStartTimestamp = min($minChildStartTimestamp, $childTransaction->getTimestamp());
            $maxChildEndTimestamp = max($maxChildEndTimestamp, TestCaseBase::calcEndTime($childTransaction));
        }

        $this->setTimestamp($minChildStartTimestamp - mt_rand(0, 1));
        $this->setDuration($maxChildEndTimestamp + mt_rand(0, 1) - $this->timestamp);
    }
}
