<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests;

use Elastic\Apm\Impl\ClockInterface;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Tests\Util\MockClock;
use Elastic\Apm\Tests\Util\MockEventSink;

class TimeRelatedApiUsingMockClockTest extends Util\TestCaseBase
{
    /** @var MockClock */
    protected $mockClock;

    public function __construct()
    {
        $this->mockClock = new MockClock();
        parent::__construct(
            function (TracerBuilder $builder): void {
                $builder->withClock($this->mockClock);
            }
        );
    }

    public function testTransactionBeginEnd(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $expectedTimestamp = $this->mockClock->getTimestamp();
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedDuration);
        $tx->end();

        // Assert
        $this->assertSame(1, count($this->mockEventSink->getTransactions()));
        $reportedTx = $this->mockEventSink->getTransactions()[0];
        $this->assertSame($expectedTimestamp, $reportedTx->getTimestamp());
        $this->assertSame($expectedDuration, $reportedTx->getDuration());
    }

    public function testTransactionBeginEndWithDuration(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedDuration + 123456789);
        $tx->end($expectedDuration);

        // Assert
        $this->assertSame(1, count($this->mockEventSink->getTransactions()));
        $reportedTx = $this->mockEventSink->getTransactions()[0];
        $this->assertSame($expectedDuration, $reportedTx->getDuration());
    }

    public function testSpanBeginEnd(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedTxBeginToSpanBeginDuration = 112233.445;
        $this->mockClock->fastForwardMilliseconds($expectedTxBeginToSpanBeginDuration);
        $expectedSpanTimestamp = $this->mockClock->getTimestamp();
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedSpanDuration);
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($this->mockEventSink->getSpans()));
        $reportedSpan = $this->mockEventSink->getSpans()[0];
        $this->assertSame($expectedSpanTimestamp, $reportedSpan->getTimestamp());
        $this->assertSame($expectedTxBeginToSpanBeginDuration, $reportedSpan->getStart());
        $this->assertSame($expectedSpanDuration, $reportedSpan->getDuration());
    }

    public function testSpanBeginEndWithDuration(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->mockClock->fastForwardMicroseconds(987654321);
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedSpanDuration + 123456789);
        $span->end($expectedSpanDuration);
        $tx->end();

        // Assert
        $this->assertSame(1, count($this->mockEventSink->getSpans()));
        $reportedSpan = $this->mockEventSink->getSpans()[0];
        $this->assertSame($expectedSpanDuration, $reportedSpan->getDuration());
    }
}
