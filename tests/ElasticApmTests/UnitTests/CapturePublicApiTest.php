<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\Tests\Util\DummyForTestsException;
use Elastic\Apm\TransactionInterface;

class CapturePublicApiTest extends UnitTestCaseBase
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
        $this->assertTransactionEquals($tx, $this->mockEventSink->getSingleTransaction());
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
        $this->assertTransactionEquals($tx, $this->mockEventSink->getSingleTransaction());
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
                return ElasticApm::getCurrentTransaction()->captureCurrentSpan(
                    'test_span_name',
                    'test_span_type',
                    function (SpanInterface $spanArg) use (&$span) {
                        $this->assertSame($spanArg, ElasticApm::getCurrentTransaction()->getCurrentSpan());
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
        $this->assertSpanEquals($span, $this->mockEventSink->getSingleSpan());
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
                return ElasticApm::getCurrentTransaction()->captureCurrentSpan(
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
        $this->assertSpanEquals($span, $this->mockEventSink->getSingleSpan());
    }

    public function testWhenExceptionThrown(): void
    {
        $throwingFunc = function (bool $shouldThrow): bool {
            if ($shouldThrow) {
                throw new DummyForTestsException("A message");
            }
            return $shouldThrow;
        };

        $captureTx = function (bool $shouldThrow) use ($throwingFunc): bool {
            return ElasticApm::captureCurrentTransaction(
                'test_TX_name',
                'test_TX_type',
                function () use ($throwingFunc, $shouldThrow): bool {
                    return ElasticApm::getCurrentTransaction()->captureCurrentSpan(
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
        $this->assertFalse($captureTx(false));
        $this->assertThrows(DummyForTestsException::class, function () use ($captureTx) {
            $captureTx(/* shouldThrow:*/ true);
        });

        // Assert
        $this->assertCount(2, $this->mockEventSink->getIdToTransaction());
        $this->assertCount(2, $this->mockEventSink->getIdToSpan());
    }
}
