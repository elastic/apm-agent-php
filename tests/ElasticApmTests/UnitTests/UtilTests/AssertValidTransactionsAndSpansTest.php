<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Closure;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\Tests\UnitTests\Util\MockSpan;
use Elastic\Apm\Tests\UnitTests\Util\MockTransaction;
use Elastic\Apm\Tests\Util\InvalidEventDataException;
use Elastic\Apm\Tests\Util\TestCaseBase;
use Elastic\Apm\TransactionInterface;
use PHPUnit\Exception as PhpUnitException;
use Throwable;

class AssertValidTransactionsAndSpansTest extends TestCaseBase
{
    /**
     * @param TransactionInterface[] $transactions
     * @param SpanInterface[]        $spans
     * @param callable                   $corruptFunc
     *
     * @phpstan-param callable(): callable  $corruptFunc
     */
    private function assertValidAndCorrupted(
        array $transactions,
        array $spans,
        callable $corruptFunc
    ): void {
        $idToTransaction = self::idToEvent($transactions);
        $idToSpan = self::idToEvent($spans);
        self::assertValidTransactionsAndSpans($idToTransaction, $idToSpan);
        /** @var callable(): void */
        $revertCorruptFunc = $corruptFunc();
        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertInvalidTransactionsAndSpans($idToTransaction, $idToSpan);
        $revertCorruptFunc();
        self::assertValidTransactionsAndSpans($idToTransaction, $idToSpan);
    }

    /**
     * @param array<string, TransactionInterface> $idToTransaction
     * @param array<string, SpanInterface>        $idToSpan
     */
    private static function assertInvalidTransactionsAndSpans(array $idToTransaction, array $idToSpan): void
    {
        try {
            self::assertValidTransactionsAndSpans($idToTransaction, $idToSpan);
        } catch (Throwable $throwable) {
            if ($throwable instanceof PhpUnitException || $throwable instanceof InvalidEventDataException) {
                return;
            }
            /** @noinspection PhpUnhandledExceptionInspection */
            throw $throwable;
        }
        self::fail('Expected an exception but none was actually thrown');
    }

    /**
     * @param ExecutionSegmentInterface[] $events
     *
     * @return array<string, ExecutionSegmentInterface>
     *
     * @template        T of ExecutionSegmentInterface
     * @phpstan-param   T[] $events
     * @phpstan-return  array<string, T>
     *
     */
    private static function idToEvent(array $events): array
    {
        $result = [];
        foreach ($events as $event) {
            self::assertArrayNotHasKey($event->getId(), $result);
            $result[$event->getId()] = $event;
        }
        return $result;
    }

    /**
     * @param mixed       $executionSegment
     * @param string|null $newParentId
     *
     * @return Closure
     *
     * @phpstan-return Closure(): Closure(): void
     */
    private static function makeCorruptParentIdFunc($executionSegment, ?string $newParentId = null): Closure
    {
        return function () use ($executionSegment, $newParentId): Closure {
            $oldParentId = $executionSegment->getParentId();
            $executionSegment->setParentId(
                $newParentId ?? IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)
            );
            return function () use ($executionSegment, $oldParentId): void {
                $executionSegment->setParentId($oldParentId);
            };
        };
    }

    /**
     * @param MockSpan $span
     * @param string   $newTransactionId
     *
     * @return Closure
     *
     * @phpstan-return Closure(): Closure(): void
     */
    private static function makeCorruptTransactionIdFunc(MockSpan $span, string $newTransactionId = null): Closure
    {
        return function () use ($span, $newTransactionId): Closure {
            $oldTransactionId = $span->getTransactionId();
            $span->setTransactionId(
                $newTransactionId ?? IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)
            );
            return function () use ($span, $oldTransactionId): void {
                $span->setTransactionId($oldTransactionId);
            };
        };
    }

    public function testOneSpanNotReachableFromRoot(): void
    {
        $span = new MockSpan();
        $tx = new MockTransaction([$span]);

        $this->assertValidAndCorrupted([$tx], [$span], self::makeCorruptParentIdFunc($span));
    }

    public function testTwoSpansNotReachableFromRoot(): void
    {
        $span_1_1 = new MockSpan();
        $span_1 = new MockSpan([$span_1_1]);
        $tx = new MockTransaction([$span_1]);

        $this->assertValidAndCorrupted([$tx], [$span_1, $span_1_1], self::makeCorruptParentIdFunc($span_1));
        $this->assertValidAndCorrupted([$tx], [$span_1, $span_1_1], self::makeCorruptParentIdFunc($span_1_1));
    }

    public function testSpanParentCycle(): void
    {
        $span_1_1 = new MockSpan();
        $span_1 = new MockSpan([$span_1_1]);
        $tx = new MockTransaction([$span_1]);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            self::makeCorruptParentIdFunc($span_1, $span_1_1->getId())
        );
    }

    public function testTransactionNotReachableFromRoot(): void
    {
        $tx_C = new MockTransaction();
        $tx_B = new MockTransaction([], [$tx_C]);
        $tx_A = new MockTransaction([], [$tx_B]);

        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_B));
        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_C));
        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_B, $tx_C->getId()));
    }

    public function testTransactionWithSpansNotReachableFromRoot(): void
    {
        $span_I = new MockSpan();
        $span_H = new MockSpan([$span_I]);
        $tx_G = new MockTransaction([$span_H]);
        $span_F = new MockSpan([], [$tx_G]);
        $span_E = new MockSpan([$span_F]);
        $tx_D = new MockTransaction([$span_E]);
        $span_C = new MockSpan([], [$tx_D]);
        $span_B = new MockSpan([$span_C]);
        $tx_A = new MockTransaction([$span_B]);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_D, $tx_G],
            [$span_B, $span_C, $span_E, $span_F, $span_H, $span_I],
            self::makeCorruptParentIdFunc($tx_G)
        );

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_D, $tx_G],
            [$span_B, $span_C, $span_E, $span_F, $span_H, $span_I],
            self::makeCorruptParentIdFunc($tx_D)
        );

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_D, $tx_G],
            [$span_B, $span_C, $span_E, $span_F, $span_H, $span_I],
            self::makeCorruptParentIdFunc($tx_D, $span_I->getId())
        );

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_D, $tx_G],
            [$span_B, $span_C, $span_E, $span_F, $span_H, $span_I],
            self::makeCorruptParentIdFunc($tx_D, $span_H->getId())
        );
    }

    public function testNoRootTransaction(): void
    {
        $tx_C = new MockTransaction();
        $tx_B = new MockTransaction([], [$tx_C]);
        $tx_A = new MockTransaction([], [$tx_B]);

        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_A));
        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_A, $tx_C->getId()));
    }

    public function testMoreThanOneRootTransaction(): void
    {
        $tx_B = new MockTransaction();
        $tx_A = new MockTransaction([], [$tx_B]);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_B],
            [],
            function () use ($tx_A, $tx_B): Closure {
                $tx_B->setParentId(null);
                return function () use ($tx_A, $tx_B): void {
                    $tx_B->setParentId($tx_A->getId());
                };
            }
        );
    }

    public function testSpanWithoutTransaction(): void
    {
        $span = new MockSpan();
        $tx = new MockTransaction([$span]);

        $this->assertValidAndCorrupted([$tx], [$span], self::makeCorruptTransactionIdFunc($span));
    }

    /**
     * @param mixed $executionSegment
     * @param float $paddingInMicroseconds
     */
    private static function padSegmentTime($executionSegment, float $paddingInMicroseconds): void
    {
        $executionSegment->setTimestamp($executionSegment->getTimestamp() - $paddingInMicroseconds);
        $executionSegment->setDuration(
            $executionSegment->getDuration() + TimeUtil::microsecondsToMilliseconds($paddingInMicroseconds)
        );
    }

    public function testSpanStartedBeforeParent(): void
    {
        $span_1_1 = new MockSpan();
        self::padSegmentTime($span_1_1, /* microseconds */ 3);
        $span_1 = new MockSpan([$span_1_1]);
        self::padSegmentTime($span_1, /* microseconds */ 3);
        $tx = new MockTransaction([$span_1]);
        self::padSegmentTime($tx, /* microseconds */ 3);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($span_1, $span_1_1): Closure {
                $delta = $span_1_1->getTimestamp() - $span_1->getTimestamp() + 2;
                self::assertGreaterThan(0, $delta);
                $span_1_1->setTimestamp($span_1_1->getTimestamp() - $delta);
                $span_1_1->setDuration($span_1_1->getDuration() + $delta);
                $span_1_1->setStart($span_1_1->getStart() - $delta);
                return function () use ($span_1_1, $delta): void {
                    $span_1_1->setTimestamp($span_1_1->getTimestamp() + $delta);
                    $span_1_1->setDuration($span_1_1->getDuration() - $delta);
                    $span_1_1->setStart($span_1_1->getStart() + $delta);
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($tx, $span_1): Closure {
                $delta = $span_1->getTimestamp() - $tx->getTimestamp() + 2;
                self::assertGreaterThan(0, $delta);
                $span_1->setTimestamp($span_1->getTimestamp() - $delta);
                $span_1->setDuration($span_1->getDuration() + $delta);
                $span_1->setStart($span_1->getStart() - $delta);
                return function () use ($span_1, $delta): void {
                    $span_1->setTimestamp($span_1->getTimestamp() + $delta);
                    $span_1->setDuration($span_1->getDuration() - $delta);
                    $span_1->setStart($span_1->getStart() + $delta);
                };
            }
        );
    }

    public function testSpanEndedAfterParent(): void
    {
        $span_1_1 = new MockSpan();
        self::padSegmentTime($span_1_1, /* microseconds */ 3);
        $span_1 = new MockSpan([$span_1_1]);
        self::padSegmentTime($span_1, /* microseconds */ 3);
        $tx = new MockTransaction([$span_1]);
        self::padSegmentTime($tx, /* microseconds */ 3);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($span_1, $span_1_1): Closure {
                $delta = TestCaseBase::calcEndTime($span_1) - TestCaseBase::calcEndTime($span_1_1) + 2;
                self::assertGreaterThan(0, $delta);
                $span_1_1->setDuration($span_1_1->getDuration() + $delta);
                return function () use ($span_1_1, $delta): void {
                    $span_1_1->setDuration($span_1_1->getDuration() - $delta);
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($tx, $span_1): Closure {
                $delta = TestCaseBase::calcEndTime($tx) - TestCaseBase::calcEndTime($span_1) + 2;
                self::assertGreaterThan(0, $delta);
                $span_1->setDuration($span_1->getDuration() + $delta);
                return function () use ($span_1, $delta): void {
                    $span_1->setDuration($span_1->getDuration() - $delta);
                };
            }
        );
    }

    public function testChildTransactionStartedBeforeParentStarted(): void
    {
        $tx_C = new MockTransaction();
        self::padSegmentTime($tx_C, /* microseconds */ 3);
        $span_B = new MockSpan([], [$tx_C]);
        self::padSegmentTime($span_B, /* microseconds */ 3);
        $tx_A = new MockTransaction([$span_B]);
        self::padSegmentTime($tx_A, /* microseconds */ 3);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_C],
            [$span_B],
            function () use ($span_B, $tx_C): Closure {
                $delta = $tx_C->getTimestamp() - $span_B->getTimestamp() + 2;
                self::assertGreaterThan(0, $delta);
                $tx_C->setTimestamp($tx_C->getTimestamp() - $delta);
                return function () use ($tx_C, $delta): void {
                    $tx_C->setTimestamp($tx_C->getTimestamp() + $delta);
                };
            }
        );
    }

    public function testChildTransactionCanStartAfterParentEnded(): void
    {
        $tx_C = new MockTransaction();
        self::padSegmentTime($tx_C, /* microseconds */ 5);
        $span_B = new MockSpan([], [$tx_C]);
        self::padSegmentTime($span_B, /* microseconds */ 5);
        $tx_C->setTimestamp(TestCaseBase::calcEndTime($span_B) + 2);
        $tx_C->setDuration(TimeUtil::microsecondsToMilliseconds(2));
        $tx_A = new MockTransaction([$span_B]);
        self::padSegmentTime($tx_A, /* microseconds */ 5);

        self::assertValidTransactionsAndSpans(self::idToEvent([$tx_A, $tx_C]), self::idToEvent([$span_B]));
    }

    public function testSpanOffsetNotInSyncWithTransaction(): void
    {
        $span = new MockSpan();
        self::padSegmentTime($span, /* microseconds */ 3);
        $tx = new MockTransaction([$span]);
        self::padSegmentTime($tx, /* microseconds */ 3);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span],
            function () use ($span): Closure {
                $span->setStart($span->getStart() - 2);
                return function () use ($span): void {
                    $span->setStart($span->getStart() + 2);
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx],
            [$span],
            function () use ($span): Closure {
                $span->setStart($span->getStart() + 2);
                return function () use ($span): void {
                    $span->setStart($span->getStart() - 2);
                };
            }
        );
    }

    public function testParentAndChildTransactionsTraceIdMismatch(): void
    {
        $tx_B = new MockTransaction();
        $tx_A = new MockTransaction([], [$tx_B]);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_B],
            [],
            function () use ($tx_A, $tx_B): Closure {
                $tx_B->setTraceId(IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES));
                return function () use ($tx_A, $tx_B): void {
                    $tx_B->setTraceId($tx_A->getTraceId());
                };
            }
        );
    }
}
