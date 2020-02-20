<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\ElasticApm;

/**
 * @see \ElasticApmTests\PublicApiTest::testExamplePublicApiElasticApm - test that depends on this class
 */
final class ExamplePublicApiElasticApm
{
    /** @var array<string, bool> */
    private $isDataInCache = [];

    public function processCheckoutRequest(string $shopId): void
    {
        $tx = ElasticApm::beginCurrentTransaction('Checkout transaction', 'request');

        $tx->setLabel('shop-id', $shopId);

        $this->getShoppingCartItems();
        $this->chargePayment();

        ElasticApm::getCurrentSpan()->setLabel('lost-tag-because-there-is-no-current-span', 123.456);

        $tx->end();

        ElasticApm::getCurrentTransaction()->setLabel('lost-tag-because-there-is-no-current-transaction', null);
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
        $span = ElasticApm::beginCurrentSpan('DB query', 'db', 'mysql', 'query');

        // ...

        // $span1 = $span->getParentSpan();

        ElasticApm::getCurrentSpan()->setLabel('db-row-count', 123);
        $this->processData($dataId);

        $this->addDataToCache($dataId);

        $span->end();
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
