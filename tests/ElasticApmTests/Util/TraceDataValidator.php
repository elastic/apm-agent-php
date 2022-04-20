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
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\FlakyAssertions;

final class TraceDataValidator extends EventDataValidator
{
    /** @var TraceDataExpected */
    protected $expected;

    /** @var TraceDataActual */
    protected $actual;

    /** @var bool */
    protected $forceEnableFlakyAssertions;

    private function __construct(
        TraceDataExpected $expected,
        TraceDataActual $actual,
        bool $forceEnableFlakyAssertions
    ) {
        $this->expected = $expected;
        $this->actual = $actual;
        $this->forceEnableFlakyAssertions = $forceEnableFlakyAssertions;
    }

    protected function validateImpl(): void
    {
        $rootTransaction = self::findRootTransaction($this->actual->idToTransaction);

        // Assert that all transactions have the same traceId
        foreach ($this->actual->idToTransaction as $transaction) {
            self::assertSame($rootTransaction->traceId, $transaction->traceId);
        }

        self::assertTransactionsGraphIsTree($rootTransaction);

        // Assert that each transaction did not start before its parent
        foreach ($this->actual->idToTransaction as $transaction) {
            if (is_null($transaction->parentId)) {
                continue;
            }
            /** @var ExecutionSegmentData|null $parentExecSegment */
            $parentExecSegment
                = ArrayUtil::getValueIfKeyExistsElse($transaction->parentId, $this->actual->idToSpan, null);
            if (is_null($parentExecSegment)) {
                self::assertTrue(
                    ArrayUtil::getValueIfKeyExists(
                        $transaction->parentId,
                        $this->actual->idToTransaction,
                        /* ref */ $parentExecSegment
                    )
                );
            }
            self::assertNotNull($parentExecSegment);
            self::assertLessThanOrEqualTimestamp($parentExecSegment->timestamp, $transaction->timestamp);
        }

        // Assert that all spans have the same traceId
        foreach ($this->actual->idToSpan as $span) {
            self::assertSame($rootTransaction->traceId, $span->traceId);
        }

        // Assert that every span's transactionId is present
        /** @var SpanData $span */
        foreach ($this->actual->idToSpan as $span) {
            self::assertArrayHasKey($span->transactionId, $this->actual->idToTransaction);
        }

        // Group spans by transaction and verify each group
        foreach ($this->actual->idToTransaction as $transactionId => $transaction) {
            $idToSpanOnlyCurrentTransaction = [];
            foreach ($this->actual->idToSpan as $spanId => $span) {
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
        TraceDataActual $actual,
        ?TraceDataExpected $expected = null,
        bool $forceEnableFlakyAssertions = false
    ): void {
        (new self($expected ?? new TraceDataExpected(), $actual, $forceEnableFlakyAssertions))->validateImpl();
    }

    /**
     * @param TransactionData         $transaction
     * @param array<string, SpanData> $idToSpan
     * @param bool                    $forceEnableFlakyAssertions
     */
    private function validateTransactionAndItsSpans(
        TransactionData $transaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        TransactionDataValidator::validate($transaction, $this->expected->transaction);

        /** @var SpanData $span */
        foreach ($idToSpan as $span) {
            ValidationUtil::assertValidSpanData($span);
            self::assertSame($transaction->id, $span->transactionId);
            self::assertSame($transaction->traceId, $span->traceId);

            if ($span->parentId === $transaction->id) {
                ExecutionSegmentDataValidator::assertNested($span, $transaction);
            } else {
                self::assertArrayHasKey($span->parentId, $idToSpan, 'count($idToSpan): ' . count($idToSpan));
                ExecutionSegmentDataValidator::assertNested($span, $idToSpan[$span->parentId]);
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
     * @param TransactionData         $transaction
     * @param array<string, SpanData> $idToSpan
     */
    private static function validateTransactionAndItsSpansFlakyPart(
        TransactionData $transaction,
        array $idToSpan
    ): void {
        self::assertCount($transaction->startedSpansCount, $idToSpan);

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
        /** @var Set<string> */
        $idsReachableFromRoot = new Set();

        /** @var Queue<string> */
        $reachableToProcess = new Queue([$rootId]);

        while (!$reachableToProcess->isEmpty()) {
            $currentParentId = $reachableToProcess->pop();
            foreach ($idToParentId as $id => $parentId) {
                if ($currentParentId === $parentId) {
                    self::assertTrue(!$idsReachableFromRoot->contains($id));
                    $idsReachableFromRoot->add($id);
                    $reachableToProcess->push($id);
                }
            }
        }

        self::assertCount($idsReachableFromRoot->count(), $idToParentId);
    }

    /**
     * @param array<string, TransactionData> $idToTransaction
     *
     * @return TransactionData
     */
    public static function findRootTransaction(array $idToTransaction): TransactionData
    {
        /** @var ?TransactionData */
        $rootTransaction = null;
        foreach ($idToTransaction as $currentTransaction) {
            if ($currentTransaction->parentId === null) {
                self::assertNull($rootTransaction, 'Found more than one root transaction');
                $rootTransaction = $currentTransaction;
            }
        }
        self::assertNotNull(
            $rootTransaction,
            'Root transaction not found. ' . LoggableToString::convert(['idToTransaction' => $idToTransaction])
        );
        /** @var TransactionData $rootTransaction */
        return $rootTransaction;
    }

    private function assertTransactionsGraphIsTree(TransactionData $rootTransaction): void
    {
        /** @var array<string, string> */
        $transactionIdToParentId = [];
        foreach ($this->actual->idToTransaction as $transactionId => $transaction) {
            if ($transaction->parentId === null) {
                continue;
            }
            $parentSpan = ArrayUtil::getValueIfKeyExistsElse($transaction->parentId, $this->actual->idToSpan, null);
            if ($parentSpan === null) {
                self::assertArrayHasKey($transaction->parentId, $this->actual->idToTransaction);
                $transactionIdToParentId[$transactionId] = $transaction->parentId;
            } else {
                $transactionIdToParentId[$transactionId] = $parentSpan->transactionId;
            }
        }
        self::assertNotNull($rootTransaction);
        self::assertGraphIsTree($rootTransaction->id, $transactionIdToParentId);
    }
}
