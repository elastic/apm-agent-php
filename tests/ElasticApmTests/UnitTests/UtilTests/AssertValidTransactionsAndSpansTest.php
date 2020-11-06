<?php

/** @noinspection PhpDocMissingThrowsInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\UtilTests;

use Closure;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\UnitTests\Util\MockSpanData;
use ElasticApmTests\UnitTests\Util\MockTransactionData;
use ElasticApmTests\Util\InvalidEventDataException;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Exception as PhpUnitException;
use Throwable;

class AssertValidTransactionsAndSpansTest extends TestCaseBase
{
    /**
     * @param TransactionData[] $transactions
     * @param SpanData[]        $spans
     * @param callable          $corruptFunc
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
     * @param array<string, TransactionData> $idToTransaction
     * @param array<string, SpanData>        $idToSpan
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
     * @param ExecutionSegmentData[] $events
     *
     * @return array<string, ExecutionSegmentData>
     *
     * @template        T of ExecutionSegmentData
     * @phpstan-param   T[] $events
     * @phpstan-return  array<string, T>
     *
     */
    private static function idToEvent(array $events): array
    {
        $result = [];
        foreach ($events as $event) {
            self::assertArrayNotHasKey($event->id, $result);
            $result[$event->id] = $event;
        }
        return $result;
    }

    /**
     * @param ExecutionSegmentData $executionSegment
     * @param string|null          $newParentId
     *
     * @return Closure
     *
     * @phpstan-return Closure(): Closure(): void
     */
    private static function makeCorruptParentIdFunc(
        ExecutionSegmentData $executionSegment,
        ?string $newParentId = null
    ): Closure {
        return function () use ($executionSegment, $newParentId): Closure {
            $oldParentId = self::getParentId($executionSegment);
            self::setParentId(
                $executionSegment,
                $newParentId ?? IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)
            );
            return function () use ($executionSegment, $oldParentId): void {
                self::setParentId($executionSegment, $oldParentId);
            };
        };
    }

    /**
     * @param MockSpanData $span
     * @param string|null  $newTransactionId
     *
     * @return Closure
     *
     * @phpstan-return Closure(): Closure(): void
     */
    private static function makeCorruptTransactionIdFunc(MockSpanData $span, ?string $newTransactionId = null): Closure
    {
        return function () use ($span, $newTransactionId): Closure {
            $oldTransactionId = $span->transactionId;
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
        $span = new MockSpanData();
        $tx = new MockTransactionData([$span]);

        $this->assertValidAndCorrupted([$tx], [$span], self::makeCorruptParentIdFunc($span));
    }

    public function testTwoSpansNotReachableFromRoot(): void
    {
        $span_1_1 = new MockSpanData();
        $span_1 = new MockSpanData([$span_1_1]);
        $tx = new MockTransactionData([$span_1]);

        $this->assertValidAndCorrupted([$tx], [$span_1, $span_1_1], self::makeCorruptParentIdFunc($span_1));
        $this->assertValidAndCorrupted([$tx], [$span_1, $span_1_1], self::makeCorruptParentIdFunc($span_1_1));
    }

    public function testSpanParentCycle(): void
    {
        $span_1_1 = new MockSpanData();
        $span_1 = new MockSpanData([$span_1_1]);
        $tx = new MockTransactionData([$span_1]);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            self::makeCorruptParentIdFunc($span_1, $span_1_1->id)
        );
    }

    public function testTransactionNotReachableFromRoot(): void
    {
        $tx_C = new MockTransactionData();
        $tx_B = new MockTransactionData([], [$tx_C]);
        $tx_A = new MockTransactionData([], [$tx_B]);

        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_B));
        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_C));
        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_B, $tx_C->id));
    }

    public function testTransactionWithSpansNotReachableFromRoot(): void
    {
        $span_I = new MockSpanData();
        $span_H = new MockSpanData([$span_I]);
        $tx_G = new MockTransactionData([$span_H]);
        $span_F = new MockSpanData([], [$tx_G]);
        $span_E = new MockSpanData([$span_F]);
        $tx_D = new MockTransactionData([$span_E]);
        $span_C = new MockSpanData([], [$tx_D]);
        $span_B = new MockSpanData([$span_C]);
        $tx_A = new MockTransactionData([$span_B]);

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
            self::makeCorruptParentIdFunc($tx_D, $span_I->id)
        );

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_D, $tx_G],
            [$span_B, $span_C, $span_E, $span_F, $span_H, $span_I],
            self::makeCorruptParentIdFunc($tx_D, $span_H->id)
        );
    }

    public function testNoRootTransaction(): void
    {
        $tx_C = new MockTransactionData();
        $tx_B = new MockTransactionData([], [$tx_C]);
        $tx_A = new MockTransactionData([], [$tx_B]);

        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_A));
        $this->assertValidAndCorrupted([$tx_A, $tx_B, $tx_C], [], self::makeCorruptParentIdFunc($tx_A, $tx_C->id));
    }

    public function testMoreThanOneRootTransaction(): void
    {
        $tx_B = new MockTransactionData();
        $tx_A = new MockTransactionData([], [$tx_B]);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_B],
            [],
            function () use ($tx_A, $tx_B): Closure {
                $tx_B->parentId = null;
                return function () use ($tx_A, $tx_B): void {
                    $tx_B->parentId = $tx_A->id;
                };
            }
        );
    }

    public function testSpanWithoutTransaction(): void
    {
        $span = new MockSpanData();
        $tx = new MockTransactionData([$span]);

        $this->assertValidAndCorrupted([$tx], [$span], self::makeCorruptTransactionIdFunc($span));
    }

    private static function padSegmentTime(ExecutionSegmentData $executionSegment, float $paddingInMicroseconds): void
    {
        $executionSegment->timestamp = $executionSegment->timestamp - $paddingInMicroseconds;
        $executionSegment->duration
            = $executionSegment->duration + TimeUtil::microsecondsToMilliseconds($paddingInMicroseconds);
    }

    public function testSpanStartedBeforeParent(): void
    {
        $span_1_1 = new MockSpanData();
        self::padSegmentTime($span_1_1, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);
        $span_1 = new MockSpanData([$span_1_1]);
        self::padSegmentTime($span_1, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);
        $tx = new MockTransactionData([$span_1]);
        self::padSegmentTime($tx, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            /* corruptFunc: */
            function () use ($span_1, $span_1_1): Closure {
                $delta = $span_1_1->timestamp - $span_1->timestamp + 2 * self::TIMESTAMP_COMPARISON_PRECISION;
                self::assertGreaterThan(0, $delta);
                $span_1_1->timestamp = $span_1_1->timestamp - $delta;
                $span_1_1->duration = $span_1_1->duration + $delta;
                /* revertCorruptFunc: */
                return function () use ($span_1_1, $delta): void {
                    $span_1_1->timestamp = $span_1_1->timestamp + $delta;
                    $span_1_1->duration = $span_1_1->duration - $delta;
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($tx, $span_1): Closure {
                $delta = $span_1->timestamp - $tx->timestamp + 2 * self::TIMESTAMP_COMPARISON_PRECISION;
                self::assertGreaterThan(0, $delta);
                $span_1->timestamp = $span_1->timestamp - $delta;
                $span_1->duration = $span_1->duration + $delta;
                return function () use ($span_1, $delta): void {
                    $span_1->timestamp = $span_1->timestamp + $delta;
                    $span_1->duration = $span_1->duration - $delta;
                };
            }
        );
    }

    public function testSpanEndedAfterParent(): void
    {
        $span_1_1 = new MockSpanData();
        self::padSegmentTime($span_1_1, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);
        $span_1 = new MockSpanData([$span_1_1]);
        self::padSegmentTime($span_1, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);
        $tx = new MockTransactionData([$span_1]);
        self::padSegmentTime($tx, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($span_1, $span_1_1): Closure {
                $delta = TestCaseBase::calcEndTime($span_1) - TestCaseBase::calcEndTime($span_1_1)
                         + 2 * self::TIMESTAMP_COMPARISON_PRECISION;
                self::assertGreaterThan(0, $delta);
                $span_1_1->duration = $span_1_1->duration + $delta;
                return function () use ($span_1_1, $delta): void {
                    $span_1_1->duration = $span_1_1->duration - $delta;
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx],
            [$span_1, $span_1_1],
            function () use ($tx, $span_1): Closure {
                $delta = TestCaseBase::calcEndTime($tx) - TestCaseBase::calcEndTime($span_1)
                         + 2 * self::TIMESTAMP_COMPARISON_PRECISION;
                self::assertGreaterThan(0, $delta);
                $span_1->duration = $span_1->duration + $delta;
                return function () use ($span_1, $delta): void {
                    $span_1->duration = $span_1->duration - $delta;
                };
            }
        );
    }

    public function testChildTransactionStartedBeforeParentStarted(): void
    {
        $tx_C = new MockTransactionData();
        self::padSegmentTime($tx_C, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);
        $span_B = new MockSpanData([], [$tx_C]);
        self::padSegmentTime($span_B, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);
        $tx_A = new MockTransactionData([$span_B]);
        self::padSegmentTime($tx_A, /* microseconds */ 3 * self::TIMESTAMP_COMPARISON_PRECISION);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_C],
            [$span_B],
            function () use ($span_B, $tx_C): Closure {
                $delta = $tx_C->timestamp - $span_B->timestamp + 2 * self::TIMESTAMP_COMPARISON_PRECISION;
                self::assertGreaterThan(0, $delta);
                $tx_C->timestamp = $tx_C->timestamp - $delta;
                return function () use ($tx_C, $delta): void {
                    $tx_C->timestamp = $tx_C->timestamp + $delta;
                };
            }
        );
    }

    public function testChildTransactionCanStartAfterParentEnded(): void
    {
        $tx_C = new MockTransactionData();
        self::padSegmentTime($tx_C, /* microseconds */ 5 * self::TIMESTAMP_COMPARISON_PRECISION);
        $span_B = new MockSpanData([], [$tx_C]);
        self::padSegmentTime($span_B, /* microseconds */ 5 * self::TIMESTAMP_COMPARISON_PRECISION);
        $tx_C->timestamp = TestCaseBase::calcEndTime($span_B) + 2 * self::TIMESTAMP_COMPARISON_PRECISION;
        $tx_C->duration = TimeUtil::microsecondsToMilliseconds(2 * self::TIMESTAMP_COMPARISON_PRECISION);
        $tx_A = new MockTransactionData([$span_B]);
        self::padSegmentTime($tx_A, /* microseconds */ 5 * self::TIMESTAMP_COMPARISON_PRECISION);

        self::assertValidTransactionsAndSpans(self::idToEvent([$tx_A, $tx_C]), self::idToEvent([$span_B]));
    }

    public function testParentAndChildTransactionsTraceIdMismatch(): void
    {
        $tx_B = new MockTransactionData();
        $tx_A = new MockTransactionData([], [$tx_B]);

        $this->assertValidAndCorrupted(
            [$tx_A, $tx_B],
            [],
            function () use ($tx_A, $tx_B): Closure {
                $tx_B->setTraceId(IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES));
                return function () use ($tx_A, $tx_B): void {
                    $tx_B->setTraceId($tx_A->traceId);
                };
            }
        );
    }
}
