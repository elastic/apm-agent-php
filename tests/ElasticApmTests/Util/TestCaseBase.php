<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Closure;
use Elastic\Apm\ExecutionSegmentDataInterface;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Impl\TracerInterface;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;
use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase;

class TestCaseBase extends TestCase
{
    // Adds the assertThrows method
    use AssertThrows;

    /** @var MockEventSink */
    protected $mockEventSink;

    /** @var TracerInterface */
    protected $tracer;

    public function setUp(): void
    {
        $this->setUpTestEnv();
    }

    protected function setUpTestEnv(?Closure $tracerBuildCallback = null, bool $shouldCreateMockEventSink = true): void
    {
        $builder = TracerBuilder::startNew();
        if ($shouldCreateMockEventSink) {
            $this->mockEventSink = new MockEventSink();
            $builder->withEventSink($this->mockEventSink);
        }
        if (!is_null($tracerBuildCallback)) {
            $tracerBuildCallback($builder);
        }
        $this->tracer = $builder->build();
        GlobalTracerHolder::set($this->tracer);
    }

    public function tearDown(): void
    {
        GlobalTracerHolder::reset();
    }

    public function assertEqualTimestamp(float $expected, float $actual): void
    {
        $this->assertEqualsWithDelta($expected, $actual, 1);
    }

    public function assertLessThanOrEqualTimestamp(float $lhs, float $rhs): void
    {
        self::assertThat($lhs, self::logicalOr(new IsEqual($rhs, /* delta: */ 1), new LessThan($rhs)), '');
    }

    public function assertLessThanOrEqualDuration(float $lhs, float $rhs): void
    {
        self::assertThat($lhs, self::logicalOr(new IsEqual($rhs, /* delta: */ 1), new LessThan($rhs)), '');
    }

    public static function getEndTimestamp(ExecutionSegmentDataInterface $timedEvent): float
    {
        return $timedEvent->getTimestamp() + TimeUtil::millisecondsToMicroseconds($timedEvent->getDuration());
    }

    /**
     * @param mixed $timestamp
     * @param mixed $outerTimedEvent
     */
    public function assertTimestampNested($timestamp, $outerTimedEvent): void
    {
        $this->assertLessThanOrEqualTimestamp($outerTimedEvent->getTimestamp(), $timestamp);
        $this->assertLessThanOrEqualTimestamp($timestamp, self::getEndTimestamp($outerTimedEvent));
    }

    /**
     * @param mixed $nestedTimedEvent
     * @param mixed $outerTimedEvent
     */
    public function assertTimedEventIsNested($nestedTimedEvent, $outerTimedEvent): void
    {
        $this->assertTimestampNested($nestedTimedEvent->getTimestamp(), $outerTimedEvent);
        $this->assertTimestampNested(self::getEndTimestamp($nestedTimedEvent), $outerTimedEvent);
    }

    /**
     * @param TransactionDataInterface    $transaction
     * @param iterable<SpanDataInterface> $spans
     */
    public function assertValidTransactionAndItsSpans(TransactionDataInterface $transaction, iterable $spans): void
    {
        ValidationUtil::assertValidTransactionData($transaction);

        /** @var SpanDataInterface $span */
        foreach ($spans as $span) {
            ValidationUtil::assertValidSpanData($span);
            $this->assertSame($transaction->getId(), $span->getTransactionId());
            $this->assertSame($transaction->getTraceId(), $span->getTraceId());

            $this->assertTimedEventIsNested($span, $transaction);

            $this->assertLessThanOrEqualDuration($span->getStart() + $span->getDuration(), $transaction->getDuration());
            $this->assertEqualTimestamp(
                $transaction->getTimestamp() + TimeUtil::millisecondsToMicroseconds((float)($span->getStart())),
                $span->getTimestamp()
            );
        }
    }

    public static function assertTransactionEquals(
        TransactionDataInterface $expected,
        TransactionDataInterface $actual
    ): void {
        self::assertEquals(TransactionData::convertToData($expected), TransactionData::convertToData($actual));
    }

    public static function assertSpanEquals(
        SpanDataInterface $expected,
        SpanDataInterface $actual
    ): void {
        self::assertEquals(SpanData::convertToData($expected), SpanData::convertToData($actual));
    }
}
