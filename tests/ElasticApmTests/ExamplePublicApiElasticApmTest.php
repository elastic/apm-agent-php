<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests;

use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\Util\ArrayUtil;
use Elastic\Apm\TransactionDataInterface;

class ExamplePublicApiElasticApmTest extends Util\TestCaseBase
{
    public function test(): void
    {
        // Act
        $exampleApp = new ExamplePublicApiElasticApm();
        $exampleApp->processCheckoutRequest(1);
        $exampleApp->processCheckoutRequest(2);

        // Assert
        // 2 calls to processCheckoutRequest == 2 transactions
        $this->assertSame(2, count($this->mockEventSink->getTransactions()));
        /** @var TransactionDataInterface */
        $tx1 = ArrayUtil::findByPredicate(
            $this->mockEventSink->getTransactions(),
            function (TransactionDataInterface $tx): bool {
                return $tx->getLabels()['shop-id'] === 'Shop #1';
            }
        );
        /** @var TransactionDataInterface */
        $tx2 = ArrayUtil::findByPredicate(
            $this->mockEventSink->getTransactions(),
            function (TransactionDataInterface $tx): bool {
                return $tx->getLabels()['shop-id'] === 'Shop #2';
            }
        );

        // each transaction produces 4 spans
        // 1) Get shopping cart items
        //      1.1) DB query or Fetch from Redis
        // 2) Charge payment
        //      2.1) DB query or Fetch from Redis
        $this->assertSame(8, count($this->mockEventSink->getSpans()));

        $this->verifyTransactionAndSpans(
            $tx1,
            $this->mockEventSink->getSpansForTransaction($tx1),
            /* $isFirstTx: */ true
        );
        $this->verifyTransactionAndSpans(
            $tx2,
            $this->mockEventSink->getSpansForTransaction($tx2),
            /* $isFirstTx: */ false
        );

        $spansWithLostLabel = array_filter(
            $this->mockEventSink->getSpans(),
            function (SpanDataInterface $span): bool {
                return array_key_exists(ExamplePublicApiElasticApm::LOST_LABEL, $span->getLabels());
            }
        );
        $this->assertCount(0, $spansWithLostLabel);

        $transactionsWithLostLabel = array_filter(
            $this->mockEventSink->getTransactions(),
            function (TransactionDataInterface $transaction): bool {
                return array_key_exists(ExamplePublicApiElasticApm::LOST_LABEL, $transaction->getLabels());
            }
        );
        $this->assertCount(0, $transactionsWithLostLabel);
    }

    /**
     * @param TransactionDataInterface $transaction
     * @param array<SpanDataInterface> $spans
     * @param bool                     $isFirstTx
     */
    private function verifyTransactionAndSpans(
        TransactionDataInterface $transaction,
        array $spans,
        bool $isFirstTx
    ): void {
        $this->assertSame(4, count($spans));

        $this->assertValidTransactionAndItsSpans($transaction, $spans);

        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_NAME, $transaction->getName());
        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_TYPE, $transaction->getType());

        foreach ($spans as $span) {
            if ($isFirstTx) {
                $this->assertSame("Shop #1", $span->getLabels()['shop-id']);
            } else {
                $this->assertSame("Shop #2", $span->getLabels()['shop-id']);
            }
        }

        /** @var array<SpanDataInterface> */
        $businessSpans = array_filter(
            $spans,
            function (SpanDataInterface $span): bool {
                return $span->getType() === 'business';
            }
        );

        $this->assertSame(2, count($businessSpans));
        /** @var SpanDataInterface $businessSpan */
        foreach ($businessSpans as $businessSpan) {
            $this->assertSame($transaction->getId(), $businessSpan->getParentId());
            if ($isFirstTx) {
                $this->assertSame(false, $businessSpan->getLabels()['is-data-in-cache']);
            } else {
                $this->assertSame(true, $businessSpan->getLabels()['is-data-in-cache']);
            }
        }

        /** @var SpanDataInterface $getShoppingCartItemsSpan */
        $getShoppingCartItemsSpan = ArrayUtil::findByPredicate(
            $businessSpans,
            function (SpanDataInterface $span): bool {
                return $span->getName() === 'Get shopping cart items';
            }
        );
        $this->assertNotNull($getShoppingCartItemsSpan);

        /** @var SpanDataInterface $chargePaymentSpan */
        $chargePaymentSpan = ArrayUtil::findByPredicate(
            $businessSpans,
            function (SpanDataInterface $span): bool {
                return $span->getName() === 'Charge payment';
            }
        );
        $this->assertNotNull($chargePaymentSpan);

        /** @var array<SpanDataInterface> */
        $dbSpans = array_filter(
            $spans,
            function (SpanDataInterface $span): bool {
                return $span->getType() === 'db';
            }
        );

        $this->assertSame(2, count($dbSpans));
        /** @var SpanDataInterface $dbSpan */
        foreach ($dbSpans as $dbSpan) {
            $dataId = $dbSpan->getLabels()['data-id'];
            if ($dataId === 'shopping-cart-items') {
                $this->assertSame($getShoppingCartItemsSpan->getId(), $dbSpan->getParentId());
            } else {
                $this->assertSame('payment-method-details', $dataId);
                $this->assertSame($chargePaymentSpan->getId(), $dbSpan->getParentId());
            }
        }
    }
}
