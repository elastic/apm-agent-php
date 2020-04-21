<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Tests\Util\MockReporter;
use Elastic\Apm\Tests\Util\NotFoundException;

class PublicApiTest extends Util\TestCaseBase
{
    public function testBeginEndTransaction(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
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
        $mockReporter = new MockReporter($this);
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

        $this->assertThrows(
            NotFoundException::class,
            function () use ($mockReporter) {
                $mockReporter->getSpanByName('nonexistent_test_span_name');
            }
        );
    }

    public function testTransactionSetName(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name_1', 'test_TX_type');
        $tx->setName('test_TX_name_2');
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame('test_TX_name_2', $reportedTx->getName());
    }

    public function testTransactionSetType(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $tx->setType('test_TX_type_2');
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame('test_TX_type_2', $reportedTx->getType());
    }

    public function testSpanSetName(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name_1', 'test_span_type');
        $span->setName('test_span_name_2');
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getSpans()));
        $reportedSpan = $mockReporter->getSpans()[0];
        $this->assertSame('test_span_name_2', $reportedSpan->getName());
    }

    public function testSpanSetType(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type_1');
        $span->setType('test_span_type_2');
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getSpans()));
        $reportedSpan = $mockReporter->getSpans()[0];
        $this->assertSame('test_span_type_2', $reportedSpan->getType());
    }

    public function testSpanSetSubtype(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setSubtype('test_span_subtype');
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getSpans()));
        $reportedSpan = $mockReporter->getSpans()[0];
        $this->assertSame('test_span_subtype', $reportedSpan->getSubtype());
    }

    public function testSpanSetAction(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setAction('test_span_action');
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getSpans()));
        $reportedSpan = $mockReporter->getSpans()[0];
        $this->assertSame('test_span_action', $reportedSpan->getAction());
    }

    public function testGeneratedIds(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->end();
        $tx->end();

        // Assert
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $this->assertSame($tx, $mockReporter->getTransactions()[0]);
        $this->assertSame(1, count($mockReporter->getSpans()));
        $this->assertSame($span, $mockReporter->getSpans()[0]);

        $this->assertValidTransaction($tx);
        $this->assertValidSpan($span);

        $this->assertValidTransactionAndItsSpans($tx, $mockReporter->getSpans());
    }

    public function testVersionShouldNotBeEmpty(): void
    {
        $this->assertTrue(strlen(ElasticApm::VERSION) != 0);
    }
}
