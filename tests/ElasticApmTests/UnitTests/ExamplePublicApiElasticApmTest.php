<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\TransactionDto;

class ExamplePublicApiElasticApmTest extends TracerUnitTestCaseBase
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
        /** @var TransactionDto $tx1 */
        $tx1 = ArrayUtilForTests::findByPredicate(
            $this->mockEventSink->idToTransaction(),
            function (TransactionDto $tx): bool {
                return self::getLabel($tx, 'shop-id') === 'Shop #1';
            }
        );
        /** @var TransactionDto $tx2 */
        $tx2 = ArrayUtilForTests::findByPredicate(
            $this->mockEventSink->idToTransaction(),
            function (TransactionDto $tx): bool {
                return self::getLabel($tx, 'shop-id') === 'Shop #2';
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
            function (SpanDto $span): bool {
                return self::hasLabel($span, ExamplePublicApiElasticApm::LOST_LABEL);
            }
        );
        $this->assertCount(0, $spansWithLostLabel);

        $transactionsWithLostLabel = array_filter(
            $this->mockEventSink->idToTransaction(),
            function (TransactionDto $transaction): bool {
                return self::hasLabel($transaction, ExamplePublicApiElasticApm::LOST_LABEL);
            }
        );
        $this->assertCount(0, $transactionsWithLostLabel);
    }

    /**
     * @param TransactionDto         $transaction
     * @param array<string, SpanDto> $idToSpan
     * @param bool                   $isFirstTx
     */
    private function verifyTransactionAndSpans(
        TransactionDto $transaction,
        array $idToSpan,
        bool $isFirstTx
    ): void {
        $this->assertCount(4, $idToSpan);

        $this->assertValidTransactionAndSpans($transaction, $idToSpan);

        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_NAME, $transaction->name);
        $this->assertSame(ExamplePublicApiElasticApm::TRANSACTION_TYPE, $transaction->type);

        foreach ($idToSpan as $span) {
            $this->assertHasLabel($span, 'shop-id');
            if ($isFirstTx) {
                $this->assertSame("Shop #1", self::getLabel($span, 'shop-id'));
            } else {
                $this->assertSame("Shop #2", self::getLabel($span, 'shop-id'));
            }
        }

        $businessSpans = array_filter(
            $idToSpan,
            function (SpanDto $span): bool {
                return $span->type === 'business';
            }
        );

        $this->assertCount(2, $businessSpans);
        /** @var SpanDto $businessSpan */
        foreach ($businessSpans as $businessSpan) {
            $this->assertSame($transaction->id, $businessSpan->parentId);
            $this->assertHasLabel($businessSpan, 'is-data-in-cache');
            if ($isFirstTx) {
                $this->assertFalse(self::getLabel($businessSpan, 'is-data-in-cache'));
            } else {
                $this->assertTrue(self::getLabel($businessSpan, 'is-data-in-cache'));
            }
        }

        /** @var SpanDto $getShoppingCartItemsSpan */
        $getShoppingCartItemsSpan = ArrayUtilForTests::findByPredicate(
            $businessSpans,
            function (SpanDto $span): bool {
                return $span->name === 'Get shopping cart items';
            }
        );
        $this->assertNotNull($getShoppingCartItemsSpan);

        /** @var SpanDto $chargePaymentSpan */
        $chargePaymentSpan = ArrayUtilForTests::findByPredicate(
            $businessSpans,
            function (SpanDto $span): bool {
                return $span->name === 'Charge payment';
            }
        );
        $this->assertNotNull($chargePaymentSpan);

        $dbSpans = array_filter(
            $idToSpan,
            function (SpanDto $span): bool {
                return $span->type === 'db';
            }
        );

        $this->assertCount(2, $dbSpans);
        /** @var SpanDto $dbSpan */
        foreach ($dbSpans as $dbSpan) {
            $dataId = self::getLabel($dbSpan, 'data-id');
            if ($dataId === 'shopping-cart-items') {
                $this->assertSame($getShoppingCartItemsSpan->id, $dbSpan->parentId);
            } else {
                $this->assertSame('payment-method-details', $dataId);
                $this->assertSame($chargePaymentSpan->id, $dbSpan->parentId);
            }
        }
    }
}
