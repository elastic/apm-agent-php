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
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\EnabledLoggerProxy;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\LoggingSubsystem;
use Elastic\Apm\Impl\Log\NoopLogSink;
use Elastic\Apm\Impl\NoopEventSink;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContext;
use ElasticApmTests\ComponentTests\Util\FlakyAssertions;
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

    /** @var LoggerFactory */
    private static $noopLoggerFactory;

    /** @var bool */
    public static $isUnitTest = true;

    /**
     * @param mixed        $name
     * @param array<mixed> $data
     * @param mixed        $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        LoggingSubsystem::$isInTestingContext = true;
        SerializationUtil::$isInTestingContext = true;

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
        self::assertThat($lhs, self::logicalOr(new IsEqual($rhs, /* delta: */ 1), new LessThan($rhs)));
    }

    public static function calcEndTime(ExecutionSegmentData $timedData): float
    {
        return $timedData->timestamp + TimeUtil::millisecondsToMicroseconds($timedData->duration);
    }

    public static function assertTimestampIsInside(float $innerTimestamp, ExecutionSegmentData $outerExecSeg): void
    {
        self::assertLessThanOrEqualTimestamp($outerExecSeg->timestamp, $innerTimestamp);
        self::assertLessThanOrEqualTimestamp($innerTimestamp, self::calcEndTime($outerExecSeg));
    }

    public static function assertTimedEventIsNested(
        ExecutionSegmentData $nestedExecSeg,
        ExecutionSegmentData $outerExecSeg
    ): void {
        self::assertTimestampIsInside($nestedExecSeg->timestamp, $outerExecSeg);
        self::assertTimestampIsInside(self::calcEndTime($nestedExecSeg), $outerExecSeg);
    }

    /**
     * @param TransactionData         $transaction
     * @param array<string, SpanData> $idToSpan
     * @param bool                    $forceEnableFlakyAssertions
     */
    public static function assertValidTransactionAndItsSpans(
        TransactionData $transaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        ValidationUtil::assertValidTransactionData($transaction);

        /** @var SpanData $span */
        foreach ($idToSpan as $span) {
            ValidationUtil::assertValidSpanData($span);
            self::assertSame($transaction->id, $span->transactionId);
            self::assertSame($transaction->traceId, $span->traceId);

            if ($span->parentId === $transaction->id) {
                self::assertTimedEventIsNested($span, $transaction);
            } else {
                self::assertArrayHasKey($span->parentId, $idToSpan, 'count($idToSpan): ' . count($idToSpan));
                self::assertTimedEventIsNested($span, $idToSpan[$span->parentId]);
            }
        }

        FlakyAssertions::run(
            function () use ($transaction, $idToSpan): void {
                self::assertValidTransactionAndItsSpansFlakyPart($transaction, $idToSpan);
            },
            $forceEnableFlakyAssertions
        );
    }

    /**
     * @param TransactionData         $transaction
     * @param array<string, SpanData> $idToSpan
     */
    private static function assertValidTransactionAndItsSpansFlakyPart(
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
     * @param array<string, SpanData> $idToSpan
     *
     * @return array<string, array<string, SpanData>>
     */
    public static function groupSpansByTransactionId(array $idToSpan): array
    {
        /** @var array<string, array<string, SpanData>> */
        $transactionIdToSpans = [];

        /** @var SpanData $span */
        foreach ($idToSpan as $spanId => $span) {
            if (!array_key_exists($span->transactionId, $transactionIdToSpans)) {
                $transactionIdToSpans[$span->transactionId] = [];
            }
            $transactionIdToSpans[$span->transactionId][$spanId] = $span;
        }

        return $transactionIdToSpans;
    }

    /**
     * @param array<string, TransactionData> $idToTransaction
     *
     * @return TransactionData
     */
    public static function findRootTransaction(array $idToTransaction): TransactionData
    {
        /** @var TransactionData|null */
        $rootTransaction = null;
        foreach ($idToTransaction as $transaction) {
            if (is_null($transaction->parentId)) {
                self::assertNull($rootTransaction, 'Found more than one root transaction');
                $rootTransaction = $transaction;
            }
        }
        self::assertNotNull(
            $rootTransaction,
            'Root transaction not found. ' . LoggableToString::convert(['idToTransaction' => $idToTransaction])
        );
        return $rootTransaction;
    }

    /**
     * @param array<string, TransactionData> $idToTransaction
     * @param array<string, SpanData>        $idToSpan
     */
    private static function assertTransactionsGraphIsTree(array $idToTransaction, array $idToSpan): void
    {
        $rootTransaction = self::findRootTransaction($idToTransaction);
        /** @var array<string, string> */
        $transactionIdToParentId = [];
        foreach ($idToTransaction as $transactionId => $transaction) {
            if (is_null($transaction->parentId)) {
                continue;
            }
            $parentSpan = ArrayUtil::getValueIfKeyExistsElse($transaction->parentId, $idToSpan, null);
            if (is_null($parentSpan)) {
                self::assertArrayHasKey($transaction->parentId, $idToTransaction);
                $transactionIdToParentId[$transactionId] = $transaction->parentId;
            } else {
                $transactionIdToParentId[$transactionId] = $parentSpan->transactionId;
            }
        }
        self::assertNotNull($rootTransaction);
        self::assertGraphIsTree($rootTransaction->id, $transactionIdToParentId);
    }

    /**
     * @param array<string, TransactionData> $idToTransaction
     * @param array<string, SpanData>        $idToSpan
     * @param bool                           $forceEnableFlakyAssertions
     */
    public static function assertValidTransactionsAndSpans(
        array $idToTransaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        self::assertTransactionsGraphIsTree($idToTransaction, $idToSpan);

        $rootTransaction = self::findRootTransaction($idToTransaction);

        // Assert that all transactions have the same traceId
        foreach ($idToTransaction as $transaction) {
            self::assertSame($rootTransaction->traceId, $transaction->traceId);
        }

        // Assert that each transaction did not start before its parent
        foreach ($idToTransaction as $transaction) {
            if (is_null($transaction->parentId)) {
                continue;
            }
            /** @var ExecutionSegmentData|null $parentExecSegment */
            $parentExecSegment = ArrayUtil::getValueIfKeyExistsElse($transaction->parentId, $idToSpan, null);
            if (is_null($parentExecSegment)) {
                self::assertTrue(
                    ArrayUtil::getValueIfKeyExists(
                        $transaction->parentId,
                        $idToTransaction,
                        /* ref */ $parentExecSegment
                    )
                );
            }
            self::assertNotNull($parentExecSegment);
            self::assertLessThanOrEqualTimestamp($parentExecSegment->timestamp, $transaction->timestamp);
        }

        // Assert that all spans have the same traceId
        foreach ($idToSpan as $span) {
            self::assertSame($rootTransaction->traceId, $span->traceId);
        }

        // Assert that every span's transactionId is present
        /** @var SpanData $span */
        foreach ($idToSpan as $span) {
            self::assertArrayHasKey($span->transactionId, $idToTransaction);
        }

        // Group spans by transaction and verify every group
        foreach ($idToTransaction as $transactionId => $transaction) {
            $idToSpanOnlyCurrentTransaction = [];
            foreach ($idToSpan as $spanId => $span) {
                if ($span->transactionId === $transactionId) {
                    $idToSpanOnlyCurrentTransaction[$spanId] = $span;
                }
            }
            self::assertValidTransactionAndItsSpans(
                $transaction,
                $idToSpanOnlyCurrentTransaction,
                $forceEnableFlakyAssertions
            );
        }
    }

    public static function assertTransactionEquals(TransactionData $expected, TransactionData $actual): void
    {
        self::assertEquals($expected, $actual);
    }

    public static function assertSpanEquals(SpanData $expected, SpanData $actual): void
    {
        self::assertEquals($expected, $actual);
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

    /**
     * @param array<mixed> $subSet
     * @param array<mixed> $largerSet
     */
    public static function assertListArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        self::assertTrue(
            count(array_intersect($subSet, $largerSet)) === count($subSet),
            LoggableToString::convert(
                [
                    'array_diff'             => array_diff($subSet, $largerSet),
                    'count(array_intersect)' => count(array_intersect($subSet, $largerSet)),
                    'count($subSet)'         => count($subSet),
                    'array_intersect'        => array_intersect($subSet, $largerSet),
                    '$subSet'                => $subSet,
                    '$largerSet'             => $largerSet,
                ]
            )
        );
    }

    /**
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $message
     */
    public static function assertSameEx($expected, $actual, string $message = ''): void
    {
        /**
         * @param mixed $value
         *
         * @return bool
         */
        $isNumeric = function ($value): bool {
            return is_float($value) || is_int($value);
        };
        if ($isNumeric($expected) && $isNumeric($actual) && (is_float($expected) !== is_float($actual))) {
            self::assertSame(floatval($expected), floatval($actual), $message);
        } else {
            self::assertSame($expected, $actual, $message);
        }
    }

    /**
     * @param array<mixed, mixed> $subSet
     * @param array<mixed, mixed> $largerSet
     */
    public static function assertMapArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        foreach ($subSet as $key => $value) {
            $ctx = LoggableToString::convert(
                [
                    '$key'       => $key,
                    '$value'     => $value,
                    '$subSet'    => $subSet,
                    '$largerSet' => $largerSet,
                ]
            );
            self::assertArrayHasKey($key, $largerSet, $ctx);
            self::assertSameEx($value, $largerSet[$key], $ctx);
        }
    }

    public static function getExecutionSegmentContext(ExecutionSegmentData $execSegData): ?ExecutionSegmentContextData
    {
        if ($execSegData instanceof SpanData) {
            return $execSegData->context;
        }

        TestCase::assertInstanceOf(TransactionData::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->context;
    }

    /**
     * @param ExecutionSegmentData $execSegData
     * @param string               $key
     *
     * @return bool
     */
    public static function hasLabel(ExecutionSegmentData $execSegData, string $key): bool
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if (is_null($context)) {
            return false;
        }
        return array_key_exists($key, $context->labels);
    }

    /**
     * @param int                  $expectedCount
     * @param ExecutionSegmentData $execSegData
     *
     * @return void
     */
    public static function assertLabelsCount(int $expectedCount, ExecutionSegmentData $execSegData): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if (is_null($context)) {
            self::assertSame(0, $expectedCount, LoggableToString::convert($execSegData));
            return;
        }
        self::assertCount($expectedCount, $context->labels);
    }

    /**
     * @param ExecutionSegmentData $execSegData
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function getLabels(ExecutionSegmentData $execSegData): array
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if (is_null($context)) {
            return [];
        }
        return $context->labels;
    }

    /**
     * @param ExecutionSegmentData $execSegData
     * @param string               $key
     *
     * @return string|bool|int|float|null
     */
    public static function getLabel(ExecutionSegmentData $execSegData, string $key)
    {
        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context);
        self::assertArrayHasKey($key, $context->labels);
        return $context->labels[$key];
    }

    public static function assertHasLabel(ExecutionSegmentData $execSegData, string $key, string $message = ''): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context, LoggableToString::convert(['execSegData' => $execSegData]) . '. ' . $message);
        self::assertArrayHasKey(
            $key,
            $context->labels,
            LoggableToString::convert(['key' => $key, 'execSegData' => $execSegData]) . '. ' . $message
        );
    }

    public static function assertNotHasLabel(ExecutionSegmentData $execSegData, string $key): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if (is_null($context)) {
            return;
        }
        self::assertArrayNotHasKey($key, $context->labels);
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualLists(array $expected, array $actual): void
    {
        self::assertTrue(sort(/* ref */ $expected));
        self::assertTrue(sort(/* ref */ $actual));
        self::assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * @param array<string|int, mixed> $idToXyzMap
     *
     * @return string[]
     */
    public static function getIdsFromIdToMap(array $idToXyzMap): array
    {
        /** @var string[] */
        $result = [];
        foreach ($idToXyzMap as $id => $_) {
            $result[] = strval($id);
        }
        return $result;
    }

    public static function buildTracerForTests(?EventSinkInterface $eventSink = null): TracerBuilderForTests
    {
        return TracerBuilderForTests::startNew()
                                    ->withLogSink(NoopLogSink::singletonInstance())
                                    ->withEventSink($eventSink ?? NoopEventSink::singletonInstance());
    }

    public static function noopLoggerFactory(): LoggerFactory
    {
        if (!isset(self::$noopLoggerFactory)) {
            self::$noopLoggerFactory = new LoggerFactory(
                new LogBackend(LogLevel::OFF, NoopLogSink::singletonInstance())
            );
        }
        return self::$noopLoggerFactory;
    }

    public static function getParentId(ExecutionSegmentData $execSegData): ?string
    {
        if ($execSegData instanceof SpanData) {
            return $execSegData->parentId;
        }

        TestCase::assertInstanceOf(TransactionData::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->parentId;
    }

    public static function setParentId(ExecutionSegmentData $execSegData, ?string $newParentId): void
    {
        if ($execSegData instanceof SpanData) {
            self::assertNotNull($newParentId);
            $execSegData->parentId = $newParentId;
            return;
        }

        TestCase::assertInstanceOf(TransactionData::class, $execSegData, DbgUtil::getType($execSegData));
        $execSegData->parentId = $newParentId;
    }

    public static function generateDummyMaxKeywordString(string $prefix = ''): string
    {
        $halfLen = Constants::KEYWORD_STRING_MAX_LENGTH / 2;
        return
            $prefix
            . '['
            . str_repeat('V', $halfLen - strlen($prefix) - 4)
            . ','
            . ';'
            . str_repeat('W', $halfLen)
            . ']';
    }

    /**
     * @return iterable<array<bool>>
     */
    public function boolDataProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    private static function processSpecificPrefix(): string
    {
        return self::$isUnitTest ? '' : AmbientContext::dbgProcessName() . ' [PID: ' . getmypid() . '] ';
    }

    public static function printMessage(string $srcMethod, string $msg): void
    {
        if (!defined('STDERR')) {
            define('STDERR', fopen('php://stderr', 'w'));
        }

        if (defined('STDERR')) {
            fwrite(STDERR, self::processSpecificPrefix() . '[' . $srcMethod . ']' . ' ' . $msg . PHP_EOL);
        }
    }

    public static function logAndPrintMessage(
        ?EnabledLoggerProxy $loggerProxy,
        string $msg
    ): void {
        if (!defined('STDERR')) {
            define('STDERR', fopen('php://stderr', 'w'));
        }
        if (defined('STDERR')) {
            fwrite(STDERR, self::processSpecificPrefix() . $msg . PHP_EOL);
        }

        if ($loggerProxy !== null) {
            $loggerProxy->log($msg);
        }
    }

    protected static function dummyAssert(): void
    {
        self::assertTrue(true);
    }
}
