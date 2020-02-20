<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\ElasticApm;
use ElasticApm\TransactionInterface;

/**
 * @see \ElasticApmTests\PublicApiTest::testExamplePublicApiElasticApm - test that depends on this class
 */
final class ExamplePublicApiElasticApm
{
    /** @var string */
    public const TRANSACTION_NAME = 'Checkout transaction';

    /** @var string */
    public const TRANSACTION_TYPE = 'shopping';

    /** @var string */
    public const LOST_LABEL = 'lost-label';

    /** @var array<string, bool> */
    private $isDataInCache = [];

    public function processCheckoutRequest(int $shopNumber): void
    {
        $shopId = 'Shop #' . $shopNumber;
        if ($shopNumber == 1) {
            $tx = ElasticApm::beginCurrentTransaction(self::TRANSACTION_NAME, self::TRANSACTION_TYPE);
            $this->processCheckoutRequestImpl($shopId, $tx);
            $tx->end();
        } else {
            ElasticApm::captureCurrentTransaction(
                self::TRANSACTION_NAME,
                self::TRANSACTION_TYPE,
                function (TransactionInterface $tx) use ($shopId) {
                    $this->processCheckoutRequestImpl($shopId, $tx);
                }
            );
        }
    }

    private function processCheckoutRequestImpl(string $shopId, TransactionInterface $tx): void
    {
        $tx->setLabel('shop-id', $shopId);

        $this->getShoppingCartItems();
        $this->chargePayment();

        // Lost label because there is no current span
        ElasticApm::getCurrentSpan()->setLabel(self::LOST_LABEL, 123.456);

        // Lost label because there is no current transaction
        ElasticApm::getCurrentTransaction()->setLabel(self::LOST_LABEL, null);
    }

    private function getShoppingCartItems(): void
    {
        ElasticApm::getCurrentTransaction();

        $span = ElasticApm::beginCurrentSpan('Get shopping cart items', 'business');

        $this->fetchData('shopping-cart-items');

        $span->end();
    }

    private function fetchData(string $dataId): void
    {
        $isDataInCache = $this->checkIfDataInCache($dataId);
        ElasticApm::getCurrentSpan()->setLabel('is-data-in-cache', $isDataInCache);

        if ($isDataInCache) {
            $this->redisFetch($dataId);
        } else {
            $this->dbSelect($dataId);
        }

        ElasticApm::getCurrentSpan()->setLabel('shop-id', ElasticApm::getCurrentTransaction()->getLabel('shop-id'));
    }

    private function redisFetch(string $dataId): void
    {
        $span = ElasticApm::beginCurrentSpan('Fetch from Redis', 'db', 'redis', 'query');

        // ...

        ElasticApm::getCurrentSpan()->setLabel('redis-response-id', 'abc');
        $this->processData($dataId);

        $span->end();
    }

    private function processData(string $dataId): void
    {
        ElasticApm::getCurrentSpan()->setLabel('data-id', $dataId);
        ElasticApm::getCurrentSpan()->setLabel('shop-id', ElasticApm::getCurrentTransaction()->getLabel('shop-id'));
    }

    private function dbSelect(string $dataId): void
    {
        ElasticApm::captureCurrentSpan(
            'DB query',
            'db',
            function () use ($dataId): void {
                // ...

                ElasticApm::getCurrentSpan()->setLabel('db-row-count', 123);
                $this->processData($dataId);

                $this->addDataToCache($dataId);
            },
            'mysql',
            'query'
        );
    }

    private function chargePayment(): void
    {
        $span = ElasticApm::beginCurrentSpan('Charge payment', 'business');

        $this->fetchData('payment-method-details');

        $span->end();
    }

    private function checkIfDataInCache(string $dataId): bool
    {
        return $this->isDataInCache[$dataId] ?? false;
    }

    private function addDataToCache(string $dataId): void
    {
        $this->isDataInCache[$dataId] = true;
    }
}
