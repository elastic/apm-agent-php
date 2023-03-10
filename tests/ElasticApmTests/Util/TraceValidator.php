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

namespace ElasticApmTests\Util;

use Ds\Queue;
use Ds\Set;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use Elastic\Apm\Impl\Util\UrlUtil;
use PHPUnit\Framework\TestCase;

final class TraceValidator
{
    use AssertValidTrait;

    /** @var TraceExpectations */
    protected $expectations;

    /** @var TraceActual */
    protected $actual;

    /** @var bool */
    protected $forceEnableFlakyAssertions;

    private function __construct(
        TraceExpectations $expectations,
        TraceActual $actual,
        bool $forceEnableFlakyAssertions
    ) {
        $this->expectations = $expectations;
        $this->actual = $actual;
        $this->forceEnableFlakyAssertions = $forceEnableFlakyAssertions;
    }

    protected function validateImpl(): void
    {
        $idToTransaction = $this->actual->idToTransaction;
        $idToSpan = $this->actual->idToSpan;
        $rootTransaction = $this->actual->rootTransaction;
        if ($this->expectations->shouldVerifyRootTransaction) {
            $this->validateRootTransaction($rootTransaction);
        }

        // Assert that all transactions have the same traceId
        foreach ($idToTransaction as $transaction) {
            TestCase::assertSame($rootTransaction->traceId, $transaction->traceId);
        }
        // Assert that all spans have the same traceId
        foreach ($idToSpan as $span) {
            TestCase::assertSame($rootTransaction->traceId, $span->traceId);
        }

        // Assert that transactions and spans don't have any shared IDs
        TestCase::assertEmpty(array_intersect(array_keys($idToTransaction), array_keys($idToSpan)));

        self::assertTransactionsGraphIsTree($rootTransaction);

        // Assert that each transaction did not start before its parent
        foreach ($idToTransaction as $transaction) {
            if ($transaction->parentId === null) {
                continue;
            }
            $parentExecutionSegment
                = DataFromAgent::executionSegmentByIdEx($idToTransaction, $idToSpan, $transaction->parentId);
            TestCaseBase::assertLessThanOrEqualTimestamp($parentExecutionSegment->timestamp, $transaction->timestamp);
        }

        // Assert that every span's transactionId is present
        foreach ($idToSpan as $span) {
            TestCase::assertArrayHasKey($span->transactionId, $idToTransaction);
        }

        // Group spans by transaction and verify each group
        foreach ($idToTransaction as $transactionId => $transaction) {
            $idToSpanOnlyCurrentTransaction = [];
            foreach ($idToSpan as $spanId => $span) {
                if ($span->transactionId === $transactionId) {
                    $idToSpanOnlyCurrentTransaction[$spanId] = $span;
                }
            }
            $this->validateTransactionAndItsSpans(
                $transaction,
                $idToSpanOnlyCurrentTransaction,
                $this->forceEnableFlakyAssertions
            );
        }
    }

    public static function validate(
        TraceActual $actual,
        ?TraceExpectations $expectations = null,
        bool $forceEnableFlakyAssertions = false
    ): void {
        (new self($expectations ?? new TraceExpectations(), $actual, $forceEnableFlakyAssertions))->validateImpl();
    }

    /**
     * @param TransactionDto         $transaction
     * @param array<string, SpanDto> $idToSpan
     * @param bool                   $forceEnableFlakyAssertions
     */
    private function validateTransactionAndItsSpans(
        TransactionDto $transaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        $transaction->assertMatches($this->expectations->transaction);

        foreach ($idToSpan as $span) {
            $span->assertMatches($this->expectations->span);
            TestCase::assertSame($transaction->id, $span->transactionId);
            TestCase::assertSame($transaction->traceId, $span->traceId);
            TestCase::assertSame($transaction->sampleRate, $span->sampleRate);

            if ($span->parentId === $transaction->id) {
                ExecutionSegmentDto::assertTimeNested($span, $transaction);
            } else {
                TestCase::assertArrayHasKey($span->parentId, $idToSpan, 'count($idToSpan): ' . count($idToSpan));
                ExecutionSegmentDto::assertTimeNested($span, $idToSpan[$span->parentId]);
            }
        }

        FlakyAssertions::run(
            function () use ($transaction, $idToSpan): void {
                self::validateTransactionAndItsSpansFlakyPart($transaction, $idToSpan);
            },
            $forceEnableFlakyAssertions
        );
    }

    /**
     * @param TransactionDto         $transaction
     * @param array<string, SpanDto> $idToSpan
     */
    private static function validateTransactionAndItsSpansFlakyPart(
        TransactionDto $transaction,
        array $idToSpan
    ): void {
        TestCase::assertCount($transaction->startedSpansCount, $idToSpan);

        $spanIdToParentId = [];
        foreach ($idToSpan as $id => $span) {
            $spanIdToParentId[$id] = $span->parentId;
        }
        self::assertGraphIsTree($transaction->id, $spanIdToParentId);
    }

    /**
     * @param string                $rootId
     * @param array<string, string> $idToParentId
     */
    private static function assertGraphIsTree(string $rootId, array $idToParentId): void
    {
        $idsReachableFromRoot = new Set();

        $reachableToProcess = new Queue([$rootId]);

        while (!$reachableToProcess->isEmpty()) {
            $currentParentId = $reachableToProcess->pop();
            foreach ($idToParentId as $id => $parentId) {
                if ($currentParentId === $parentId) {
                    TestCase::assertTrue(!$idsReachableFromRoot->contains($id));
                    $idsReachableFromRoot->add($id);
                    $reachableToProcess->push($id);
                }
            }
        }

        TestCase::assertCount($idsReachableFromRoot->count(), $idToParentId);
    }

    private function assertTransactionsGraphIsTree(TransactionDto $rootTransaction): void
    {
        /** @var array<string, string> $transactionIdToParentId */
        $transactionIdToParentId = [];
        foreach ($this->actual->idToTransaction as $transactionId => $transaction) {
            if ($transaction->parentId === null) {
                continue;
            }
            $parentSpan = ArrayUtil::getValueIfKeyExistsElse($transaction->parentId, $this->actual->idToSpan, null);
            if ($parentSpan === null) {
                TestCase::assertArrayHasKey($transaction->parentId, $this->actual->idToTransaction);
                $transactionIdToParentId[$transactionId] = $transaction->parentId;
            } else {
                $transactionIdToParentId[$transactionId] = $parentSpan->transactionId;
            }
        }
        TestCase::assertNotNull($rootTransaction);
        self::assertGraphIsTree($rootTransaction->id, $transactionIdToParentId);
    }

    private function validateRootTransaction(TransactionDto $rootTx): void
    {
        if ($this->expectations->rootTransactionName !== null) {
            TestCase::assertSame($this->expectations->rootTransactionName, $rootTx->name);
        }
        if ($this->expectations->rootTransactionType !== null) {
            TestCase::assertSame($this->expectations->rootTransactionType, $rootTx->type);
        }

        if ($rootTx->context !== null) {
            $this->validateRootTransactionContext($rootTx->context);
        }
    }

    private function validateRootTransactionContext(TransactionContextDto $rootTxCtx): void
    {
        $rootTxCtxReq = $rootTxCtx->request;
        if ($this->expectations->isRootTransactionHttp) {
            TestCase::assertNotNull($rootTxCtxReq);
            $this->validateRootTransactionContextRequest($rootTxCtxReq);
        } else {
            TestCase::assertNull($rootTxCtxReq);
        }
    }

    private function validateRootTransactionContextRequest(TransactionContextRequestDto $rootTxCtxReq): void
    {
        /**
         * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L101
         * "required": ["url", "method"]
         */
        TestCase::assertNotNull($rootTxCtxReq->url);
        TestCase::assertNotNull($rootTxCtxReq->method);

        $expectedUrlParts = $this->expectations->rootTransactionUrlParts;
        if ($expectedUrlParts !== null) {
            $this->validateRootTransactionContextRequestUrl($expectedUrlParts, $rootTxCtxReq->url);
        }

        if ($this->expectations->rootTransactionHttpRequestMethod !== null) {
            TestCase::assertSame($this->expectations->rootTransactionHttpRequestMethod, $rootTxCtxReq->method);
        }
    }

    protected function validateRootTransactionContextRequestUrl(
        UrlParts $expectedUrlParts,
        TransactionContextRequestUrlDto $rootTxCtxReqUrl
    ): void {
        $expectedFullUrl = UrlUtil::buildFullUrl($expectedUrlParts);
        TestCase::assertSame($expectedFullUrl, $rootTxCtxReqUrl->full);
        TestCase::assertSame($expectedFullUrl, $rootTxCtxReqUrl->original);
        TestCase::assertSame($expectedUrlParts->scheme, $rootTxCtxReqUrl->protocol);
        TestCase::assertSame($expectedUrlParts->host, $rootTxCtxReqUrl->domain);
        TestCase::assertSame($expectedUrlParts->port, $rootTxCtxReqUrl->port);
        TestCase::assertSame($expectedUrlParts->path, $rootTxCtxReqUrl->path);
        TestCase::assertSame($expectedUrlParts->query, $rootTxCtxReqUrl->query);
    }

    /**
     * @param mixed $traceId
     *
     * @return string
     */
    public static function assertValidId($traceId): string
    {
        return self::assertValidIdEx($traceId, Constants::TRACE_ID_SIZE_IN_BYTES);
    }
}
