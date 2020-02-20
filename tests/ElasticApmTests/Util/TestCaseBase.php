<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use ElasticApm\ExecutionSegmentInterface;
use ElasticApm\Impl\ExecutionSegment;
use ElasticApm\Impl\TracerSingleton;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;
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

    public function assertValidId(string $id, int $expectedSizeInBytes): void
    {
        $this->assertSame($expectedSizeInBytes * 2, strlen($id));

        foreach (str_split($id) as $idChar) {
            $this->assertTrue(ctype_xdigit($idChar));
        }
    }

    public function assertValidExecutionSegment(ExecutionSegmentInterface $executionSegment): void
    {
        $this->assertValidId($executionSegment->getId(), ExecutionSegment::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->assertValidId($executionSegment->getTraceId(), ExecutionSegment::TRACE_ID_SIZE_IN_BYTES);

        $this->assertNotNull($executionSegment->getType());
    }

    public function assertValidTransaction(TransactionInterface $transaction): void
    {
        $this->assertValidExecutionSegment($transaction);
    }

    public function assertValidSpan(SpanInterface $span): void
    {
        $this->assertValidExecutionSegment($span);

        $this->assertNotNull($span->getName());
    }

    /**
     * @param TransactionInterface    $transaction
     * @param iterable<SpanInterface> $spans
     */
    public function assertValidTransactionAndItsSpans(TransactionInterface $transaction, iterable $spans): void
    {
        $this->assertValidTransaction($transaction);

        foreach ($spans as $span) {
            $this->assertValidSpan($span);
            $this->assertSame($transaction->getId(), $span->getTransactionId());
            $this->assertSame($transaction->getTraceId(), $span->getTraceId());
        }
    }
}
