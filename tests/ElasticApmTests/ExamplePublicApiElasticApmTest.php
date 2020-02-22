<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\Impl\TracerBuilder;
use ElasticApm\Impl\GlobalTracerHolder;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;
use ElasticApmTests\Util\ArrayUtil;
use ElasticApmTests\Util\MockReporter;

class ExamplePublicApiElasticApmTest extends Util\TestCaseBase
{
    public function test(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        GlobalTracerHolder::set(TracerBuilder::startNew()->withReporter($mockReporter)->build());

        // Act
        $exampleApp = new ExamplePublicApiElasticApm();
        $exampleApp->processCheckoutRequest(1);
        $exampleApp->processCheckoutRequest(2);

        // Assert
        // 2 calls to processCheckoutRequest == 2 transactions
        $this->assertSame(2, count($mockReporter->getTransactions()));
        /** @var TransactionInterface */
        $tx1 = ArrayUtil::findByPredicate(
            $mockReporter->getTransactions(),
            function (TransactionInterface $tx): bool {
                return $tx->getLabel('shop-id') === 'Shop #1';
            }
        );
        /** @var TransactionInterface */
        $tx2 = ArrayUtil::findByPredicate(
            $mockReporter->getTransactions(),
            function (TransactionInterface $tx): bool {
                return $tx->getLabel('shop-id') === 'Shop #2';
            }
        );

        // each transaction produces 4 spans
        // 1) Get shopping cart items
        //      1.1) DB query or Fetch from Redis
        // 2) Charge payment
        //      2.1) DB query or Fetch from Redis
        $this->assertSame(8, count($mockReporter->getSpans()));

        $this->verifyTransactionAndSpans(
            $tx1,
            $mockReporter->getSpansForTransaction($tx1),
            /* $isFirstTx: */ true
        );
        $this->verifyTransactionAndSpans(
            $tx2,
            $mockReporter->getSpansForTransaction($tx2),
            /* $isFirstTx: */ false
        );

        $spansWithLostLabel = array_filter(
            $mockReporter->getSpans(),
            function (SpanInterface $span): bool {
                return $span->getLabel(ExamplePublicApiElasticApm::LOST_LABEL) !== null;
            }
        );
        $this->assertSame(0, count($spansWithLostLabel));

        $transactionsWithLostLabel = array_filter(
            $mockReporter->getTransactions(),
            function (TransactionInterface $transaction): bool {
                return $transaction->getLabel(ExamplePublicApiElasticApm::LOST_LABEL) !== null;
            }
        );
        $this->assertSame(0, count($transactionsWithLostLabel));
    }

    /**
     * @param TransactionInterface $transaction
     * @param array<SpanInterface> $spans
     * @param bool                 $isFirstTx
     */
    private function verifyTransactionAndSpans(
        TransactionInterface $transaction,
        array $spans,
        bool $isFirstTx
    ): void {
        $this->assertSame(4, count($spans));

        $this->assertValidTransactionAndItsSpans($transaction, $spans);

        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_NAME, $transaction->getName());
        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_TYPE, $transaction->getType());

        foreach ($spans as $span) {
            if ($isFirstTx) {
                $this->assertSame("Shop #1", $span->getLabel('shop-id'));
            } else {
                $this->assertSame("Shop #2", $span->getLabel('shop-id'));
            }
        }

        /** @var array<SpanInterface> */
        $businessSpans = array_filter(
            $spans,
            function (SpanInterface $span): bool {
                return $span->getType() === 'business';
            }
        );

        $this->assertSame(2, count($businessSpans));
        /** @var SpanInterface $businessSpan */
        foreach ($businessSpans as $businessSpan) {
            $this->assertSame($transaction->getId(), $businessSpan->getParentId());
            if ($isFirstTx) {
                $this->assertSame(false, $businessSpan->getLabel('is-data-in-cache'));
            } else {
                $this->assertSame(true, $businessSpan->getLabel('is-data-in-cache'));
            }
        }

        /** @var SpanInterface $getShoppingCartItemsSpan */
        $getShoppingCartItemsSpan = ArrayUtil::findByPredicate(
            $businessSpans,
            function (SpanInterface $span): bool {
                return $span->getName() === 'Get shopping cart items';
            }
        );
        $this->assertNotNull($getShoppingCartItemsSpan);

        /** @var SpanInterface $chargePaymentSpan */
        $chargePaymentSpan = ArrayUtil::findByPredicate(
            $businessSpans,
            function (SpanInterface $span): bool {
                return $span->getName() === 'Charge payment';
            }
        );
        $this->assertNotNull($chargePaymentSpan);

        /** @var array<SpanInterface> */
        $dbSpans = array_filter(
            $spans,
            function (SpanInterface $span): bool {
                return $span->getType() === 'db';
            }
        );

        $this->assertSame(2, count($dbSpans));
        /** @var SpanInterface $dbSpan */
        foreach ($dbSpans as $dbSpan) {
            $dataId = $dbSpan->getLabel('data-id');
            if ($dataId === 'shopping-cart-items') {
                $this->assertSame($getShoppingCartItemsSpan->getId(), $dbSpan->getParentId());
            } else {
                $this->assertSame('payment-method-details', $dataId);
                $this->assertSame($chargePaymentSpan->getId(), $dbSpan->getParentId());
            }
        }
    }
}
