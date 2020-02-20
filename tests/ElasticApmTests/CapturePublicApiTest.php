<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\ElasticApm;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

class CapturePublicApiTest extends Util\TestCaseBase
{
    public function testElasticApmCurrentTransactionReturnVoid(): void
    {
        // Arrange
        $mockReporter = $this->setUpElasticApmWithMockReporter();

        // Act
        /** @var TransactionInterface $tx */
        $tx = null;
        $retVal = ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            function (TransactionInterface $transactionArg) use (&$tx) {
                $this->assertSame($transactionArg, ElasticApm::getCurrentTransaction());
                $tx = $transactionArg;
                $this->assertSame('test_TX_name', $tx->getName());
                $this->assertSame('test_TX_type', $tx->getType());
            }
        );

        // Assert
        $this->assertTrue(is_null($retVal));
        $this->assertNull($retVal);
        $this->assertNotNull($tx);
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $this->assertSame($tx, $mockReporter->getTransactions()[0]);
    }

    public function testElasticApmCurrentTransactionReturnObject(): void
    {
        // Arrange
        $mockReporter = $this->setUpElasticApmWithMockReporter();

        // Act
        /** @var TransactionInterface $tx */
        $tx = null;
        /** @var TestDummyObject $retVal */
        $retVal = ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            function (TransactionInterface $transactionArg) use (&$tx): TestDummyObject {
                $tx = $transactionArg;
                $this->assertSame('test_TX_name', $tx->getName());
                $this->assertSame('test_TX_type', $tx->getType());
                return new TestDummyObject('some dummy property value');
            }
        );

        // Assert
        $this->assertInstanceOf(TestDummyObject::class, $retVal);
        $this->assertSame('some dummy property value', $retVal->dummyPublicStringProperty);
        $this->assertNotNull($tx);
        $this->assertSame(1, count($mockReporter->getTransactions()));
        $this->assertSame($tx, $mockReporter->getTransactions()[0]);
    }

    public function testElasticApmCurrentSpanReturnVoid(): void
    {
        // Arrange
        $mockReporter = $this->setUpElasticApmWithMockReporter();

        // Act
        /** @var SpanInterface $span */
        $span = null;
        $retVal = ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            function () use (&$span) {
                return ElasticApm::captureCurrentSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $spanArg) use (&$span) {
                        $this->assertSame($spanArg, ElasticApm::getCurrentSpan());
                        $span = $spanArg;
                        $this->assertSame('test_span_name', $span->getName());
                        $this->assertSame('test_span_type', $span->getType());
                        $this->assertSame('test_span_subtype', $span->getSubtype());
                        $this->assertSame('test_span_action', $span->getAction());
                    },
                    'test_span_subtype',
                    'test_span_action'
                );
            }
        );

        // Assert
        $this->assertTrue(is_null($retVal));
        $this->assertNotNull($span);
        $this->assertSame(1, count($mockReporter->getSpans()));
        $this->assertSame($span, $mockReporter->getSpans()[0]);
    }

    public function testElasticApmCurrentSpanReturnObject(): void
    {
        // Arrange
        $mockReporter = $this->setUpElasticApmWithMockReporter();

        // Act
        /** @var SpanInterface $span */
        $span = null;
        /** @var TestDummyObject $retVal */
        $retVal = ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            function () use (&$span): TestDummyObject {
                return ElasticApm::captureCurrentSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $spanArg) use (&$span): TestDummyObject {
                        $span = $spanArg;
                        $this->assertSame('test_span_name', $span->getName());
                        $this->assertSame('test_span_type', $span->getType());
                        $this->assertSame('test_span_subtype', $span->getSubtype());
                        $this->assertSame('test_span_action', $span->getAction());
                        return new TestDummyObject('some dummy property value');
                    },
                    'test_span_subtype',
                    'test_span_action'
                );
            }
        );

        // Assert
        $this->assertInstanceOf(TestDummyObject::class, $retVal);
        $this->assertSame('some dummy property value', $retVal->dummyPublicStringProperty);
        $this->assertNotNull($span);
        $this->assertSame(1, count($mockReporter->getSpans()));
        $this->assertSame($span, $mockReporter->getSpans()[0]);
    }
}
