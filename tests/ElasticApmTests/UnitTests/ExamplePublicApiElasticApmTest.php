<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Elastic\Apm\SpanInterface;
use Elastic\Apm\Tests\UnitTests\Util\ArrayTestUtil;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\TransactionInterface;

class ExamplePublicApiElasticApmTest extends UnitTestCaseBase
{
    public function test(): void
    {
        // Act
        $exampleApp = new ExamplePublicApiElasticApm();
        $exampleApp->processCheckoutRequest(1);
        $exampleApp->processCheckoutRequest(2);

        // Assert
        // 2 calls to processCheckoutRequest == 2 transactions
        $this->assertCount(2, $this->mockEventSink->getIdToTransaction());
        /** @var TransactionInterface */
        $tx1 = ArrayTestUtil::findByPredicate(
            $this->mockEventSink->getIdToTransaction(),
            function (TransactionInterface $tx): bool {
                return $tx->context()->getLabels()['shop-id'] === 'Shop #1';
            }
        );
        /** @var TransactionInterface */
        $tx2 = ArrayTestUtil::findByPredicate(
            $this->mockEventSink->getIdToTransaction(),
            function (TransactionInterface $tx): bool {
                return $tx->context()->getLabels()['shop-id'] === 'Shop #2';
            }
        );

        // each transaction produces 4 spans
        // 1) Get shopping cart items
        //      1.1) DB query or Fetch from Redis
        // 2) Charge payment
        //      2.1) DB query or Fetch from Redis
        $this->assertCount(8, $this->mockEventSink->getIdToSpan());

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
            $this->mockEventSink->getIdToSpan(),
            function (SpanInterface $span): bool {
                return array_key_exists(ExamplePublicApiElasticApm::LOST_LABEL, $span->context()->getLabels());
            }
        );
        $this->assertCount(0, $spansWithLostLabel);

        $transactionsWithLostLabel = array_filter(
            $this->mockEventSink->getIdToTransaction(),
            function (TransactionInterface $transaction): bool {
                return array_key_exists(ExamplePublicApiElasticApm::LOST_LABEL, $transaction->context()->getLabels());
            }
        );
        $this->assertCount(0, $transactionsWithLostLabel);
    }

    /**
     * @param TransactionInterface         $transaction
     * @param array<string, SpanInterface> $idToSpan
     * @param bool                             $isFirstTx
     */
    private function verifyTransactionAndSpans(
        TransactionInterface $transaction,
        array $idToSpan,
        bool $isFirstTx
    ): void {
        $this->assertCount(4, $idToSpan);

        $this->assertValidTransactionAndItsSpans($transaction, $idToSpan);

        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_NAME, $transaction->getName());
        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_TYPE, $transaction->getType());

        foreach ($idToSpan as $span) {
            if ($isFirstTx) {
                $this->assertSame("Shop #1", $span->context()->getLabels()['shop-id']);
            } else {
                $this->assertSame("Shop #2", $span->context()->getLabels()['shop-id']);
            }
        }

        /** @var array<SpanInterface> */
        $businessSpans = array_filter(
            $idToSpan,
            function (SpanInterface $span): bool {
                return $span->getType() === 'business';
            }
        );

        $this->assertCount(2, $businessSpans);
        /** @var SpanInterface $businessSpan */
        foreach ($businessSpans as $businessSpan) {
            $this->assertSame($transaction->getId(), $businessSpan->getParentId());
            if ($isFirstTx) {
                $this->assertFalse($businessSpan->context()->getLabels()['is-data-in-cache']);
            } else {
                $this->assertTrue($businessSpan->context()->getLabels()['is-data-in-cache']);
            }
        }

        /** @var SpanInterface $getShoppingCartItemsSpan */
        $getShoppingCartItemsSpan = ArrayTestUtil::findByPredicate(
            $businessSpans,
            function (SpanInterface $span): bool {
                return $span->getName() === 'Get shopping cart items';
            }
        );
        $this->assertNotNull($getShoppingCartItemsSpan);

        /** @var SpanInterface $chargePaymentSpan */
        $chargePaymentSpan = ArrayTestUtil::findByPredicate(
            $businessSpans,
            function (SpanInterface $span): bool {
                return $span->getName() === 'Charge payment';
            }
        );
        $this->assertNotNull($chargePaymentSpan);

        /** @var array<SpanInterface> */
        $dbSpans = array_filter(
            $idToSpan,
            function (SpanInterface $span): bool {
                return $span->getType() === 'db';
            }
        );

        $this->assertCount(2, $dbSpans);
        /** @var SpanInterface $dbSpan */
        foreach ($dbSpans as $dbSpan) {
            $dataId = $dbSpan->context()->getLabels()['data-id'];
            if ($dataId === 'shopping-cart-items') {
                $this->assertSame($getShoppingCartItemsSpan->getId(), $dbSpan->getParentId());
            } else {
                $this->assertSame('payment-method-details', $dataId);
                $this->assertSame($chargePaymentSpan->getId(), $dbSpan->getParentId());
            }
        }
    }
}
