<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Ds\Queue;
use Ds\Set;
use Elastic\Apm\ExecutionSegmentDataInterface;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\ComponentTests\Util\TempDisableFailingAssertions;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\Constraint\Exception as ConstraintException;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Throwable;

class TestCaseBase extends TestCase
{
    // Compare up to 10 milliseconds (10000 microseconds) precision
    public const TIMESTAMP_COMPARISON_PRECISION = 10000;

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
        self::assertEqualsWithDelta($expected, $actual, self::TIMESTAMP_COMPARISON_PRECISION);
    }

    public static function assertLessThanOrEqualTimestamp(float $lhs, float $rhs): void
    {
        self::assertThat(
            $lhs,
            self::logicalOr(
                new IsEqual($rhs, /* delta: */ self::TIMESTAMP_COMPARISON_PRECISION),
                new LessThan($rhs)
            ),
            ' $lhs: ' . number_format($lhs) . ', $rhs: ' . number_format($rhs)
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

        if (TempDisableFailingAssertions::$shouldDisableFailingAssertions) {
            TempDisableFailingAssertions::checkDisableFailedAssertion(
                __FILE__,
                __LINE__,
                $transaction->getStartedSpansCount() === count($idToSpan),
                '$transaction->getStartedSpansCount() === count($idToSpan)',
                '$transaction->getStartedSpansCount(): ' . $transaction->getStartedSpansCount() . '.'
                . ' count($idToSpan): ' . count($idToSpan) . '.'
            );
        } else {
            self::assertCount($transaction->getStartedSpansCount(), $idToSpan);
        }

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

        if (TempDisableFailingAssertions::$shouldDisableFailingAssertions) {
            TempDisableFailingAssertions::checkDisableFailedAssertion(
                __FILE__,
                __LINE__,
                $idsReachableFromRoot->count() === count($idToParentId),
                '$idsReachableFromRoot->count() === count($idToParentId)',
                '$idsReachableFromRoot->count(): ' . $idsReachableFromRoot->count() . '.'
                . ' count($idToParentId): ' . count($idToParentId) . '.'
            );
        } else {
            self::assertCount($idsReachableFromRoot->count(), $idToParentId);
        }
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
     *
     * @return TransactionDataInterface
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
        self::assertNotNull(
            $rootTransaction,
            'Root transaction not found.'
            . ' idToTransaction: ' . DbgUtil::formatValue($idToTransaction)
        );
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

    /**
     * Asserts that the callable throws a specified throwable.
     * If successful and the inspection callable is not null
     * then it is called and the caught exception is passed as argument.
     *
     * @param string        $class   The exception type expected to be thrown.
     * @param callable      $execute The callable.
     * @param string        $message
     * @param callable|null $inspect [optional] The inspector.
     */
    public static function assertThrows(
        string $class,
        callable $execute,
        string $message = '',
        callable $inspect = null
    ): void {
        try {
            $execute();
        } catch (ExpectationFailedException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            static::assertThat($ex, new ConstraintException($class), $message);

            if ($inspect !== null) {
                $inspect($ex);
            }

            return;
        }

        static::assertThat(null, new ConstraintException($class), $message);
    }
}
