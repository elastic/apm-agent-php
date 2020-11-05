<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\UnitTests\Util\NotFoundException;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class PublicApiTest extends UnitTestCaseBase
{
    public function testBeginEndTransaction(): void
    {
        // Arrange
        $this->assertFalse($this->tracer->isNoop());

        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertFalse($tx->isNoop());
        $tx->end();

        // Assert
        $this->assertEmpty($this->mockEventSink->idToSpan());
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());
    }

    public function testBeginEndSpan(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
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
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());

        $this->assertCount(4, $this->mockEventSink->idToSpan());

        $reportedSpan_1 = $this->mockEventSink->spanByName('test_span_1_name');
        $this->assertSame('test_span_1_type', $reportedSpan_1->getType());
        $this->assertNull($reportedSpan_1->getSubtype());
        $this->assertNull($reportedSpan_1->getAction());

        $reportedSpan_2 = $this->mockEventSink->spanByName('test_span_2_name');
        $this->assertSame('test_span_2_type', $reportedSpan_2->getType());
        $this->assertSame('test_span_2_subtype', $reportedSpan_2->getSubtype());
        $this->assertSame('test_span_2_action', $reportedSpan_2->getAction());

        $reportedSpan_2_1 = $this->mockEventSink->spanByName('test_span_2_1_name');
        $this->assertSame('test_span_2_1_type', $reportedSpan_2_1->getType());
        $this->assertSame('test_span_2_1_subtype', $reportedSpan_2_1->getSubtype());
        $this->assertNull($reportedSpan_2_1->getAction());

        $reportedSpan_2_2 = $this->mockEventSink->spanByName('test_span_2_2_name');
        $this->assertSame('test_span_2_2_type', $reportedSpan_2_2->getType());
        $this->assertNull($reportedSpan_2_2->getSubtype());
        $this->assertSame('test_span_2_2_action', $reportedSpan_2_2->getAction());

        $this->assertThrows(
            NotFoundException::class,
            function () {
                $this->mockEventSink->spanByName('nonexistent_test_span_name');
            }
        );
    }

    public function testTransactionSetName(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name_1', 'test_TX_type');
        $tx->setName('test_TX_name_2');
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name_2', $reportedTx->getName());
    }

    public function testTransactionSetType(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $tx->setType('test_TX_type_2');
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_type_2', $reportedTx->getType());
    }

    public function testTransactionSetResult(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $this->assertSame(null, $tx->getResult());
        $tx->setResult('test_TX_result');
        $this->assertSame('test_TX_result', $tx->getResult());
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_result', $reportedTx->getResult());
    }

    public function testTransactionSetResultToNull(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $this->assertSame(null, $tx->getResult());
        $tx->setResult('test_TX_result');
        $this->assertSame('test_TX_result', $tx->getResult());
        $tx->setResult(null);
        $this->assertSame(null, $tx->getResult());
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame(null, $reportedTx->getResult());
    }

    public function testSpanSetName(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name_1', 'test_span_type');
        $span->setName('test_span_name_2');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_name_2', $reportedSpan->getName());
    }

    public function testSpanSetType(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type_1');
        $span->setType('test_span_type_2');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_type_2', $reportedSpan->getType());
    }

    public function testSpanSetSubtype(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setSubtype('test_span_subtype');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_subtype', $reportedSpan->getSubtype());
    }

    public function testSpanSetAction(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setAction('test_span_action');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_action', $reportedSpan->getAction());
    }

    public function testGeneratedIds(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->end();
        $tx->end();

        // Assert
        $this->assertTransactionEquals($tx, $this->mockEventSink->singleTransaction());
        $this->assertSpanEquals($span, $this->mockEventSink->singleSpan());

        $this->assertValidTransactionAndItsSpans($tx, $this->mockEventSink->idToSpan());
    }

    public function testVersionShouldNotBeEmpty(): void
    {
        $this->assertTrue(strlen(ElasticApm::VERSION) != 0);
    }
}
