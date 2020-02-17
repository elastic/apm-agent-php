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
        ElasticApm::beginCurrentTransaction('Checkout', 'request');

        ElasticApm::getCurrentTransaction()->setTag('shop-id', $shopId);

        $this->getShoppingCartItems();
        $this->chargePayment();

        ElasticApm::getCurrentSpan()->setTag('lost-tag-because-there-is-no-current-span', 123.456);

        ElasticApm::endCurrentTransaction();

        ElasticApm::getCurrentSpan()->setTag('lost-tag-because-there-is-no-current-transaction', null);
    }

    private function getShoppingCartItems(): void
    {
        ElasticApm::beginCurrentSpan('Get shopping cart items', 'business');

        $this->fetchData('shopping-cart-items');

        ElasticApm::endCurrentSpan();
    }

    private function fetchData(string $dataId): void
    {
        $isDataInCache = $this->checkIfDataInCache($dataId);
        ElasticApm::getCurrentSpan()->setTag('is-data-in-cache', $isDataInCache);

        if ($isDataInCache) {
            $this->redisFetch($dataId);
        } else {
            $this->dbSelect($dataId);
        }

        ElasticApm::getCurrentSpan()->setTag('shop-id', ElasticApm::getCurrentTransaction()->getTag('shop-id'));
    }

    private function redisFetch(string $dataId): void
    {
        ElasticApm::beginCurrentSpan("Fetch from Redis", 'db', 'redis', 'query');

        // ...

        ElasticApm::getCurrentSpan()->setTag('redis-response-id', "abc");
        $this->processData($dataId);

        ElasticApm::endCurrentSpan();
    }

    private function processData(string $dataId): void
    {
        ElasticApm::getCurrentSpan()->setTag('data-id', $dataId);
        ElasticApm::getCurrentSpan()->setTag('shop-id', ElasticApm::getCurrentTransaction()->getTag('shop-id'));
    }

    private function dbSelect(string $dataId): void
    {
        ElasticApm::beginCurrentSpan("DB query", 'db', 'mysql', 'query');

        // ...

        ElasticApm::getCurrentSpan()->setTag('db-row-count', 123);
        $this->processData($dataId);

        $this->addDataToCache($dataId);

        ElasticApm::endCurrentSpan();
    }

    private function chargePayment(): void
    {
        ElasticApm::beginCurrentSpan('Charge payment', 'business');

        $this->fetchData('payment-method-details');

        ElasticApm::endCurrentSpan();
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
