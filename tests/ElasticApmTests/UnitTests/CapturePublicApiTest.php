<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\UnitTests\Util\MockConfigRawSnapshotSource;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\DummyExceptionForTests;
use ElasticApmTests\Util\LogSinkForTests;

class CapturePublicApiTest extends TracerUnitTestCaseBase
{
    /** @var int */
    private static $callToMethodThrowingDummyExceptionForTestsLineNumber;

    // public function setUp(): void
    // {
    //     $this->setUpTestEnv(
    //         function (TracerBuilder $builder): void {
    //             $mockConfig = new MockConfigRawSnapshotSource();
    //             $mockConfig->set(OptionNames::LOG_LEVEL, LogLevel::intToName(LogLevel::TRACE));
    //             $builder->withLogSink(new LogSinkForTests(__CLASS__))
    //                     ->withConfigRawSnapshotSource($mockConfig);
    //         }
    //     );
    // }

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

    private static function methodThrowingDummyExceptionForTests(): void
    {
        throw new DummyExceptionForTests("A message", 123321);
    }

    public function testWhenExceptionThrown(): void
    {
        $throwingFunc = function (bool $shouldThrow): bool {
            if ($shouldThrow) {
                self::$callToMethodThrowingDummyExceptionForTestsLineNumber = __LINE__ + 1;
                self::methodThrowingDummyExceptionForTests();
            }
            return $shouldThrow;
        };

        $throwingRunData = [];
        $captureTx = function (bool $shouldThrow) use ($throwingFunc, &$throwingRunData): bool {
            return ElasticApm::captureCurrentTransaction(
                'test_TX_name',
                $shouldThrow ? 'test_throwing_TX_type' : 'test_TX_type',
                function (TransactionInterface $tx) use ($throwingFunc, $shouldThrow, &$throwingRunData): bool {
                    $throwingRunData['traceId'] = $tx->getTraceId();
                    $throwingRunData['transactionId'] = $tx->getId();
                    $tx->context()->setLabel('TX_label_key', 'TX_label_value');
                    return ElasticApm::getCurrentTransaction()->captureCurrentSpan(
                        'test_span_name',
                        'test_span_type',
                        function (SpanInterface $span) use ($throwingFunc, $shouldThrow, &$throwingRunData): bool {
                            $throwingRunData['spanId'] = $span->getId();
                            $span->context()->setLabel('span_label_key', 'span_label_value');
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

        $this->assertCount(2, $this->mockEventSink->eventsFromAgent->idToTransaction);
        $this->assertCount(2, $this->mockEventSink->eventsFromAgent->idToSpan);

        $this->assertCount(2, $this->mockEventSink->eventsFromAgent->idToError);
        foreach ($this->mockEventSink->eventsFromAgent->idToError as $_ => $error) {
            self::assertSame($throwingRunData['traceId'], $error->traceId);
            self::assertSame($throwingRunData['transactionId'], $error->transactionId);
            self::assertArrayHasKey($error->transactionId, $this->mockEventSink->eventsFromAgent->idToTransaction);
            self::assertArrayHasKey($throwingRunData['spanId'], $this->mockEventSink->eventsFromAgent->idToSpan);
            self::assertArrayHasKey($error->id, $this->mockEventSink->eventsFromAgent->idToError);
            self::assertTrue(
                $error->parentId === $throwingRunData['spanId']
                || $error->parentId === $throwingRunData['transactionId']
            );

            self::assertNotNull($error->transaction);
            self::assertSame('test_throwing_TX_type', $error->transaction->type);
            self::assertTrue($error->transaction->isSampled);

            // Only transaction's context is copied to an error and thus only transaction's labels
            self::assertNotNull($error->context);
            self::assertCount(1, $error->context->labels);
            self::assertSame('TX_label_value', $error->context->labels['TX_label_key']);

            self::assertNotNull($error->exception);
            self::assertSame(DummyExceptionForTests::NAMESPACE, $error->exception->module);
            self::assertSame(DummyExceptionForTests::CLASS_NAME, $error->exception->type);
            self::assertNotNull($error->exception->stacktrace);
            $topFrame = $error->exception->stacktrace[0];
            self::assertSame(__CLASS__ . '::methodThrowingDummyExceptionForTests()', $topFrame->function);
            self::assertSame(__FILE__, $topFrame->filename);
            self::assertSame(self::$callToMethodThrowingDummyExceptionForTestsLineNumber, $topFrame->lineno);
        }
    }
}
