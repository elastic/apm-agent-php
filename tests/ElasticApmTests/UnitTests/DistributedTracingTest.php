<?php

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\ElasticApm;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class DistributedTracingTest extends TracerUnitTestCaseBase
{
    /**
     * @return array<array<bool>>
     */
    public function dataProviderForTestManuallyPassDistributedTracingData(): array
    {
        return [[false], [true]];
    }

    /**
     * @dataProvider dataProviderForTestManuallyPassDistributedTracingData
     *
     * @param bool $shouldSendFromSpan
     */
    public function testManuallyPassDistributedTracingData(bool $shouldSendFromSpan): void
    {
        // Arrange
        // Act

        $senderTransaction = ElasticApm::beginCurrentTransaction('POST /web-layer-api', 'web-layer');
        if ($shouldSendFromSpan) {
            $senderSpan = $senderTransaction->beginCurrentSpan('fetch from data layer', 'data-layer');
        }

        // On the sending side: get and serialize DistributedTracingData
        $senderDistData = ElasticApm::getCurrentTransaction()->getDistributedTracingData();
        $serializedDistData = json_encode($senderDistData);

        // Pass DistributedTracingData to the recievinging side

        // On the recievinging side
        $deserializedDistData = json_decode($serializedDistData);
        $receiverDistData = new DistributedTracingData();
        foreach ($deserializedDistData as $key => $val) {
            $receiverDistData->$key = $val;
        }

        $receiverTransaction = ElasticApm::beginCurrentTransaction(
            'GET /data-api',
            'data-layer',
            /* timestamp */ null,
            $receiverDistData
        );
        self::assertSame($senderTransaction->getTraceId(), $receiverTransaction->getTraceId());

        $receiverTransaction->end();

        if ($shouldSendFromSpan) {
            $senderSpan->end();
        }

        $senderTransaction->end();

        // Assert

        $idToTx = $this->mockEventSink->idToTransaction();
        $expectedTxIds = [$senderTransaction->getId(), $receiverTransaction->getId()];
        self::assertCount(2, $idToTx);
        self::assertEqualsCanonicalizing($expectedTxIds, array_keys($idToTx));
        $idToSpan = $this->mockEventSink->idToSpan();
        self::assertCount($shouldSendFromSpan ? 1 : 0, $idToSpan);
        $expectedParentId = $shouldSendFromSpan ? $senderSpan->getId() : $senderTransaction->getId();
        self::assertSame($expectedParentId, $receiverTransaction->getParentId());
    }
}
