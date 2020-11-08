<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use ElasticApmTests\Util\FloatLimits;
use ElasticApmTests\Util\TestCaseBase;

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
            $maxChildEndTimestamp = max($maxChildEndTimestamp, TestCaseBase::calcEndTime($childSpan));
        }

        foreach ($this->childTransactions as $childTransaction) {
            $childTransaction->parentId = $this->id;
            $minChildStartTimestamp = min($minChildStartTimestamp, $childTransaction->timestamp);
            $maxChildEndTimestamp = max($maxChildEndTimestamp, TestCaseBase::calcEndTime($childTransaction));
        }

        $this->setTimestamp($minChildStartTimestamp - mt_rand(0, 1));
        $this->duration = $maxChildEndTimestamp + mt_rand(0, 1) - $this->timestamp;
    }
}
