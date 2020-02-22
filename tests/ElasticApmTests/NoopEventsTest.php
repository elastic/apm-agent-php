<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\ElasticApm;
use ElasticApm\ExecutionSegmentInterface;
use ElasticApm\Impl\NoopExecutionSegment;
use ElasticApm\Impl\NoopReporter;
use ElasticApm\Impl\NoopSpan;
use ElasticApm\Impl\NoopTransaction;
use ElasticApm\Impl\TracerBuilder;
use ElasticApm\Impl\GlobalTracerHolder;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;
use ElasticApmTests\Util\MockReporter;

class NoopEventsTest extends Util\TestCaseBase
{
    public function testDisabledTracer(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withEnabled(false)->withReporter($mockReporter)->build();
        $this->assertTrue($tracer->isNoop());

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertNoopTransaction($tx);

        $span = $tx->beginChildSpan('test_span_name', 'test_span_type', 'test_span_subtype', 'test_span_action');
        $this->assertNoopSpan($span);

        $span->end();
        $tx->end();

        $counter = 123;
        $tracer->captureTransaction(
            null,
            'test_TX_type',
            function (TransactionInterface $transaction) use (&$counter): void {
                $this->assertNoopTransaction($transaction);
                $this->assertSame(123, $counter);
                ++$counter;
                $transaction->captureChildSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $span) use (&$counter): void {
                        $this->assertNoopSpan($span);
                        $this->assertSame(124, $counter);
                        ++$counter;
                    }
                );
            }
        );
        $this->assertSame(125, $counter);

        $tracer->captureCurrentTransaction(
            null,
            'test_TX_type',
            function (TransactionInterface $transaction) use (&$counter): void {
                $this->assertNoopTransaction($transaction);
                $this->assertSame(125, $counter);
                ++$counter;
                $transaction->captureCurrentSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $span) use (&$counter): void {
                        $this->assertNoopSpan($span);
                        $this->assertSame(126, $counter);
                        ++$counter;
                    }
                );
            }
        );
        $this->assertSame(127, $counter);

        // Assert
        $this->assertNoopTransaction($tx);
        $this->assertNoopSpan($span);
        $this->assertSame(0, count($mockReporter->getSpans()));
        $this->assertSame(0, count($mockReporter->getTransactions()));
    }
    public function testDisabledTracerUsingElasticApmFacade(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        GlobalTracerHolder::set(TracerBuilder::startNew()->withEnabled(false)->withReporter($mockReporter)->build());

        // Act
        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $this->assertNoopTransaction($tx);

        $span =
            ElasticApm::beginCurrentSpan('test_span_name', 'test_span_type', 'test_span_subtype', 'test_span_action');
        $this->assertNoopSpan($span);

        $span->end();
        $tx->end();

        $counter = 125;
        ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            function (TransactionInterface $transaction) use (&$counter): void {
                $this->assertNoopTransaction($transaction);
                $this->assertSame(125, $counter);
                ++$counter;
                ElasticApm::captureCurrentSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $span) use (&$counter): void {
                        $this->assertNoopSpan($span);
                        $this->assertSame(126, $counter);
                        ++$counter;
                    }
                );
            }
        );
        $this->assertSame(127, $counter);

        // Assert
        $this->assertNoopTransaction($tx);
        $this->assertNoopSpan($span);
        $this->assertSame(0, count($mockReporter->getSpans()));
        $this->assertSame(0, count($mockReporter->getTransactions()));
    }

    public function testNoopReporter(): void
    {
        // Arrange
        $noopReporter = NoopReporter::create();
        $tracer = TracerBuilder::startNew()->withReporter($noopReporter)->build();

        // Act & Assert
        $this->assertFalse($tracer->isNoop());
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertFalse($tx->isNoop());
        $tx->end();
        $this->assertFalse($tx->isNoop());
        $this->assertFalse($tracer->isNoop());
    }

    private function assertNoopExecutionSegment(ExecutionSegmentInterface $execSegment): void
    {
        $this->assertTrue($execSegment->isNoop());
        $this->assertSame(NoopExecutionSegment::ID, $execSegment->getId());
        $this->assertSame(NoopExecutionSegment::TRACE_ID, $execSegment->getTraceId());
        $this->assertSame(0.0, $execSegment->getDuration());
        $this->assertSame(0.0, $execSegment->getTimestamp());

        $this->assertSame(NoopExecutionSegment::TYPE, $execSegment->getType());
        $execSegment->setType('some_other_type');
        $this->assertSame(NoopExecutionSegment::TYPE, $execSegment->getType());
    }

    private function assertNoopTransaction(TransactionInterface $transaction): void
    {
        $this->assertNoopExecutionSegment($transaction);

        $this->assertNull($transaction->getParentId());

        $this->assertNull($transaction->getName());
        $transaction->setName('test_TX_name');
        $this->assertNull($transaction->getName());
        $transaction->setName(null);
        $this->assertNull($transaction->getName());
    }

    private function assertNoopSpan(SpanInterface $span): void
    {
        $this->assertNoopExecutionSegment($span);

        $this->assertSame(NoopTransaction::ID, $span->getParentId());

        $this->assertSame(NoopSpan::NAME, $span->getName());
        $span->setName('test_span_name');
        $this->assertSame(NoopSpan::NAME, $span->getName());

        $this->assertSame(0.0, $span->getStart());

        $this->assertNull($span->getSubtype());
        $span->setSubtype('test_span_subtype');
        $this->assertNull($span->getSubtype());

        $this->assertNull($span->getAction());
        $span->setAction('test_span_action');
        $this->assertNull($span->getAction());
    }
}
