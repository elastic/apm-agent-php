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
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\UnitTests\Util\MockEventSink;
use ElasticApmTests\UnitTests\Util\MockLogSink;
use ElasticApmTests\UnitTests\Util\MockLogSinkStatement;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class DistributedTracingTest extends TracerUnitTestCaseBase
{
    /**
     * @param string                            $api
     * @param bool                              $shouldUseDeprecatedApi
     * @param bool                              $shouldReturnHeaderValueAsArray
     * @param string                            $name
     * @param string                            $type
     * @param array<string, string>|string|null $distTracingData
     *
     * @return TransactionInterface
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function beginAndEndTransactionUsingApi(
        string $api,
        bool $shouldUseDeprecatedApi,
        bool $shouldReturnHeaderValueAsArray,
        string $name,
        string $type,
        $distTracingData
    ): TransactionInterface {
        /** @var ?string */
        $serializedDistTracingData = null;
        /** @var ?array<string, string> */
        $distTracingHeaders = null;
        if ($distTracingData !== null && $shouldUseDeprecatedApi) {
            self::assertIsString($distTracingData);
            $serializedDistTracingData = $distTracingData;
        } else {
            self::assertIsArray($distTracingData);
            $distTracingHeaders = $distTracingData;
        }

        $headerExtractor
            = /**
         * @param string $headerName
         *
         * @return string[]|string
         */
            function (string $headerName) use (
                $distTracingHeaders,
                $shouldReturnHeaderValueAsArray
            ) {
                self::assertNotNull($distTracingHeaders);
                if (!array_key_exists($headerName, $distTracingHeaders)) {
                    return [];
                }
                $headerValue = $distTracingHeaders[$headerName];
                return $shouldReturnHeaderValueAsArray ? [$headerValue] : $headerValue;
            };

        $txBody = function (TransactionInterface $tx): TransactionInterface {
            return $tx;
        };

        switch ($api) {
            case 'beginCurrentTransaction':
                $tx = $shouldUseDeprecatedApi
                    ? ElasticApm::beginCurrentTransaction(
                        $name,
                        $type,
                        null /* <- timestamp */,
                        $serializedDistTracingData
                    )
                    : ElasticApm::newTransaction($name, $type)
                                ->asCurrent()
                                ->distributedTracingHeaderExtractor($headerExtractor)
                                ->begin();
                $tx->end();
                return $tx;

            case 'captureCurrentTransaction':
                return $shouldUseDeprecatedApi // @phpstan-ignore-line
                    ? ElasticApm::captureCurrentTransaction(
                        $name,
                        $type,
                        $txBody,
                        null /* <- timestamp */,
                        $serializedDistTracingData
                    )
                    : ElasticApm::newTransaction($name, $type)
                                ->asCurrent()
                                ->distributedTracingHeaderExtractor($headerExtractor)
                                ->capture($txBody);

            case 'beginTransaction':
                $tx = $shouldUseDeprecatedApi
                    ? ElasticApm::beginTransaction(
                        $name,
                        $type,
                        null /* <- timestamp */,
                        $serializedDistTracingData
                    )
                    : ElasticApm::newTransaction($name, $type)
                                ->distributedTracingHeaderExtractor($headerExtractor)
                                ->begin();
                $tx->end();
                return $tx;

            case 'captureTransaction':
                return $shouldUseDeprecatedApi // @phpstan-ignore-line
                    ? ElasticApm::captureTransaction(
                        $name,
                        $type,
                        $txBody,
                        null /* <- timestamp */,
                        $serializedDistTracingData
                    )
                    : ElasticApm::newTransaction($name, $type)
                                ->distributedTracingHeaderExtractor($headerExtractor)
                                ->capture($txBody);

            default:
                self::fail('Unknown $api: ' . $api);
        }
    }

    /**
     * @return string[]
     */
    private static function beginTransactionApis(): array
    {
        return ['beginCurrentTransaction', 'captureCurrentTransaction', 'beginTransaction', 'captureTransaction'];
    }

    /**
     * @return iterable<array{bool, bool, bool, string}>
     */
    public function dataProviderForTestManualPassDistTracingData(): iterable
    {
        foreach ([false, true] as $isSentFromSpan) {
            foreach ([false, true] as $shouldUseDeprecatedApi) {
                foreach ([false, true] as $shouldReturnHeaderValueAsArray) {
                    foreach (self::beginTransactionApis() as $beginTransactionApi) {
                        yield [
                            $isSentFromSpan,
                            $shouldUseDeprecatedApi,
                            $shouldReturnHeaderValueAsArray,
                            $beginTransactionApi,
                        ];
                    }
                }
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestManualPassDistTracingData
     *
     * @param bool   $isSentFromSpan
     * @param bool   $shouldUseDeprecatedApi
     * @param bool   $shouldReturnHeaderValueAsArray
     * @param string $beginTransactionApi
     */
    public function testManualPassDistTracingData(
        bool $isSentFromSpan,
        bool $shouldUseDeprecatedApi,
        bool $shouldReturnHeaderValueAsArray,
        string $beginTransactionApi
    ): void {
        // Arrange
        // Act

        $senderTransaction = ElasticApm::beginCurrentTransaction('POST /web-layer-api', 'web-layer');
        if ($isSentFromSpan) {
            $senderSpan = $senderTransaction->beginCurrentSpan('fetch from data layer', 'data-layer');
        }

        // On the sending side: get and serialize DistributedTracingData for the current span/transaction
        if ($shouldUseDeprecatedApi) {
            /** @noinspection PhpDeprecationInspection */
            $senderDistTracingData = ElasticApm::getSerializedCurrentDistributedTracingData();
        } else {
            $senderDistTracingData = [];
            ElasticApm::getCurrentExecutionSegment()->injectDistributedTracingHeaders(
                function (string $headerName, string $headerValue) use (&$senderDistTracingData): void {
                    $senderDistTracingData[$headerName] = $headerValue;
                }
            );
        }

        // Pass DistributedTracingData to the receivinging side
        $receiverDistTracingData = $senderDistTracingData;

        // On the receivinging side
        $receiverTransaction = self::beginAndEndTransactionUsingApi(
            $beginTransactionApi,
            $shouldUseDeprecatedApi,
            $shouldReturnHeaderValueAsArray,
            'GET /data-api' /* <- name */,
            'data-layer' /* <- type */,
            $receiverDistTracingData
        );
        self::assertSame($senderTransaction->getTraceId(), $receiverTransaction->getTraceId());

        $receiverTransaction->end();

        if ($isSentFromSpan) {
            $senderSpan->end();
        }

        $senderTransaction->end();

        // Assert

        $expectedTxIds = [$senderTransaction->getId(), $receiverTransaction->getId()];
        $actualTxIds = self::getIdsFromIdToMap($this->mockEventSink->idToTransaction());
        self::assertCount(2, $actualTxIds);
        self::assertEqualAsSets($expectedTxIds, $actualTxIds);
        $idToSpan = $this->mockEventSink->idToSpan();
        self::assertCount($isSentFromSpan ? 1 : 0, $idToSpan);
        $expectedParentId = $isSentFromSpan ? $senderSpan->getId() : $senderTransaction->getId();
        self::assertSame($expectedParentId, $receiverTransaction->getParentId());
    }

    /**
     * @return iterable<array{bool, bool, bool, string}>
     */
    public function dataProviderForTestManualPassDistTracingDataTracerDisabled(): iterable
    {
        foreach ([false, true] as $isSenderTracerEnabled) {
            foreach ([false, true] as $isReceiverTracerEnabled) {
                foreach ([false, true] as $shouldUseDeprecatedApi) {
                    foreach (self::beginTransactionApis() as $beginTransactionApi) {
                        foreach ([0, 1, 2] as $distDataSource) {
                            yield [
                                $isSenderTracerEnabled,
                                $isReceiverTracerEnabled,
                                $shouldUseDeprecatedApi,
                                $beginTransactionApi,
                                $distDataSource,
                            ];
                        }
                    }
                }
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestManualPassDistTracingDataTracerDisabled
     *
     * @param bool   $isSenderTracerEnabled
     * @param bool   $isReceiverTracerEnabled
     * @param bool   $shouldUseDeprecatedApi
     * @param string $beginTransactionApi
     * @param int    $distDataSource
     */
    public function testManualPassDistTracingDataTracerDisabled(
        bool $isSenderTracerEnabled,
        bool $isReceiverTracerEnabled,
        bool $shouldUseDeprecatedApi,
        string $beginTransactionApi,
        int $distDataSource
    ): void {
        // Arrange

        $senderEventSink = new MockEventSink();
        $senderTracer = self::buildTracerForTests($senderEventSink)->withEnabled($isSenderTracerEnabled)->build();
        $receiverEventSink = new MockEventSink();
        $receiverTracer = self::buildTracerForTests($receiverEventSink)->withEnabled($isReceiverTracerEnabled)->build();

        // Act

        // On the sending side:
        GlobalTracerHolder::setValue($senderTracer);

        if ($distDataSource !== 0) {
            $senderTransaction = ElasticApm::beginCurrentTransaction('POST /web-layer-api', 'web-layer');
            self::assertSame(!$senderTransaction->isNoop(), $isSenderTracerEnabled);
            if ($distDataSource !== 1) {
                $senderSpan = $senderTransaction->beginCurrentSpan('fetch from data layer', 'data-layer');
            }
        }

        // On the sending side: get and serialize DistributedTracingData for the current span/transaction
        if ($shouldUseDeprecatedApi) {
            /** @noinspection PhpDeprecationInspection */
            $senderDistTracingData = ElasticApm::getSerializedCurrentDistributedTracingData();
            self::assertIsString($senderDistTracingData);
        } else {
            $senderDistTracingData = [];
            ElasticApm::getCurrentExecutionSegment()->injectDistributedTracingHeaders(
                function (string $headerName, string $headerValue) use (&$senderDistTracingData): void {
                    $senderDistTracingData[$headerName] = $headerValue;
                }
            );
        }

        // Pass DistributedTracingData to the receivinging side
        $receiverDistTracingData = $senderDistTracingData;

        // On the receivinging side
        GlobalTracerHolder::setValue($receiverTracer);

        // On the receivinging side: begin a new transaction and pass received DistributedTracingData
        $receiverTransaction = self::beginAndEndTransactionUsingApi(
            $beginTransactionApi,
            $shouldUseDeprecatedApi,
            false /* <- shouldReturnHeaderValueAsArray */,
            'GET /data-api' /* <- name */,
            'data-layer' /* <- type */,
            $receiverDistTracingData
        );
        self::assertSame(!$receiverTransaction->isNoop(), $isReceiverTracerEnabled);
        if (isset($senderTransaction) && ($isSenderTracerEnabled === $isReceiverTracerEnabled)) {
            self::assertSame($senderTransaction->getTraceId(), $receiverTransaction->getTraceId());
        }

        $receiverTransaction->end();

        GlobalTracerHolder::setValue($senderTracer);

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
                self::assertEquals(
                    [$senderTransaction->getId()],
                    self::getIdsFromIdToMap($senderEventSink->idToTransaction())
                );
            } else {
                self::assertEmpty($senderEventSink->idToTransaction());
            }

            if (isset($senderSpan)) {
                self::assertEquals([$senderSpan->getId()], self::getIdsFromIdToMap($senderEventSink->idToSpan()));
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
            self::assertEquals(
                [$receiverTransaction->getId()],
                self::getIdsFromIdToMap($receiverEventSink->idToTransaction())
            );
            $receiverTxData = $receiverEventSink->idToTransaction()[$receiverTransaction->getId()];
            self::assertSame($expectedParentId, $receiverTxData->parentId);
        } else {
            self::assertEmpty($receiverEventSink->idToTransaction());
            self::assertEmpty($receiverEventSink->idToSpan());
        }
    }

    public function testManualPassDistTracingDataConcurrentTransactionsAndSpans(): void
    {
        // Arrange

        $senderEventSink = new MockEventSink();
        $senderTracer = self::buildTracerForTests($senderEventSink)->build();
        $receiverEventSink = new MockEventSink();
        $receiverTracer = self::buildTracerForTests($receiverEventSink)->build();

        // Act

        // On the sending side:
        GlobalTracerHolder::setValue($senderTracer);

        $senderTxA = ElasticApm::beginTransaction('POST /web-layer-api-A', 'web-layer');
        self::assertFalse($senderTxA->isNoop());
        self::assertTrue(ElasticApm::getCurrentTransaction()->isNoop());
        $senderTxB = ElasticApm::beginTransaction('POST /web-layer-api-B', 'web-layer');
        self::assertFalse($senderTxB->isNoop());
        self::assertTrue(ElasticApm::getCurrentTransaction()->isNoop());

        $senderSpanB = $senderTxB->beginChildSpan('fetch from data layer B', 'data-layer');
        self::assertFalse($senderSpanB->isNoop());
        self::assertTrue($senderTxB->getCurrentSpan()->isNoop());
        $senderSpanA = $senderTxA->beginChildSpan('fetch from data layer A', 'data-layer');
        self::assertFalse($senderSpanA->isNoop());
        self::assertTrue($senderTxA->getCurrentSpan()->isNoop());

        $distTracingDataA = [];
        $senderSpanA->injectDistributedTracingHeaders(
            function (string $headerName, string $headerValue) use (&$distTracingDataA): void {
                $distTracingDataA[$headerName] = $headerValue;
            }
        );

        $distTracingDataB = [];
        $senderSpanB->injectDistributedTracingHeaders(
            function (string $headerName, string $headerValue) use (&$distTracingDataB): void {
                $distTracingDataB[$headerName] = $headerValue;
            }
        );

        // On the receivinging side
        GlobalTracerHolder::setValue($receiverTracer);

        $headerExtractorB = function (string $headerName) use ($distTracingDataB): ?string {
            return array_key_exists($headerName, $distTracingDataB)
                ? $distTracingDataB[$headerName]
                : null;
        };
        $receiverTxB = ElasticApm::newTransaction('GET /data-api-B', 'data-layer')
                                 ->distributedTracingHeaderExtractor($headerExtractorB)
                                 ->begin();
        self::assertFalse($receiverTxB->isNoop());
        self::assertTrue(ElasticApm::getCurrentTransaction()->isNoop());


        $headerExtractorA = function (string $headerName) use ($distTracingDataA): ?string {
            return array_key_exists($headerName, $distTracingDataA)
                ? $distTracingDataA[$headerName]
                : null;
        };
        $receiverTxA = ElasticApm::newTransaction('GET /data-api-A', 'data-layer')
                                 ->distributedTracingHeaderExtractor($headerExtractorA)
                                 ->begin();
        self::assertFalse($receiverTxA->isNoop());
        self::assertTrue(ElasticApm::getCurrentTransaction()->isNoop());

        $receiverTxB->end();
        $receiverTxA->end();

        GlobalTracerHolder::setValue($senderTracer);

        $senderSpanB->end();
        $senderSpanA->end();
        $senderTxA->end();
        $senderTxB->end();

        // Assert

        self::assertEmpty($this->mockEventSink->idToTransaction());
        self::assertEmpty($this->mockEventSink->idToSpan());

        $expectedSenderTxIds = [$senderTxA->getId(), $senderTxB->getId()];
        self::assertEqualAsSets($expectedSenderTxIds, self::getIdsFromIdToMap($senderEventSink->idToTransaction()));
        $expectedSenderSpanIds = [$senderSpanA->getId(), $senderSpanB->getId()];
        self::assertEqualAsSets($expectedSenderSpanIds, self::getIdsFromIdToMap($senderEventSink->idToSpan()));
        $expectedReceiverTxIds = [$receiverTxA->getId(), $receiverTxB->getId()];
        self::assertEqualAsSets(
            $expectedReceiverTxIds,
            self::getIdsFromIdToMap($receiverEventSink->idToTransaction())
        );

        $senderSpanAData = $senderEventSink->idToSpan()[$senderSpanA->getId()];
        $receiverTxAData = $receiverEventSink->idToTransaction()[$receiverTxA->getId()];
        self::assertSame($senderSpanAData->id, $receiverTxAData->parentId);
        self::assertSame($senderSpanAData->traceId, $receiverTxAData->traceId);

        $senderSpanBData = $senderEventSink->idToSpan()[$senderSpanB->getId()];
        $receiverTxBData = $receiverEventSink->idToTransaction()[$receiverTxB->getId()];
        self::assertSame($senderSpanBData->id, $receiverTxBData->parentId);
        self::assertSame($senderSpanBData->traceId, $receiverTxBData->traceId);
    }

    /**
     * @return iterable<array{bool, bool}>
     */
    public function dataPrividerForTestEnsureParentId(): iterable
    {
        foreach ([false, true] as $isTracerEnabled) {
            foreach ([false, true] as $doesTxHaveParentAlready) {
                yield [
                    $isTracerEnabled,
                    $doesTxHaveParentAlready,
                ];
            }
        }
    }

    /**
     * @dataProvider dataPrividerForTestEnsureParentId
     *
     * @param bool $isTracerEnabled
     * @param bool $doesTxHaveParentAlready
     */
    public function testEnsureParentId(bool $isTracerEnabled, bool $doesTxHaveParentAlready): void
    {
        // Arrange

        $webFrontEventSink = new MockEventSink();
        $webFrontTracer = self::buildTracerForTests($webFrontEventSink)->withEnabled($isTracerEnabled)->build();
        $backendServiceLogSink = new MockLogSink();
        $backendServiceEventSink = new MockEventSink();
        $backendServiceTracer = self::buildTracerForTests($backendServiceEventSink)
                                    ->withEnabled($isTracerEnabled)
                                    ->withLogSink($backendServiceLogSink)
                                    ->withConfig(OptionNames::LOG_LEVEL, 'DEBUG')
                                    ->build();

        // Act

        /** @var ?TransactionInterface */
        $webFrontTx = null;

        /** @var array<string, string> */
        $distTracingData = [];

        if ($doesTxHaveParentAlready) {
            GlobalTracerHolder::setValue($webFrontTracer);
            $webFrontTx = ElasticApm::beginCurrentTransaction('web front end TX', 'web front end TX type');
            ElasticApm::getCurrentExecutionSegment()->injectDistributedTracingHeaders(
                function (string $headerName, string $headerValue) use (&$distTracingData): void {
                    $distTracingData[$headerName] = $headerValue;
                }
            );
        }

        GlobalTracerHolder::setValue($backendServiceTracer);
        $headerExtractor = function (string $headerName) use ($distTracingData): ?string {
            return array_key_exists($headerName, $distTracingData)
                ? $distTracingData[$headerName]
                : null;
        };
        $backendServiceTx = ElasticApm::newTransaction('backend service TX', 'backend service TX type')
                                      ->asCurrent()
                                      ->distributedTracingHeaderExtractor($headerExtractor)
                                      ->begin();
        $parentId = ElasticApm::getCurrentTransaction()->ensureParentId();
        $backendServiceTx->end();

        if ($webFrontTx !== null) {
            GlobalTracerHolder::setValue($webFrontTracer);
            $webFrontTx->end();
        }

        // Assert

        self::assertEmpty($this->mockEventSink->idToTransaction());
        self::assertEmpty($this->mockEventSink->idToSpan());

        self::assertEmpty($webFrontEventSink->idToSpan());
        self::assertEmpty($backendServiceEventSink->idToSpan());

        if (!$isTracerEnabled) {
            self::assertEmpty($webFrontEventSink->idToTransaction());
            self::assertEmpty($backendServiceEventSink->idToTransaction());
            return;
        }

        self::assertCount(1, $backendServiceEventSink->idToTransaction());
        self::assertSame($backendServiceTx->getId(), $backendServiceEventSink->singleTransaction()->id);

        if ($doesTxHaveParentAlready) {
            self::assertCount(1, $webFrontEventSink->idToTransaction());
            self::assertNotNull($webFrontTx);
            self::assertSame($webFrontTx->getId(), $webFrontEventSink->singleTransaction()->id);

            self::assertSame(
                $webFrontEventSink->singleTransaction()->id,
                $backendServiceEventSink->singleTransaction()->parentId
            );
        } else {
            self::assertEmpty($webFrontEventSink->idToTransaction());

            self::assertSame($parentId, $backendServiceEventSink->singleTransaction()->parentId);

            $logStatements = array_filter(
                $backendServiceLogSink->consumed,
                function (MockLogSinkStatement $logStatement): bool {
                    return TextUtil::isSuffixOf('Transaction.php', $logStatement->srcCodeFile)
                           && $logStatement->srcCodeFunc === 'ensureParentId';
                }
            );
            $this->assertCount(1, $logStatements);
            $logStatement = array_values($logStatements)[0];
            $this->assertGreaterThanOrEqual(1, count($logStatement->contextsStack));
            $ctxsWithParentId = array_filter(
                $logStatement->contextsStack,
                /**
                 * @param array<string, mixed> $context
                 */
                function (array $context): bool {
                    return array_key_exists('parentId', $context);
                }
            );
            $this->assertCount(1, $ctxsWithParentId);
            $ctxWithParentId = array_values($ctxsWithParentId)[0];
            $this->assertSame($parentId, $ctxWithParentId['parentId']);
        }
    }
}
