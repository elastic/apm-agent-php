<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\NoopTransaction;
use ElasticApm\Report\NoopReporter;
use ElasticApm\Report\SpanDtoInterface;
use ElasticApm\Report\TransactionDtoInterface;
use ElasticApm\TracerBuilder;
use ElasticApm\TracerSingleton;
use ElasticApmTests\Util\ArrayUtil;
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

        $this->assertThrows(
            NotFoundException::class,
            function () use ($mockReporter) {
                $mockReporter->getSpanByName('nonexistent_test_span_name');
            }
        );
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

    public function testGeneratedIds(): void
    {
        // Arrange
        $mockReporter = new MockReporter();
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

    public function testExamplePublicApiElasticApm(): void
    {
        // Arrange
        $mockReporter = new MockReporter();
        TracerSingleton::set(TracerBuilder::startNew()->withReporter($mockReporter)->build());

        // Act
        $exampleApp = new ExamplePublicApiElasticApm();
        $exampleApp->processCheckoutRequest('Shop #1');
        $exampleApp->processCheckoutRequest('Shop #2');

        // Assert
        // 2 calls to processCheckoutRequest == 2 transactions
        $this->assertSame(2, count($mockReporter->getTransactions()));
        /** @var TransactionDtoInterface */
        $tx1 = ArrayUtil::findByPredicate(
            $mockReporter->getTransactions(),
            function (TransactionDtoInterface $tx): bool {
                return $tx->getTag('shop-id') === 'Shop #1';
            }
        );
        /** @var TransactionDtoInterface */
        $tx2 = ArrayUtil::findByPredicate(
            $mockReporter->getTransactions(),
            function (TransactionDtoInterface $tx): bool {
                return $tx->getTag('shop-id') === 'Shop #2';
            }
        );

        // each transaction produces 4 spans
        // 1) Get shopping cart items
        //      1.1) DB query or Fetch from Redis
        // 2) Charge payment
        //      2.1) DB query or Fetch from Redis
        $this->assertSame(8, count($mockReporter->getSpans()));

        $verifyTxAndSpans = function (TransactionDtoInterface $tx, array $spans, bool $isFirstTx) {
            $this->assertSame(4, count($spans));

            $this->assertValidTransactionAndItsSpans($tx, $spans);

            foreach ($spans as $span) {
                if ($isFirstTx) {
                    $this->assertSame("Shop #1", $span->getTag('shop-id'));
                } else {
                    $this->assertSame("Shop #2", $span->getTag('shop-id'));
                }
            }

            /** @var array<SpanDtoInterface> */
            $businessSpans = array_filter(
                $spans,
                function (SpanDtoInterface $span): bool {
                    return $span->getType() === 'business';
                }
            );

            $this->assertSame(2, count($businessSpans));
            /** @var SpanDtoInterface $businessSpan */
            foreach ($businessSpans as $businessSpan) {
                if ($isFirstTx) {
                    $this->assertSame(false, $businessSpan->getTag('is-data-in-cache'));
                } else {
                    $this->assertSame(true, $businessSpan->getTag('is-data-in-cache'));
                }
            }

            /** @var array<SpanDtoInterface> */
            $dbSpans = array_filter(
                $spans,
                function (SpanDtoInterface $span): bool {
                    return $span->getType() === 'db';
                }
            );

            $this->assertSame(2, count($dbSpans));
            /** @var SpanDtoInterface $dbSpan */
            foreach ($dbSpans as $dbSpan) {
                $dataId = $dbSpan->getTag('data-id');
                $this->assertTrue($dataId === 'shopping-cart-items' || $dataId === 'payment-method-details');
            }
        };

        $verifyTxAndSpans($tx1, $mockReporter->getSpansForTransaction($tx1), /* $isFirstTx: */ true);
        $verifyTxAndSpans($tx2, $mockReporter->getSpansForTransaction($tx2), /* $isFirstTx: */ false);

        $spansWithLostTag = array_filter(
            $mockReporter->getSpans(),
            function (SpanDtoInterface $span): bool {
                return $span->getTag('lost-tag-because-there-is-no-current-span') !== null;
            }
        );
        $this->assertSame(0, count($spansWithLostTag));
    }
}
