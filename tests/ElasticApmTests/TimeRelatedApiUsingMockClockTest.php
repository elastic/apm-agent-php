<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\Impl\TracerBuilder;
use ElasticApmTests\Util\MockClock;
use ElasticApmTests\Util\MockReporter;

class TimeRelatedApiUsingMockClockTest extends Util\TestCaseBase
{
    public function testTransactionBeginEnd(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $mockClock = new MockClock();
        $tracer = TracerBuilder::startNew()->withClock($mockClock)->withReporter($mockReporter)->build();

        // Act
        $mockClock->fastForward(987654321);
        $expectedTimestamp = $mockClock->getTimestamp();
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedDuration = 12345.678;
        $mockClock->fastForwardDuration($expectedDuration);
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame($expectedTimestamp, $reportedTx->getTimestamp());
        $this->assertSame($expectedDuration, $reportedTx->getDuration());
    }

    public function testTransactionBeginEndWithDuration(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $mockClock = new MockClock();
        $tracer = TracerBuilder::startNew()->withClock($mockClock)->withReporter($mockReporter)->build();

        // Act
        $mockClock->fastForward(987654321);
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedDuration = 12345.678;
        $mockClock->fastForwardDuration($expectedDuration + 123456789);
        $tx->end($expectedDuration);

        // Assert
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame($expectedDuration, $reportedTx->getDuration());
    }

    public function testSpanBeginEnd(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $mockClock = new MockClock();
        $tracer = TracerBuilder::startNew()->withClock($mockClock)->withReporter($mockReporter)->build();

        // Act
        $mockClock->fastForward(987654321);
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedTxBeginToSpanBeginDuration = 112233.445;
        $mockClock->fastForwardDuration($expectedTxBeginToSpanBeginDuration);
        $expectedSpanTimestamp = $mockClock->getTimestamp();
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $mockClock->fastForwardDuration($expectedSpanDuration);
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getSpans()));
        $reportedSpan = $mockReporter->getSpans()[0];
        $this->assertSame($expectedSpanTimestamp, $reportedSpan->getTimestamp());
        $this->assertSame($expectedTxBeginToSpanBeginDuration, $reportedSpan->getStart());
        $this->assertSame($expectedSpanDuration, $reportedSpan->getDuration());
    }

    public function testSpanBeginEndWithDuration(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $mockClock = new MockClock();
        $tracer = TracerBuilder::startNew()->withClock($mockClock)->withReporter($mockReporter)->build();

        // Act
        $mockClock->fastForward(987654321);
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $mockClock->fastForward(987654321);
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $mockClock->fastForwardDuration($expectedSpanDuration + 123456789);
        $span->end($expectedSpanDuration);
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getSpans()));
        $reportedSpan = $mockReporter->getSpans()[0];
        $this->assertSame($expectedSpanDuration, $reportedSpan->getDuration());
    }
}
