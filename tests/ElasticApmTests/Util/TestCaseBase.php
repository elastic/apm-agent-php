<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use ElasticApm\Impl\Constants;
use ElasticApm\Report\ExecutionSegmentDtoInterface;
use ElasticApm\Report\SpanDtoInterface;
use ElasticApm\Report\TransactionDtoInterface;
use ElasticApm\TracerSingleton;
use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\TestCase;

class TestCaseBase extends TestCase
{
    // Adds the assertThrows method
    use AssertThrows;

    public function tearDown(): void
    {
        TracerSingleton::reset();
    }

    protected function assertValidId(string $id, int $expectedSizeInBytes): void
    {
        $this->assertSame($expectedSizeInBytes * 2, strlen($id));

        foreach (str_split($id) as $idChar) {
            $this->assertTrue(ctype_xdigit($idChar));
        }
    }

    protected function assertValidExecutionSegment(ExecutionSegmentDtoInterface $executionSegment): void
    {
        $this->assertValidId($executionSegment->getId(), Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->assertValidId($executionSegment->getTraceId(), Constants::TRACE_ID_SIZE_IN_BYTES);

        $this->assertNotNull($executionSegment->getType());
    }

    protected function assertValidTransaction(TransactionDtoInterface $transaction): void
    {
        $this->assertValidExecutionSegment($transaction);
    }

    protected function assertValidSpan(SpanDtoInterface $span): void
    {
        $this->assertValidExecutionSegment($span);

        $this->assertNotNull($span->getName());
    }

    /**
     * @param TransactionDtoInterface    $transaction
     * @param iterable<SpanDtoInterface> $spans
     */
    protected function assertValidTransactionAndItsSpans(TransactionDtoInterface $transaction, iterable $spans): void
    {
        $this->assertValidTransaction($transaction);

        foreach ($spans as $span) {
            $this->assertValidSpan($span);
            $this->assertSame($transaction->getId(), $span->getTransactionId());
            $this->assertSame($transaction->getTraceId(), $span->getTraceId());
        }
    }
}
