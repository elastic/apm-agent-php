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

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\GlobalTracerHolder;
use ElasticApmTests\UnitTests\Util\MockEventSink;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class DistributedTracingTest extends TracerUnitTestCaseBase
{
    /**
     * @return iterable<array<bool>>
     */
    public function dataProviderForTestManualPassDistTracingData(): iterable
    {
        yield [/* isSentFromSpan: */
               false,
        ];
        yield [/* isSentFromSpan: */
               true,
        ];
    }

    /**
     * @dataProvider dataProviderForTestManualPassDistTracingData
     *
     * @param bool $isSentFromSpan
     */
    public function testManualPassDistTracingData(bool $isSentFromSpan): void
    {
        // Arrange
        // Act

        $senderTransaction = ElasticApm::beginCurrentTransaction('POST /web-layer-api', 'web-layer');
        if ($isSentFromSpan) {
            $senderSpan = $senderTransaction->beginCurrentSpan('fetch from data layer', 'data-layer');
        }

        // On the sending side: get and serialize DistributedTracingData for the current span/transaction
        $senderDistTracingData = ElasticApm::getSerializedCurrentDistributedTracingData();

        // Pass DistributedTracingData to the receivinging side
        $receiverDistTracingData = $senderDistTracingData;

        // On the receivinging side
        $receiverTransaction = ElasticApm::beginCurrentTransaction(
            'GET /data-api',
            'data-layer',
            /* timestamp */ null,
            $receiverDistTracingData
        );
        self::assertSame($senderTransaction->getTraceId(), $receiverTransaction->getTraceId());

        $receiverTransaction->end();

        if ($isSentFromSpan) {
            $senderSpan->end();
        }

        $senderTransaction->end();

        // Assert

        $idToTx = $this->mockEventSink->idToTransaction();
        $expectedTxIds = [$senderTransaction->getId(), $receiverTransaction->getId()];
        self::assertCount(2, $idToTx);
        self::assertEqualsCanonicalizing($expectedTxIds, array_keys($idToTx));
        $idToSpan = $this->mockEventSink->idToSpan();
        self::assertCount($isSentFromSpan ? 1 : 0, $idToSpan);
        $expectedParentId = $isSentFromSpan ? $senderSpan->getId() : $senderTransaction->getId();
        self::assertSame($expectedParentId, $receiverTransaction->getParentId());
    }

    public function testManualPassDistTracingDataTracerDisabled(): void
    {
        foreach ([false, true] as $isSenderTracerEnabled) {
            foreach ([false, true] as $isReceiverTracerEnabled) {
                foreach ([0, 1, 2] as $distDataSource) {
                    $this->implManualPassDistTracingDataTracerDisabled(
                        $isSenderTracerEnabled,
                        $isReceiverTracerEnabled,
                        $distDataSource
                    );
                }
            }
        }
    }

    public function implManualPassDistTracingDataTracerDisabled(
        bool $isSenderTracerEnabled,
        bool $isReceiverTracerEnabled,
        int $distDataSource
    ): void {
        // Arrange

        $senderEventSink = new MockEventSink();
        $senderTracer = self::buildTracerForTests($senderEventSink)->withEnabled($isSenderTracerEnabled)->build();
        $receiverEventSink = new MockEventSink();
        $receiverTracer = self::buildTracerForTests($receiverEventSink)->withEnabled($isReceiverTracerEnabled)->build();

        // Act

        // On the sending side:
        GlobalTracerHolder::set($senderTracer);

        if ($distDataSource !== 0) {
            $senderTransaction = ElasticApm::beginCurrentTransaction('POST /web-layer-api', 'web-layer');
            if ($distDataSource !== 1) {
                $senderSpan = $senderTransaction->beginCurrentSpan('fetch from data layer', 'data-layer');
            }
        }

        // On the sending side: get and serialize DistributedTracingData for the current span/transaction
        $senderDistTracingData = ElasticApm::getSerializedCurrentDistributedTracingData();
        self::assertIsString($senderDistTracingData);

        // Pass DistributedTracingData to the receivinging side
        $receiverDistTracingData = $senderDistTracingData;

        // On the receivinging side
        GlobalTracerHolder::set($receiverTracer);

        // On the receivinging side: begin a new transaction and pass received DistributedTracingData
        $receiverTransaction = ElasticApm::beginCurrentTransaction(
            'GET /data-api',
            'data-layer',
            /* timestamp */ null,
            $receiverDistTracingData
        );

        $receiverTransaction->end();

        GlobalTracerHolder::set($senderTracer);

        if (isset($senderSpan)) {
            $senderSpan->end();
        }
        if (isset($senderTransaction)) {
            $senderTransaction->end();
        }

        // Assert

        self::assertEmpty($this->mockEventSink->idToTransaction());
        self::assertEmpty($this->mockEventSink->idToSpan());

        if ($isSenderTracerEnabled) {
            $expectedParentId = isset($senderSpan)
                ? $senderSpan->getId()
                : (isset($senderTransaction) ? $senderTransaction->getId() : null);

            if (isset($senderTransaction)) {
                self::assertEquals([$senderTransaction->getId()], array_keys($senderEventSink->idToTransaction()));
            } else {
                self::assertEmpty($senderEventSink->idToTransaction());
            }

            if (isset($senderSpan)) {
                self::assertEquals([$senderSpan->getId()], array_keys($senderEventSink->idToSpan()));
            } else {
                self::assertEmpty($senderEventSink->idToSpan());
            }
        } else {
            $expectedParentId = null;
            self::assertEmpty($senderEventSink->idToTransaction());
            self::assertEmpty($senderEventSink->idToSpan());
        }

        if (isset($senderTransaction) && ($isSenderTracerEnabled === $isReceiverTracerEnabled)) {
            self::assertSame($senderTransaction->getTraceId(), $receiverTransaction->getTraceId());
        }

        if ($isReceiverTracerEnabled) {
            self::assertEquals([$receiverTransaction->getId()], array_keys($receiverEventSink->idToTransaction()));
            $receiverTxData = $receiverEventSink->idToTransaction()[$receiverTransaction->getId()];
            self::assertSame($expectedParentId, $receiverTxData->parentId);
        } else {
            self::assertEmpty($receiverEventSink->idToTransaction());
            self::assertEmpty($receiverEventSink->idToSpan());
        }
    }
}
