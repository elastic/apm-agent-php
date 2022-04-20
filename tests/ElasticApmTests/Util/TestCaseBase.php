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

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\EnabledLoggerProxy;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\NoopLogSink;
use Elastic\Apm\Impl\NoopEventSink;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\DbgUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContext;
use PHPUnit\Framework\Constraint\Exception as ConstraintException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Throwable;

class TestCaseBase extends TestCase
{
    /** @var bool */
    public static $isUnitTest = true;

    /** @var ?LoggerFactory */
    private static $noopLoggerFactory = null;

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
        if (self::$noopLoggerFactory === null) {
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

    public static function dummyAssert(): void
    {
        self::assertTrue(true);
    }

    /**
     * @param array<string, TransactionData> $idToTransaction
     * @param array<string, SpanData>        $idToSpan
     * @param bool                           $forceEnableFlakyAssertions
     */
    public static function assertValidOneTraceTransactionsAndSpans(
        array $idToTransaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        TraceDataValidator::validate(
            new TraceDataActual($idToTransaction, $idToSpan),
            null /* <- expected */,
            $forceEnableFlakyAssertions
        );
    }

    /**
     * @param TransactionData         $transaction
     * @param array<string, SpanData> $idToSpan
     * @param bool                    $forceEnableFlakyAssertions
     */
    protected static function assertValidTransactionAndSpans(
        TransactionData $transaction,
        array $idToSpan,
        bool $forceEnableFlakyAssertions = false
    ): void {
        TraceDataValidator::validate(
            new TraceDataActual([$transaction->id => $transaction], $idToSpan),
            null /* <- expected */,
            $forceEnableFlakyAssertions
        );
    }
}
