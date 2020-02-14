<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\NoopTransaction;
use ElasticApm\Report\NoopReporter;
use ElasticApm\TracerBuilder;
use ElasticApmTests\Util\MockReporter;
use ElasticApmTests\Util\NotFoundException;

class PublicApiTest extends Util\TestCaseBase
{
    public function testBeginEndTransaction(): void
    {
        // Arrange
        $mockReporter = new MockReporter();
        $this->assertFalse($mockReporter->isNoop());
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();
        $this->assertFalse($tracer->isNoop());

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertFalse($tx->isNoop());
        $tx->end();

        // Assert
        $this->assertSame(0, count($mockReporter->getSpans()));
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());
    }

    public function testBeginEndSpan(): void
    {
        // Arrange
        $mockReporter = new MockReporter();
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span_1 = $tx->beginChildSpan('test_span_1_name', 'test_span_1_type');
        // spans can overlap in any desired way
        $span_2 = $tx->beginChildSpan(
            'test_span_2_name',
            'test_span_2_type',
            'test_span_2_subtype',
            'test_span_2_action'
        );
        $span_2_1 = $span_2->beginChildSpan('test_span_2_1_name', 'test_span_2_1_type', 'test_span_2_1_subtype');
        $span_2_2 = $span_2->beginChildSpan(
            'test_span_2_2_name',
            'test_span_2_2_type',
            /* subtype: */ null,
            'test_span_2_2_action'
        );
        $span_1->end();
        $span_2_2->end();
        // parent span can end before its child spans
        $span_2->end();
        $span_2_1->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());

        $this->assertSame(4, count($mockReporter->getSpans()));

        $reportedSpan_1 = $mockReporter->getSpanByName('test_span_1_name');
        $this->assertSame('test_span_1_type', $reportedSpan_1->getType());
        $this->assertNull($reportedSpan_1->getSubtype());
        $this->assertNull($reportedSpan_1->getAction());

        $reportedSpan_2 = $mockReporter->getSpanByName('test_span_2_name');
        $this->assertSame('test_span_2_type', $reportedSpan_2->getType());
        $this->assertSame('test_span_2_subtype', $reportedSpan_2->getSubtype());
        $this->assertSame('test_span_2_action', $reportedSpan_2->getAction());

        $reportedSpan_2_1 = $mockReporter->getSpanByName('test_span_2_1_name');
        $this->assertSame('test_span_2_1_type', $reportedSpan_2_1->getType());
        $this->assertSame('test_span_2_1_subtype', $reportedSpan_2_1->getSubtype());
        $this->assertNull($reportedSpan_2_1->getAction());

        $reportedSpan_2_2 = $mockReporter->getSpanByName('test_span_2_2_name');
        $this->assertSame('test_span_2_2_type', $reportedSpan_2_2->getType());
        $this->assertNull($reportedSpan_2_2->getSubtype());
        $this->assertSame('test_span_2_2_action', $reportedSpan_2_2->getAction());

        $this->assertThrows(NotFoundException::class, function () use ($mockReporter) {
            $mockReporter->getSpanByName('nonexistent_test_span_name');
        });
    }

    public function testDisabledTracer(): void
    {
        // Arrange
        $mockReporter = new MockReporter();
        $tracer = TracerBuilder::startNew()->withEnabled(false)->withReporter($mockReporter)->build();
        $this->assertTrue($tracer->isNoop());

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertTrue($tx->isNoop());
        $tx->setId('1234567890ABCDEF');
        $this->assertSame(NoopTransaction::ID, $tx->getId());
        $tx->setTraceId('1234567890ABCDEF1234567890ABCDEF');
        $this->assertSame(NoopTransaction::TRACE_ID, $tx->getTraceId());
        $tx->setName('test_TX_name');
        $this->assertNull($tx->getName());
        $tx->setDuration(7654.321);
        $this->assertSame(0.0, $tx->getDuration());
        $tx->setTimestamp(1234567890);
        $this->assertSame(0, $tx->getTimestamp());
        $tx->setParentId('1234567890ABCDEF');
        $this->assertNull($tx->getParentId());
        $tx->end();

        // Assert
        $this->assertSame(0, count($mockReporter->getSpans()));
        $this->assertSame(0, count($mockReporter->getTransactions()));
    }

    public function testNoopReporter(): void
    {
        // Arrange
        $noopReporter = NoopReporter::create();
        $tracer = TracerBuilder::startNew()->withReporter($noopReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->end();

        // Assert
        $this->assertTrue($noopReporter->isNoop());
        $this->assertFalse($tracer->isNoop());
        $this->assertFalse($tx->isNoop());
    }
}
