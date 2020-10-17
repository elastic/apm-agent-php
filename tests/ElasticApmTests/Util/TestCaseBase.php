<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Ds\Queue;
use Ds\Set;
use Elastic\Apm\ExecutionSegmentDataInterface;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;
use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase;

class TestCaseBase extends TestCase
{
    // Adds the assertThrows method
    use AssertThrows;

    /**
     * @param mixed        $name
     * @param array<mixed> $data
     * @param mixed        $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    public static function assertEqualTimestamp(float $expected, float $actual): void
    {
        self::assertEqualsWithDelta($expected, $actual, 1);
    }

    public static function assertLessThanOrEqualTimestamp(float $lhs, float $rhs): void
    {

        self::assertThat(
            $lhs,
            self::logicalOr(new IsEqual($rhs, /* delta: */ 1), new LessThan($rhs)),
            "lhs: $lhs, rhs: $rhs" .
            ', number_format($lhs): ' . number_format($lhs) . ', number_format($rhs): ' . number_format($rhs) .
            ((PHP_INT_SIZE >= 8) ? (', intval($lhs): ' . intval($lhs) . ', intval($rhs): ' . intval($rhs)) : '')
        );
    }

    public static function assertLessThanOrEqualDuration(float $lhs, float $rhs): void
    {
        self::assertThat($lhs, self::logicalOr(new IsEqual($rhs, /* delta: */ 1), new LessThan($rhs)), '');
    }

    public static function calcEndTime(ExecutionSegmentDataInterface $timedEvent): float
    {
        return $timedEvent->getTimestamp() + TimeUtil::millisecondsToMicroseconds($timedEvent->getDuration());
    }

    /**
     * @param mixed $timestamp
     * @param mixed $outerTimedEvent
     */
    public static function assertTimestampNested($timestamp, $outerTimedEvent): void
    {
        self::assertLessThanOrEqualTimestamp($outerTimedEvent->getTimestamp(), $timestamp);
        self::assertLessThanOrEqualTimestamp($timestamp, self::calcEndTime($outerTimedEvent));
    }

    /**
     * @param mixed $nestedTimedEvent
     * @param mixed $outerTimedEvent
     */
    public static function assertTimedEventIsNested($nestedTimedEvent, $outerTimedEvent): void
    {
        self::assertTimestampNested($nestedTimedEvent->getTimestamp(), $outerTimedEvent);
        self::assertTimestampNested(self::calcEndTime($nestedTimedEvent), $outerTimedEvent);
    }

    /**
     * @param TransactionDataInterface         $transaction
     * @param array<string, SpanDataInterface> $idToSpan
     */
    public static function assertValidTransactionAndItsSpans(
        TransactionDataInterface $transaction,
        array $idToSpan
    ): void {
        ValidationUtil::assertValidTransactionData($transaction);

        /** @var SpanDataInterface $span */
        foreach ($idToSpan as $span) {
            ValidationUtil::assertValidSpanData($span);
            self::assertSame($transaction->getId(), $span->getTransactionId());
            self::assertSame($transaction->getTraceId(), $span->getTraceId());

            if ($span->getParentId() === $transaction->getId()) {
                self::assertTimedEventIsNested($span, $transaction);
            } else {
                self::assertArrayHasKey($span->getParentId(), $idToSpan, 'count($idToSpan): ' . count($idToSpan));
                self::assertTimedEventIsNested($span, $idToSpan[$span->getParentId()]);
            }

            self::assertLessThanOrEqualDuration($span->getStart() + $span->getDuration(), $transaction->getDuration());
            self::assertEqualTimestamp(
                $transaction->getTimestamp() + TimeUtil::millisecondsToMicroseconds((float)($span->getStart())),
                $span->getTimestamp()
            );
        }

        self::assertCount($transaction->getStartedSpansCount(), $idToSpan);

        $spanIdToParentId = [];
        foreach ($idToSpan as $id => $span) {
            $spanIdToParentId[$id] = $span->getParentId();
        }
        self::assertGraphIsTree($transaction->getId(), $spanIdToParentId);
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
     * @param array<string, SpanDataInterface> $idToSpan
     *
     * @return array<string, array<string, SpanDataInterface>>
     */
    public static function groupSpansByTransactionId(array $idToSpan): array
    {
        /** @var array<string, array<string, SpanDataInterface>> */
        $transactionIdToSpans = [];

        /** @var SpanDataInterface $span */
        foreach ($idToSpan as $spanId => $span) {
            if (!array_key_exists($span->getTransactionId(), $transactionIdToSpans)) {
                $transactionIdToSpans[$span->getTransactionId()] = [];
            }
            $transactionIdToSpans[$span->getTransactionId()][$spanId] = $span;
        }

        return $transactionIdToSpans;
    }

    /**
     * @param array<string, TransactionDataInterface> $idToTransaction
     */
    public static function findRootTransaction(array $idToTransaction): TransactionDataInterface
    {
        /** @var TransactionDataInterface|null */
        $rootTransaction = null;
        foreach ($idToTransaction as $transactionId => $transaction) {
            if (is_null($transaction->getParentId())) {
                self::assertNull($rootTransaction, 'Found more than one root transaction');
                $rootTransaction = $transaction;
            }
        }
        self::assertNotNull($rootTransaction, 'Root transaction not found');
        return $rootTransaction;
    }

    /**
     * @param array<string, TransactionDataInterface> $idToTransaction
     * @param array<string, SpanDataInterface>        $idToSpan
     */
    private static function assertTransactionsGraphIsTree(array $idToTransaction, array $idToSpan): void
    {
        $rootTransaction = self::findRootTransaction($idToTransaction);
        /** @var array<string, string> */
        $transactionIdToParentId = [];
        foreach ($idToTransaction as $transactionId => $transaction) {
            if (is_null($transaction->getParentId())) {
                continue;
            }
            $parentSpan = ArrayUtil::getValueIfKeyExistsElse($transaction->getParentId(), $idToSpan, null);
            if (is_null($parentSpan)) {
                self::assertArrayHasKey($transaction->getParentId(), $idToTransaction);
                $transactionIdToParentId[$transactionId] = $transaction->getParentId();
            } else {
                $transactionIdToParentId[$transactionId] = $parentSpan->getTransactionId();
            }
        }
        self::assertNotNull($rootTransaction);
        self::assertGraphIsTree($rootTransaction->getId(), $transactionIdToParentId);
    }

    /**
     * @param array<string, TransactionDataInterface> $idToTransaction
     * @param array<string, SpanDataInterface>        $idToSpan
     */
    public static function assertValidTransactionsAndSpans(array $idToTransaction, array $idToSpan): void
    {
        self::assertTransactionsGraphIsTree($idToTransaction, $idToSpan);

        $rootTransaction = self::findRootTransaction($idToTransaction);

        // Assert that all transactions have the same traceId
        foreach ($idToTransaction as $transactionId => $transaction) {
            self::assertSame($rootTransaction->getTraceId(), $transaction->getTraceId());
        }

        // Assert that each transaction did not start before its parent
        foreach ($idToTransaction as $transactionId => $transaction) {
            if (is_null($transaction->getParentId())) {
                continue;
            }
            /** @var ExecutionSegmentDataInterface|null $parentExecSegment */
            $parentExecSegment = ArrayUtil::getValueIfKeyExistsElse($transaction->getParentId(), $idToSpan, null);
            if (is_null($parentExecSegment)) {
                self::assertTrue(
                    ArrayUtil::getValueIfKeyExists(
                        $transaction->getParentId(),
                        $idToTransaction,
                        /* ref */ $parentExecSegment
                    )
                );
            }
            self::assertNotNull($parentExecSegment);
            self::assertLessThanOrEqualTimestamp($parentExecSegment->getTimestamp(), $transaction->getTimestamp());
        }

        // Assert that all spans have the same traceId
        foreach ($idToSpan as $spanId => $span) {
            self::assertSame($rootTransaction->getTraceId(), $span->getTraceId());
        }

        // Assert that every span's transactionId is present
        foreach ($idToSpan as $spanId => $span) {
            self::assertArrayHasKey($span->getTransactionId(), $idToTransaction);
        }

        // Group spans by transaction and verify every group
        foreach ($idToTransaction as $transactionId => $transaction) {
            $idToSpanOnlyCurrentTransaction = [];
            foreach ($idToSpan as $spanId => $span) {
                if ($span->getTransactionId() === $transactionId) {
                    $idToSpanOnlyCurrentTransaction[$spanId] = $span;
                }
            }
            self::assertValidTransactionAndItsSpans($transaction, $idToSpanOnlyCurrentTransaction);
        }
    }

    public static function assertTransactionEquals(
        TransactionDataInterface $expected,
        TransactionDataInterface $actual
    ): void {
        self::assertEquals(TransactionData::convertToData($expected), TransactionData::convertToData($actual));
    }

    public static function assertSpanEquals(
        SpanDataInterface $expected,
        SpanDataInterface $actual
    ): void {
        self::assertEquals(SpanData::convertToData($expected), SpanData::convertToData($actual));
    }
}
