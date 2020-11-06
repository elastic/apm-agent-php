<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;
use ElasticApmTests\Util\DummyExceptionForTests;

class CapturePublicApiTest extends UnitTestCaseBase
{
    public function testElasticApmCurrentTransactionReturnVoid(): void
    {
        // Act

        // @phpstan-ignore-next-line
        $retVal = ElasticApm::captureCurrentTransaction(
            'initial_test_TX_name',
            'initial_test_TX_type',
            function (TransactionInterface $capturedTx): void {
                $this->assertSame($capturedTx, ElasticApm::getCurrentTransaction());
                $capturedTx->setName('final_test_TX_name');
                $capturedTx->setType('final_test_TX_type');
            }
        );

        // Assert

        // https://www.php.net/manual/en/migration71.new-features.php
        // Attempting to use a void function's return value simply evaluates to NULL
        // @phpstan-ignore-next-line
        $this->assertNull($retVal);
        $tx = $this->mockEventSink->singleTransaction();
        $this->assertSame('final_test_TX_name', $tx->name);
        $this->assertSame('final_test_TX_type', $tx->type);
    }

    public function testElasticApmCurrentTransactionReturnNull(): void
    {
        // Act

        $retVal = ElasticApm::captureCurrentTransaction(
            'initial_test_TX_name',
            'initial_test_TX_type',
            /**
             * @param TransactionInterface $capturedTx
             *
             * @return mixed
             */
            function (TransactionInterface $capturedTx) {
                $this->assertSame($capturedTx, ElasticApm::getCurrentTransaction());
                $capturedTx->setName('final_test_TX_name');
                $capturedTx->setType('final_test_TX_type');
                return null;
            }
        );

        // Assert

        $this->assertNull($retVal);
        $tx = $this->mockEventSink->singleTransaction();
        $this->assertSame('final_test_TX_name', $tx->name);
        $this->assertSame('final_test_TX_type', $tx->type);
    }

    public function testElasticApmCurrentTransactionReturnObject(): void
    {
        // Act
        /** @var TestDummyObject $retVal */
        $retVal = ElasticApm::captureCurrentTransaction(
            'initial_test_TX_name',
            'initial_test_TX_type',
            function (TransactionInterface $capturedTx): TestDummyObject {
                $capturedTx->setName('final_test_TX_name');
                $capturedTx->setType('final_test_TX_type');
                return new TestDummyObject('some dummy property value');
            }
        );

        // Assert
        $this->assertInstanceOf(TestDummyObject::class, $retVal);
        $this->assertSame('some dummy property value', $retVal->dummyPublicStringProperty);
        $tx = $this->mockEventSink->singleTransaction();
        $this->assertSame('final_test_TX_name', $tx->name);
        $this->assertSame('final_test_TX_type', $tx->type);
    }

    public function testElasticApmCurrentSpanReturnVoid(): void
    {
        // Act

        $retVal = 'dummy';
        ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            /**
             * @return mixed
             */
            function () use (&$retVal): void {
                // @phpstan-ignore-next-line
                $retVal = ElasticApm::getCurrentTransaction()->captureCurrentSpan(
                    'initial_test_span_name',
                    'initial_test_span_type',
                    function (SpanInterface $capturedSpan): void {
                        $this->assertSame($capturedSpan, ElasticApm::getCurrentTransaction()->getCurrentSpan());
                        $capturedSpan->setName('final_test_span_name');
                        $capturedSpan->setType('final_test_span_type');
                        $capturedSpan->setSubtype('final_test_span_subtype');
                        $capturedSpan->setAction('final_test_span_action');
                    },
                    'initial_test_span_subtype',
                    'initial_test_span_action'
                );
            }
        );

        // Assert

        // https://www.php.net/manual/en/migration71.new-features.php
        // Attempting to use a void function's return value simply evaluates to NULL
        // @phpstan-ignore-next-line
        $this->assertNull($retVal);
        $tx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $tx->name);
        $this->assertSame('test_TX_type', $tx->type);
        $span = $this->mockEventSink->singleSpan();
        $this->assertSame('final_test_span_name', $span->name);
        $this->assertSame('final_test_span_type', $span->type);
        $this->assertSame('final_test_span_subtype', $span->subtype);
        $this->assertSame('final_test_span_action', $span->action);
    }

    public function testElasticApmCurrentSpanReturnObject(): void
    {
        // Act
        /** @var TestDummyObject $retVal */
        $retVal = ElasticApm::captureCurrentTransaction(
            'test_TX_name',
            'test_TX_type',
            function (): TestDummyObject {
                return ElasticApm::getCurrentTransaction()->captureCurrentSpan(
                    'initial_test_span_name',
                    'initial_test_span_type',
                    function (SpanInterface $capturedSpan): TestDummyObject {
                        $this->assertSame($capturedSpan, ElasticApm::getCurrentTransaction()->getCurrentSpan());

                        $capturedSpan->setName('final_test_span_name');
                        $capturedSpan->setType('final_test_span_type');
                        $capturedSpan->setSubtype('final_test_span_subtype');
                        $capturedSpan->setAction('final_test_span_action');

                        return new TestDummyObject('some dummy property value');
                    },
                    'initial_test_span_subtype',
                    'initial_test_span_action'
                );
            }
        );

        // Assert
        $this->assertInstanceOf(TestDummyObject::class, $retVal);
        $this->assertSame('some dummy property value', $retVal->dummyPublicStringProperty);
        $tx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $tx->name);
        $this->assertSame('test_TX_type', $tx->type);
        $span = $this->mockEventSink->singleSpan();
        $this->assertSame('final_test_span_name', $span->name);
        $this->assertSame('final_test_span_type', $span->type);
        $this->assertSame('final_test_span_subtype', $span->subtype);
        $this->assertSame('final_test_span_action', $span->action);
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
        $this->assertThrows(
            DummyExceptionForTests::class,
            function () use ($captureTx) {
                $captureTx(/* shouldThrow:*/ true);
            }
        );

        // Assert
        $this->assertCount(2, $this->mockEventSink->idToTransaction());
        $this->assertCount(2, $this->mockEventSink->idToSpan());
    }
}
