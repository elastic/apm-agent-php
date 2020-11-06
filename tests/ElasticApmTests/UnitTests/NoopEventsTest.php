<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\NoopExecutionSegment;
use Elastic\Apm\Impl\NoopTransaction;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class NoopEventsTest extends UnitTestCaseBase
{
    public function setUp(): void
    {
        $this->setUpTestEnv(
            function (TracerBuilder $builder): void {
                $builder->withEnabled(false);
            }
        );
    }

    public function testDisabledTracer(): void
    {
        // Arrange
        $this->assertTrue($this->tracer->isNoop());

        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertNoopTransaction($tx);

        $span = $tx->beginChildSpan('test_span_name', 'test_span_type', 'test_span_subtype', 'test_span_action');
        $this->assertNoopSpan($span);

        $span->end();
        $tx->end();

        $counter = 123;
        $this->tracer->captureTransaction(
            'test_TX_name',
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

        $this->tracer->captureCurrentTransaction(
            'test_TX_name',
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
        $this->assertEmpty($this->mockEventSink->idToSpan());
        $this->assertEmpty($this->mockEventSink->idToTransaction());
    }

    public function testDisabledTracerUsingElasticApmFacade(): void
    {
        // Act
        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $this->assertNoopTransaction($tx);

        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            'test_span_name',
            'test_span_type',
            'test_span_subtype',
            'test_span_action'
        );
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
                ElasticApm::getCurrentTransaction()->captureCurrentSpan(
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
        $this->assertEmpty($this->mockEventSink->idToSpan());
        $this->assertEmpty($this->mockEventSink->idToTransaction());
    }

    private function assertNoopExecutionSegment(ExecutionSegmentInterface $execSegment): void
    {
        $this->assertTrue($execSegment->isNoop());
        $execSegment->setType('some_other_type');
        $this->assertSame(NoopExecutionSegment::ID, $execSegment->getId());
        $this->assertSame(NoopExecutionSegment::TRACE_ID, $execSegment->getTraceId());
        $this->assertSame(0.0, $execSegment->getTimestamp());
    }

    private function assertNoopTransaction(TransactionInterface $transaction): void
    {
        $this->assertNoopExecutionSegment($transaction);
        $transaction->setName('test_TX_name');
        $this->assertNull($transaction->getParentId());
    }

    private function assertNoopSpan(SpanInterface $span): void
    {
        $this->assertNoopExecutionSegment($span);
        $span->setName('test_span_name');
        $span->setSubtype('test_span_subtype');
        $span->setAction('test_span_action');
        $this->assertSame(NoopTransaction::ID, $span->getParentId());
    }
}
