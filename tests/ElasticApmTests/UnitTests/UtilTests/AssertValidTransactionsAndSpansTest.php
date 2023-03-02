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

/** @noinspection PhpDocMissingThrowsInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\UtilTests;

use Closure;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\UnitTests\Util\MockClock;
use ElasticApmTests\UnitTests\Util\MockSpan;
use ElasticApmTests\UnitTests\Util\MockTracer;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\ExecutionSegmentDto;
use ElasticApmTests\Util\InvalidEventDataException;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TraceActual;
use ElasticApmTests\Util\TraceExpectations;
use ElasticApmTests\Util\TraceValidator;
use ElasticApmTests\Util\TransactionDto;
use PHPUnit\Exception as PhpUnitException;
use Throwable;

class AssertValidTransactionsAndSpansTest extends TestCaseBase
{
    /** @var MockClock */
    protected $mockClock;

    /** @var MockTracer */
    protected $mockTracer;

    /** @var float */
    protected $startTimestamp;

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();

        $this->mockClock = new MockClock(/* initial */ 1000 * 1000 * 1000);
        $this->startTimestamp = mt_rand(1, 10) * TimeUtil::secondsToMicroseconds(1000 * 1000);
        // Fast forward to some initial point
        $this->mockClock->fastForwardMicroseconds($this->startTimestamp);
        $this->mockTracer = new MockTracer($this->mockClock);
    }

    /**
     * @param array<string, TransactionDto> $idToTransaction
     * @param array<string, SpanDto>        $idToSpan
     * @param bool                           $forceEnableFlakyAssertions
     */
    public function assertValidOneTraceTransactionsAndSpans(
        array $idToTransaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        $expected = new TraceExpectations();
        $expected->transaction->timestampBefore = $this->startTimestamp;
        $expected->transaction->timestampAfter = $this->mockClock->getSystemClockCurrentTime();
        $expected->span->timestampBefore = $this->startTimestamp;
        $expected->span->timestampAfter = $this->mockClock->getSystemClockCurrentTime();
        TraceValidator::validate(
            new TraceActual($idToTransaction, $idToSpan),
            $expected,
            $forceEnableFlakyAssertions
        );
    }

    /**
     * @param TransactionDto[]            $transactions
     * @param SpanDto[]                   $spans
     * @param callable                     $corruptFunc
     *
     * @phpstan-param callable(): callable $corruptFunc
     */
    private function assertValidAndCorrupted(
        array $transactions,
        array $spans,
        callable $corruptFunc
    ): void {
        $idToTransaction = self::idToEvent($transactions);
        $idToSpan = self::idToEvent($spans);
        self::assertValidOneTraceTransactionsAndSpans($idToTransaction, $idToSpan);
        /** @var callable(): void $revertCorruptFunc */
        $revertCorruptFunc = $corruptFunc();
        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertInvalidTransactionsAndSpans($idToTransaction, $idToSpan);
        $revertCorruptFunc();
        self::assertValidOneTraceTransactionsAndSpans($idToTransaction, $idToSpan);
    }

    /**
     * @param array<string, TransactionDto> $idToTransaction
     * @param array<string, SpanDto>        $idToSpan
     */
    private function assertInvalidTransactionsAndSpans(array $idToTransaction, array $idToSpan): void
    {
        try {
            $this->assertValidOneTraceTransactionsAndSpans(
                $idToTransaction,
                $idToSpan,
                /* forceEnableFlakyAssertions: */ true
            );
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
     * @param ExecutionSegmentDto[] $events
     *
     * @return array<string, ExecutionSegmentDto>
     *
     * @template        T of ExecutionSegmentDto
     * @phpstan-param   T[]          $events
     * @phpstan-return  array<string, T>
     *
     */
    private static function idToEvent(array $events): array
    {
        $result = [];
        foreach ($events as $event) {
            ArrayUtilForTests::addUnique($event->id, $event, /* ref */ $result);
        }
        return $result;
    }

    /**
     * @param ExecutionSegmentDto $execSeg
     * @param ?string              $newParentId
     *
     * @return Closure(): Closure(): void
     */
    private static function makeCorruptParentIdFunc(ExecutionSegmentDto $execSeg, ?string $newParentId): Closure
    {
        return function () use ($execSeg, $newParentId): Closure {
            $oldParentId = self::getParentId($execSeg);
            self::setParentId($execSeg, $newParentId);
            return function () use ($execSeg, $oldParentId): void {
                self::setParentId($execSeg, $oldParentId);
            };
        };
    }

    /**
     * @param ExecutionSegmentDto $execSeg
     *
     * @return Closure(): Closure(): void
     */
    private static function makeCorruptRandomParentIdFunc(ExecutionSegmentDto $execSeg): Closure
    {
        return self::makeCorruptParentIdFunc(
            $execSeg,
            IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES)
        );
    }

    private static function makeCorruptRandomTransactionIdFunc(MockSpan $span): Closure
    {
        return function () use ($span): Closure {
            $oldTransactionId = $span->transactionId;
            $span->transactionId = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
            return function () use ($span, $oldTransactionId): void {
                $span->transactionId = $oldTransactionId;
            };
        };
    }

    public function testOneSpanNotReachableFromRoot(): void
    {
        $tx = $this->mockTracer->beginTransaction();
        $span = $tx->beginChildSpan();
        $span->end();
        $tx->end();

        $this->assertValidAndCorrupted([$tx], [$span], self::makeCorruptRandomParentIdFunc($span));
    }

    public function testTwoSpansNotReachableFromRoot(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $span_L2 = $tx_L1->beginChildSpan();
        $span_L3 = $tx_L1->beginChildSpan();
        $span_L3->end();
        $span_L2->end();
        $tx_L1->end();

        $this->assertValidAndCorrupted([$tx_L1], [$span_L2, $span_L3], self::makeCorruptRandomParentIdFunc($span_L2));
        $this->assertValidAndCorrupted([$tx_L1], [$span_L2, $span_L3], self::makeCorruptRandomParentIdFunc($span_L3));
    }

    public function testSpanParentCycle(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction('tx_L1');
        $span_L2 = $tx_L1->beginChildSpan('span_L2');
        $span_L3 = $span_L2->beginChildSpan('span_L3');
        $span_L3->end();
        $span_L2->end();
        $tx_L1->end();

        $allSpans = [$span_L2, $span_L3];
        $this->assertValidAndCorrupted([$tx_L1], $allSpans, self::makeCorruptParentIdFunc($span_L2, $span_L3->id));
    }

    public function testTransactionNotReachableFromRoot(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $tx_L2 = $tx_L1->beginChildTransaction();
        $tx_L3 = $tx_L2->beginChildTransaction();
        $tx_L3->end();
        $tx_L2->end();
        $tx_L1->end();

        $allTransactions = [$tx_L1, $tx_L2, $tx_L3];
        $this->assertValidAndCorrupted($allTransactions, [], self::makeCorruptRandomParentIdFunc($tx_L2));
        $this->assertValidAndCorrupted($allTransactions, [], self::makeCorruptRandomParentIdFunc($tx_L3));
    }

    public function testTransactionParentCycle(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $tx_L2 = $tx_L1->beginChildTransaction();
        $tx_L3 = $tx_L2->beginChildTransaction();
        $tx_L3->end();
        $tx_L2->end();
        $tx_L1->end();

        $allTransactions = [$tx_L1, $tx_L2, $tx_L3];
        $this->assertValidAndCorrupted($allTransactions, [], self::makeCorruptParentIdFunc($tx_L2, $tx_L3->id));
    }

    public function testTransactionWithSpansNotReachableFromRoot(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $sp_L2 = $tx_L1->beginChildSpan();
        $sp_L3 = $sp_L2->beginChildSpan();
        $tx_L4 = $sp_L3->beginChildTransaction();
        $sp_L5 = $tx_L4->beginChildSpan();
        $sp_L6 = $sp_L5->beginChildSpan();
        $tx_L7 = $sp_L6->beginChildTransaction();
        $sp_L8 = $tx_L7->beginChildSpan();
        $sp_L9 = $sp_L8->beginChildSpan();
        $sp_L9->end();
        $sp_L8->end();
        $tx_L7->end();
        $sp_L6->end();
        $sp_L5->end();
        $tx_L4->end();
        $sp_L3->end();
        $sp_L2->end();
        $tx_L1->end();

        $allTransactions = [$tx_L1, $tx_L4, $tx_L7];
        $allSpans = [$sp_L2, $sp_L3, $sp_L5, $sp_L6, $sp_L8, $sp_L9];

        $this->assertValidAndCorrupted($allTransactions, $allSpans, self::makeCorruptRandomParentIdFunc($tx_L7));
        $this->assertValidAndCorrupted($allTransactions, $allSpans, self::makeCorruptRandomParentIdFunc($tx_L4));
        $this->assertValidAndCorrupted($allTransactions, $allSpans, self::makeCorruptParentIdFunc($tx_L4, $sp_L9->id));
        $this->assertValidAndCorrupted($allTransactions, $allSpans, self::makeCorruptParentIdFunc($tx_L4, $sp_L8->id));
    }

    public function testNoRootTransaction(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $tx_L2 = $tx_L1->beginChildTransaction();
        $tx_L3 = $tx_L1->beginChildTransaction();
        $tx_L3->end();
        $tx_L2->end();
        $tx_L1->end();

        $allTransactions = [$tx_L1, $tx_L2, $tx_L3];
        $this->assertValidAndCorrupted($allTransactions, [], self::makeCorruptRandomParentIdFunc($tx_L1));
        $this->assertValidAndCorrupted($allTransactions, [], self::makeCorruptParentIdFunc($tx_L1, $tx_L2->id));
        $this->assertValidAndCorrupted($allTransactions, [], self::makeCorruptParentIdFunc($tx_L1, $tx_L3->id));
    }

    public function testMoreThanOneRootTransaction(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $tx_L2 = $tx_L1->beginChildTransaction();
        $tx_L2->end();
        $tx_L1->end();

        $this->assertValidAndCorrupted([$tx_L1, $tx_L2], [], self::makeCorruptParentIdFunc($tx_L2, null));
    }

    public function testSpanWithoutTransaction(): void
    {
        $tx = $this->mockTracer->beginTransaction();
        $span = $tx->beginChildSpan();
        $span->end();
        $tx->end();

        $this->assertValidAndCorrupted([$tx], [$span], self::makeCorruptRandomTransactionIdFunc($span));
    }

    public function testSpanStartedBeforeParent(): void
    {
        $timeHalfStep = 2 * self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS;
        $timeStep = 2 * $timeHalfStep;
        $tx_L1 = $this->mockTracer->beginTransaction();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2 = $tx_L1->beginChildSpan();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L3 = $sp_L2->beginChildSpan();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L3->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L1->end();

        $this->assertValidAndCorrupted(
            [$tx_L1],
            [$sp_L2, $sp_L3],
            /* corruptFunc: */
            function () use ($sp_L2, $sp_L3, $timeHalfStep): Closure {
                $delta = $sp_L3->timestamp - $sp_L2->timestamp + $timeHalfStep;
                self::assertGreaterThan(0, $delta);
                $sp_L3->timestamp -= $delta;
                $sp_L3->duration += $delta;
                /* revertCorruptFunc: */
                return function () use ($sp_L3, $delta): void {
                    $sp_L3->timestamp += $delta;
                    $sp_L3->duration -= $delta;
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx_L1],
            [$sp_L2, $sp_L3],
            function () use ($tx_L1, $sp_L2, $timeHalfStep): Closure {
                $delta = $sp_L2->timestamp - $tx_L1->timestamp + $timeHalfStep;
                self::assertGreaterThan(0, $delta);
                $sp_L2->timestamp -= $delta;
                $sp_L2->duration += $delta;
                return function () use ($sp_L2, $delta): void {
                    $sp_L2->timestamp += $delta;
                    $sp_L2->duration -= $delta;
                };
            }
        );
    }

    public function testSpanEndedAfterParent(): void
    {
        $timeHalfStep = 2 * self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS;
        $timeStep = 2 * $timeHalfStep;
        $tx_L1 = $this->mockTracer->beginTransaction();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2 = $tx_L1->beginChildSpan();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L3 = $sp_L2->beginChildSpan();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L3->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L1->end();

        $this->assertValidAndCorrupted(
            [$tx_L1],
            [$sp_L2, $sp_L3],
            function () use ($sp_L2, $sp_L3, $timeHalfStep): Closure {
                $delta = TestCaseBase::calcEndTime($sp_L2) - TestCaseBase::calcEndTime($sp_L3) + $timeHalfStep;
                self::assertGreaterThan(0, $delta);
                $sp_L3->duration += $delta;
                return function () use ($sp_L3, $delta): void {
                    $sp_L3->duration -= $delta;
                };
            }
        );

        $this->assertValidAndCorrupted(
            [$tx_L1],
            [$sp_L2, $sp_L3],
            function () use ($tx_L1, $sp_L2, $timeHalfStep): Closure {
                $delta = TestCaseBase::calcEndTime($tx_L1) - TestCaseBase::calcEndTime($sp_L2) + $timeHalfStep;
                self::assertGreaterThan(0, $delta);
                $sp_L2->duration += $delta;
                return function () use ($sp_L2, $delta): void {
                    $sp_L2->duration -= $delta;
                };
            }
        );
    }

    public function testChildTransactionStartedBeforeParentStarted(): void
    {
        $timeHalfStep = 2 * self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS;
        $timeStep = 2 * $timeHalfStep;
        $tx_L1 = $this->mockTracer->beginTransaction();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2 = $tx_L1->beginChildSpan();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L3 = $sp_L2->beginChildTransaction();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L3->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L1->end();

        $this->assertValidAndCorrupted(
            [$tx_L1, $tx_L3],
            [$sp_L2],
            function () use ($sp_L2, $tx_L3, $timeHalfStep): Closure {
                $delta = $tx_L3->timestamp - $sp_L2->timestamp + $timeHalfStep;
                self::assertGreaterThan(0, $delta);
                $tx_L3->timestamp -= $delta;
                return function () use ($tx_L3, $delta): void {
                    $tx_L3->timestamp += $delta;
                };
            }
        );
    }

    public function testChildTransactionCanStartAfterParentEnded(): void
    {
        $timeHalfStep = 2 * self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS;
        $timeStep = 2 * $timeHalfStep;
        $tx_L1 = $this->mockTracer->beginTransaction();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2 = $tx_L1->beginChildSpan();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $sp_L2->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L1->end();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L3 = $sp_L2->beginChildTransaction();
        $this->mockClock->fastForwardMicroseconds($timeStep);
        $tx_L3->end();

        self::assertValidOneTraceTransactionsAndSpans(self::idToEvent([$tx_L1, $tx_L3]), self::idToEvent([$sp_L2]));
    }

    public function testParentAndChildTransactionsTraceIdMismatch(): void
    {
        $tx_L1 = $this->mockTracer->beginTransaction();
        $tx_L2 = $tx_L1->beginChildTransaction();
        $tx_L2->end();
        $tx_L1->end();

        $this->assertValidAndCorrupted(
            [$tx_L1, $tx_L2],
            [],
            function () use ($tx_L1, $tx_L2): Closure {
                $tx_L2->traceId = IdGenerator::generateId(Constants::TRACE_ID_SIZE_IN_BYTES);
                return function () use ($tx_L1, $tx_L2): void {
                    $tx_L2->traceId = $tx_L1->traceId;
                };
            }
        );
    }
}
