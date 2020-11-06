<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\TracerBuilder;
use ElasticApmTests\UnitTests\Util\MockClock;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class TimeRelatedApiUsingMockClockTest extends UnitTestCaseBase
{
    /** @var MockClock */
    protected $mockClock;

    public function setUp(): void
    {
        $this->mockClock = new MockClock();
        $this->setUpTestEnv(
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

        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame($expectedTimestamp, $reportedTx->timestamp);
        $this->assertSame($expectedDuration, $reportedTx->duration);
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
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame($expectedDuration, $reportedTx->duration);
    }

    public function testSpanBeginEnd(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->mockClock->fastForwardMilliseconds(112233.445);
        $expectedSpanTimestamp = $this->mockClock->getTimestamp();
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedSpanDuration);
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame($expectedSpanTimestamp, $reportedSpan->timestamp);
        $this->assertSame($expectedSpanDuration, $reportedSpan->duration);
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
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame($expectedSpanDuration, $reportedSpan->duration);
    }
}
