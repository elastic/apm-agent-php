<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use Elastic\Apm\Tests\Util\TestDummyException;

class CapturePublicApiTest extends Util\TestCaseBase
{
    public function testElasticApmCurrentTransactionReturnVoid(): void
    {
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
        $this->assertSame(1, count($this->mockEventSink->getTransactions()));
        $this->assertTransactionEquals($tx, $this->mockEventSink->getTransactions()[0]);
    }

    public function testElasticApmCurrentTransactionReturnObject(): void
    {
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
        $this->assertSame(1, count($this->mockEventSink->getTransactions()));
        $this->assertTransactionEquals($tx, $this->mockEventSink->getTransactions()[0]);
    }

    public function testElasticApmCurrentSpanReturnVoid(): void
    {
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
        $this->assertSame(1, count($this->mockEventSink->getSpans()));
        $this->assertSpanEquals($span, $this->mockEventSink->getSpans()[0]);
    }

    public function testElasticApmCurrentSpanReturnObject(): void
    {
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
        $this->assertSame(1, count($this->mockEventSink->getSpans()));
        $this->assertSpanEquals($span, $this->mockEventSink->getSpans()[0]);
    }

    public function testWhenExceptionThrown(): void
    {
        $throwingFunc = function (bool $shouldThrow): bool {
            if ($shouldThrow) {
                throw new TestDummyException("A message");
            }
            return $shouldThrow;
        };

        $captureTx = function (bool $shouldThrow) use ($throwingFunc): bool {
            return ElasticApm::captureCurrentTransaction(
                'test_TX_name',
                'test_TX_type',
                function () use ($throwingFunc, $shouldThrow): bool {
                    return ElasticApm::captureCurrentSpan(
                        'test_span_name',
                        'test_span_type',
                        function () use ($throwingFunc, $shouldThrow): bool {
                            return $throwingFunc($shouldThrow);
                        }
                    );
                }
            );
        };

        // Act
        $this->assertSame(false, $captureTx(false));
        $this->assertThrows(TestDummyException::class, function () use ($captureTx) {
            $captureTx(true);
        });

        // Assert
        $this->assertSame(2, count($this->mockEventSink->getTransactions()));
        $this->assertSame(2, count($this->mockEventSink->getSpans()));
    }
}
