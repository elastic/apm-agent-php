<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\SpanDataInterface;
use ElasticApmTests\UnitTests\Util\ArrayTestUtil;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\TransactionDataInterface;

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
        $this->assertCount(2, $this->mockEventSink->idToTransaction());
        /** @var TransactionDataInterface */
        $tx1 = ArrayTestUtil::findByPredicate(
            $this->mockEventSink->idToTransaction(),
            function (TransactionDataInterface $tx): bool {
                return $tx->getLabels()['shop-id'] === 'Shop #1';
            }
        );
        /** @var TransactionDataInterface */
        $tx2 = ArrayTestUtil::findByPredicate(
            $this->mockEventSink->idToTransaction(),
            function (TransactionDataInterface $tx): bool {
                return $tx->getLabels()['shop-id'] === 'Shop #2';
            }
        );

        // each transaction produces 4 spans
        // 1) Get shopping cart items
        //      1.1) DB query or Fetch from Redis
        // 2) Charge payment
        //      2.1) DB query or Fetch from Redis
        $this->assertCount(8, $this->mockEventSink->idToSpan());

        $this->verifyTransactionAndSpans(
            $tx1,
            $this->mockEventSink->spansForTransaction($tx1),
            /* $isFirstTx: */ true
        );
        $this->verifyTransactionAndSpans(
            $tx2,
            $this->mockEventSink->spansForTransaction($tx2),
            /* $isFirstTx: */ false
        );

        $spansWithLostLabel = array_filter(
            $this->mockEventSink->idToSpan(),
            function (SpanDataInterface $span): bool {
                return array_key_exists(ExamplePublicApiElasticApm::LOST_LABEL, $span->getLabels());
            }
        );
        $this->assertCount(0, $spansWithLostLabel);

        $transactionsWithLostLabel = array_filter(
            $this->mockEventSink->idToTransaction(),
            function (TransactionDataInterface $transaction): bool {
                return array_key_exists(ExamplePublicApiElasticApm::LOST_LABEL, $transaction->getLabels());
            }
        );
        $this->assertCount(0, $transactionsWithLostLabel);
    }

    /**
     * @param TransactionDataInterface         $transaction
     * @param array<string, SpanDataInterface> $idToSpan
     * @param bool                             $isFirstTx
     */
    private function verifyTransactionAndSpans(
        TransactionDataInterface $transaction,
        array $idToSpan,
        bool $isFirstTx
    ): void {
        $this->assertCount(4, $idToSpan);

        $this->assertValidTransactionAndItsSpans($transaction, $idToSpan);

        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_NAME, $transaction->getName());
        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_TYPE, $transaction->getType());

        foreach ($idToSpan as $span) {
            if ($isFirstTx) {
                $this->assertSame("Shop #1", $span->getLabels()['shop-id']);
            } else {
                $this->assertSame("Shop #2", $span->getLabels()['shop-id']);
            }
        }

        /** @var array<SpanDataInterface> */
        $businessSpans = array_filter(
            $idToSpan,
            function (SpanDataInterface $span): bool {
                return $span->getType() === 'business';
            }
        );

        $this->assertCount(2, $businessSpans);
        /** @var SpanDataInterface $businessSpan */
        foreach ($businessSpans as $businessSpan) {
            $this->assertSame($transaction->getId(), $businessSpan->getParentId());
            if ($isFirstTx) {
                $this->assertFalse($businessSpan->getLabels()['is-data-in-cache']);
            } else {
                $this->assertTrue($businessSpan->getLabels()['is-data-in-cache']);
            }
        }

        /** @var SpanDataInterface $getShoppingCartItemsSpan */
        $getShoppingCartItemsSpan = ArrayTestUtil::findByPredicate(
            $businessSpans,
            function (SpanDataInterface $span): bool {
                return $span->getName() === 'Get shopping cart items';
            }
        );
        $this->assertNotNull($getShoppingCartItemsSpan);

        /** @var SpanDataInterface $chargePaymentSpan */
        $chargePaymentSpan = ArrayTestUtil::findByPredicate(
            $businessSpans,
            function (SpanDataInterface $span): bool {
                return $span->getName() === 'Charge payment';
            }
        );
        $this->assertNotNull($chargePaymentSpan);

        /** @var array<SpanDataInterface> */
        $dbSpans = array_filter(
            $idToSpan,
            function (SpanDataInterface $span): bool {
                return $span->getType() === 'db';
            }
        );

        $this->assertCount(2, $dbSpans);
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
