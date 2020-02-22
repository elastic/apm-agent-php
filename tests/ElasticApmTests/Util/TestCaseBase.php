<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use ElasticApm\ExecutionSegmentInterface;
use ElasticApm\Impl\TracerBuilder;
use ElasticApm\Impl\GlobalTracerHolder;
use ElasticApm\Impl\Util\IdGenerator;
use ElasticApm\Impl\Util\TimeUtil;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;
use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\TestCase;

class TestCaseBase extends TestCase
{
    // Adds the assertThrows method
    use AssertThrows;

    /** @var float Timestamp for February 20, 2020 18:04:47.987 */
    private const PAST_TIMESTAMP = 1582221887987;

    public function tearDown(): void
    {
        GlobalTracerHolder::reset();
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
        $this->assertValidId($executionSegment->getId(), IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->assertValidId($executionSegment->getTraceId(), IdGenerator::TRACE_ID_SIZE_IN_BYTES);

        $this->assertNotNull($executionSegment->getType());

        $this->assertGreaterThanOrEqual(0, $executionSegment->getDuration());

        $this->assertGreaterThan(self::PAST_TIMESTAMP, $executionSegment->getTimestamp());
    }

    public function assertValidTransaction(TransactionInterface $transaction): void
    {
        $this->assertValidExecutionSegment($transaction);
    }

    public function assertValidSpan(SpanInterface $span): void
    {
        $this->assertValidExecutionSegment($span);

        $this->assertNotNull($span->getName());

        $this->assertNotNull($span->getStart());
        $this->assertGreaterThanOrEqual(0, $span->getStart());
    }

    public function assertEqualTimestamps(float $expected, float $actual): void
    {
        $this->assertEqualsWithDelta($expected, $actual, 1);
    }

    /**
     * @param TransactionInterface    $transaction
     * @param iterable<SpanInterface> $spans
     */
    public function assertValidTransactionAndItsSpans(TransactionInterface $transaction, iterable $spans): void
    {
        $this->assertValidTransaction($transaction);

        /** @var SpanInterface $span */
        foreach ($spans as $span) {
            $this->assertValidSpan($span);
            $this->assertSame($transaction->getId(), $span->getTransactionId());
            $this->assertSame($transaction->getTraceId(), $span->getTraceId());

            $this->assertTrue($span->getStart() + $span->getDuration() <= $transaction->getDuration());

            $this->assertEqualTimestamps(
                $transaction->getTimestamp() + TimeUtil::millisecondsToMicroseconds((float)($span->getStart())),
                $span->getTimestamp()
            );
        }
    }

    protected function setUpElasticApmWithMockReporter(): MockReporter
    {
        $mockReporter = new MockReporter($this);
        GlobalTracerHolder::set(TracerBuilder::startNew()->withReporter($mockReporter)->build());
        return $mockReporter;
    }
}
