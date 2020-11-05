<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\SpanInterface;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;
use ElasticApmTests\Util\DummyExceptionForTests;
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
        $this->assertTransactionEquals($tx, $this->mockEventSink->singleTransaction());
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
        $this->assertTransactionEquals($tx, $this->mockEventSink->singleTransaction());
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
        $this->assertSpanEquals($span, $this->mockEventSink->singleSpan());
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
        $this->assertSpanEquals($span, $this->mockEventSink->singleSpan());
    }

    public function testWhenExceptionThrown(): void
    {
        $throwingFunc = function (bool $shouldThrow): bool {
            if ($shouldThrow) {
                throw new DummyExceptionForTests("A message");
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
        $this->assertFalse($captureTx(false));
        $this->assertThrows(DummyExceptionForTests::class, function () use ($captureTx) {
            $captureTx(/* shouldThrow:*/ true);
        });

        // Assert
        $this->assertCount(2, $this->mockEventSink->idToTransaction());
        $this->assertCount(2, $this->mockEventSink->idToSpan());
    }
}
