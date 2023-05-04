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

use Countable;
use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\OptionWithDefaultValueMetadata;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\NoopLogSink;
use Elastic\Apm\Impl\NoopEventSink;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\Exception as ConstraintException;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Throwable;

class TestCaseBase extends TestCase
{
    /**
     * 10 milliseconds (10000 microseconds) precision
     */
    public const TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS = 10000;

    public const DURATION_COMPARISON_PRECISION_MILLISECONDS = self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS / 1000;

    /** @var ?Logger */
    private $logger = null;

    public static function assertTransactionEquals(TransactionDto $expected, TransactionDto $actual): void
    {
        self::assertEquals($expected, $actual);
    }

    public static function assertSpanEquals(SpanDto $expected, SpanDto $actual): void
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
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(
            [
                'array_diff'             => array_diff($subSet, $largerSet),
                'count(array_intersect)' => count(array_intersect($subSet, $largerSet)),
                'count($subSet)'         => count($subSet),
                'array_intersect'        => array_intersect($subSet, $largerSet),
                '$subSet'                => $subSet,
                '$largerSet'             => $largerSet,
            ]
        );
        self::assertTrue(count(array_intersect($subSet, $largerSet)) === count($subSet));
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
     * @param array<mixed> $subSet
     * @param array<mixed> $largerSet
     */
    public static function assertMapArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        foreach ($subSet as $key => $value) {
            AssertMessageStack::newScope(/* out */ $dbgCtx);
            $dbgCtx->add(
                [
                    '$key'       => $key,
                    '$value'     => $value,
                    '$subSet'    => $subSet,
                    '$largerSet' => $largerSet,
                ]
            );
            self::assertArrayHasKey($key, $largerSet);
            self::assertSameEx($value, $largerSet[$key]);
        }
    }

    public static function getExecutionSegmentContext(ExecutionSegmentDto $execSegData): ?ExecutionSegmentContextDto
    {
        if ($execSegData instanceof SpanDto) {
            return $execSegData->context;
        }

        self::assertInstanceOf(TransactionDto::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->context;
    }

    /**
     * @param ExecutionSegmentDto $execSegData
     * @param string               $key
     *
     * @return bool
     */
    public static function hasLabel(ExecutionSegmentDto $execSegData, string $key): bool
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null) {
            return false;
        }
        return $context->labels !== null && array_key_exists($key, $context->labels);
    }

    /**
     * @param int                  $expectedCount
     * @param ExecutionSegmentDto $execSegData
     *
     * @return void
     */
    public static function assertLabelsCount(int $expectedCount, ExecutionSegmentDto $execSegData): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['expectedCount' => $expectedCount, 'execSegData' => $execSegData]);
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null || $context->labels === null) {
            self::assertSame(0, $expectedCount);
            return;
        }
        self::assertCount($expectedCount, $context->labels);
    }

    /**
     * @param ExecutionSegmentDto $execSegData
     *
     * @return array<string, string|bool|int|float|null>
     */
    public static function getLabels(ExecutionSegmentDto $execSegData): array
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null || $context->labels === null) {
            return [];
        }
        return $context->labels;
    }

    /**
     * @param ExecutionSegmentDto $execSegData
     * @param string               $key
     *
     * @return string|bool|int|float|null
     */
    public static function getLabel(ExecutionSegmentDto $execSegData, string $key)
    {
        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context);
        self::assertNotNull($context->labels);
        self::assertArrayHasKey($key, $context->labels);
        return $context->labels[$key];
    }

    public static function assertHasLabel(ExecutionSegmentDto $execSegData, string $key): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['execSegData' => $execSegData, 'key' => $key]);

        $context = self::getExecutionSegmentContext($execSegData);
        self::assertNotNull($context);
        self::assertNotNull($context->labels);
        self::assertArrayHasKey($key, $context->labels);
    }

    public static function assertNotHasLabel(ExecutionSegmentDto $execSegData, string $key): void
    {
        $context = self::getExecutionSegmentContext($execSegData);
        if ($context === null || $context->labels === null) {
            return;
        }
        self::assertArrayNotHasKey($key, $context->labels);
    }

    /**
     * @param array<mixed> $actual
     */
    public static function assertArrayIsList(array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['$actual' => $actual]);
        self::assertTrue(ArrayUtil::isList($actual));
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualLists(array $expected, array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['expected' => $expected, 'actual' => $actual]);
        self::assertSame(count($expected), count($actual));
        foreach (RangeUtil::generateUpTo(count($expected)) as $i) {
            AssertMessageStack::newSubScope(/* ref */ $dbgCtx);
            $dbgCtx->add(['i' => $i]);
            self::assertSame($expected[$i], $actual[$i]);
            AssertMessageStack::popSubScope(/* ref */ $dbgCtx);
        }
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualAsSets(array $expected, array $actual, string $message = ''): void
    {
        self::assertTrue(sort(/* ref */ $expected));
        self::assertTrue(sort(/* ref */ $actual));
        self::assertEqualsCanonicalizing($expected, $actual, $message);
    }

    /**
     * @param array<mixed> $subsetMap
     * @param array<mixed> $containingMap
     */
    public static function assertMapIsSubsetOf(array $subsetMap, array $containingMap): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['subsetMap' => $subsetMap, 'containingMap' => $containingMap]);
        self::assertGreaterThanOrEqual(count($subsetMap), count($containingMap));
        foreach ($subsetMap as $subsetMapKey => $subsetMapVal) {
            AssertMessageStack::newSubScope(/* ref */ $dbgCtx);
            $dbgCtx->add(['subsetMapKey' => $subsetMapKey, 'subsetMapVal' => $subsetMapVal]);
            self::assertArrayHasKey($subsetMapKey, $containingMap);
            self::assertEquals($subsetMapVal, $containingMap[$subsetMapKey]);
            AssertMessageStack::popSubScope(/* ref */ $dbgCtx);
        }
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    public static function assertEqualMaps(array $expected, array $actual): void
    {
        self::assertMapIsSubsetOf($expected, $actual);
        self::assertMapIsSubsetOf($actual, $expected);
    }

    /**
     * @param array<string|int, mixed> $idToXyzMap
     *
     * @return string[]
     */
    public static function getIdsFromIdToMap(array $idToXyzMap): array
    {
        /** @var string[] $result */
        $result = [];
        foreach ($idToXyzMap as $id => $_) {
            $result[] = strval($id);
        }
        return $result;
    }

    public static function buildTracerForTests(?EventSinkInterface $eventSink = null): TracerBuilderForTests
    {
        return TracerBuilderForTests::startNew()
                                    ->withClock(AmbientContextForTests::clock())
                                    ->withLogSink(NoopLogSink::singletonInstance())
                                    ->withEventSink($eventSink ?? NoopEventSink::singletonInstance());
    }

    public static function getParentId(ExecutionSegmentDto $execSegData): ?string
    {
        if ($execSegData instanceof SpanDto) {
            return $execSegData->parentId;
        }

        self::assertInstanceOf(TransactionDto::class, $execSegData, DbgUtil::getType($execSegData));
        return $execSegData->parentId;
    }

    /** @noinspection PhpIfWithCommonPartsInspection */
    public static function setParentId(ExecutionSegmentDto $execSegData, ?string $newParentId): void
    {
        if ($execSegData instanceof SpanDto) {
            self::assertNotNull($newParentId);
            $execSegData->parentId = $newParentId;
        } else {
            self::assertInstanceOf(TransactionDto::class, $execSegData, DbgUtil::getType($execSegData));
            $execSegData->parentId = $newParentId;
        }

        self::assertSame($newParentId, self::getParentId($execSegData));
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
     * @return iterable<array{bool}>
     */
    public function boolDataProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     *
     * @return Logger
     */
    protected function getLogger(string $namespace, string $fqClassName, string $srcCodeFile): Logger
    {
        if ($this->logger === null) {
            $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
                LogCategoryForTests::TEST,
                $namespace,
                $fqClassName,
                $srcCodeFile
            )->addContext('this', $this);
        }
        self::assertNotNull($this->logger);

        return $this->logger;
    }

    /**
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     *
     * @return Logger
     */
    public static function getLoggerStatic(string $namespace, string $fqClassName, string $srcCodeFile): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST,
            $namespace,
            $fqClassName,
            $srcCodeFile
        );
    }

    public static function dummyAssert(): void
    {
        self::assertTrue(true);
    }

    /**
     * @param mixed  $expected
     * @param mixed  $actual
     */
    public static function assertSameNullness($expected, $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['$expected' => $expected, '$actual' => $actual]);
        self::assertSame($expected === null, $actual === null);
    }

    /**
     * @param mixed              $actual
     *
     * @phpstan-assert int|float $actual
     */
    public static function assertIsNumber($actual): void
    {
        self::assertThat($actual, Assert::logicalOr(new IsType(IsType::TYPE_INT), new IsType(IsType::TYPE_FLOAT)));
    }

    /**
     * @param int|float  $rangeBegin
     * @param int|float  $actual
     * @param int|float  $rangeEnd
     */
    public static function assertInClosedRange($rangeBegin, $actual, $rangeEnd): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['rangeBegin' => $rangeBegin, 'actual' => $actual, 'rangeEnd' => $rangeEnd]);
        self::assertGreaterThanOrEqual($rangeBegin, $actual);
        self::assertLessThanOrEqual($rangeEnd, $actual);
    }

    public static function assertLessThanOrEqualTimestamp(float $before, float $after): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(
            [
                'before'         => TimeUtilForTests::timestampToLoggable($before),
                'after'          => TimeUtilForTests::timestampToLoggable($after),
                'after - before' => TimeUtilForTests::timestampToLoggable($after - $before),
            ]
        );
        self::assertThat($before, TestCase::logicalOr(new IsEqual($after, /* delta: */ self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS), new LessThan($after)));
    }

    public static function assertTimestampInRange(float $pastTimestamp, float $timestamp, float $futureTimestamp): void
    {
        TestCaseBase::assertLessThanOrEqualTimestamp($pastTimestamp, $timestamp);
        TestCaseBase::assertLessThanOrEqualTimestamp($timestamp, $futureTimestamp);
    }

    public static function calcEndTime(ExecutionSegmentDto $timedData): float
    {
        return $timedData->timestamp + TimeUtil::millisecondsToMicroseconds($timedData->duration);
    }

    /**
     * @param TransactionDto         $transaction
     * @param array<string, SpanDto> $idToSpan
     * @param ?bool                  $flakyAssertionsEnabled
     */
    protected static function assertValidTransactionAndSpans(TransactionDto $transaction, array $idToSpan, ?bool $flakyAssertionsEnabled = null): void
    {
        TraceValidator::validate(new TraceActual([$transaction->id => $transaction], $idToSpan), /* expectations */ null, $flakyAssertionsEnabled);
    }

    /**
     * @template T
     *
     * @param Optional<T> $expected
     * @param T           $actual
     */
    public static function assertSameExpectedOptional(Optional $expected, $actual): void
    {
        if ($expected->isValueSet()) {
            self::assertSame($expected->getValue(), $actual);
        }
    }

    /**
     * @param string|int           $expectedKey
     * @param mixed                $expectedVal
     * @param array<string, mixed> $actualArray
     */
    public static function assertSameValueInArray($expectedKey, $expectedVal, array $actualArray): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['expectedKey' => $expectedKey, 'expectedVal' => $expectedVal, 'actualArray' => $actualArray]);
        self::assertArrayHasKey($expectedKey, $actualArray);
        self::assertSame($expectedVal, $actualArray[$expectedKey]);
    }

    /**
     * @param string               $expectedKey
     * @param mixed                $expectedVal
     * @param array<string, mixed> $actualArray
     */
    public static function assertEqualValueInArray(string $expectedKey, $expectedVal, array $actualArray): void
    {
        self::assertArrayHasKey($expectedKey, $actualArray);
        self::assertEquals($expectedVal, $actualArray[$expectedKey]);
    }

    /**
     * @template T of int|float
     *
     * @param T $rangeBegin
     * @param T $val
     * @param T $rangeInclusiveEnd
     */
    public static function assertInRangeInclusive($rangeBegin, $val, $rangeInclusiveEnd): void
    {
        self::assertGreaterThanOrEqual($rangeBegin, $val);
        self::assertLessThanOrEqual($rangeInclusiveEnd, $val);
    }

    /**
     * @param string $optName
     * @param mixed  $val
     *
     * @return bool
     */
    public static function equalsConfigDefaultValue(string $optName, $val): bool
    {
        $optMeta = AllOptionsMetadata::get()[$optName];
        if (!$optMeta instanceof OptionWithDefaultValueMetadata) {
            return false;
        }
        return $val == $optMeta->defaultValue();
    }

    /**
     * @param string|int          $key
     * @param mixed               $expectedValue
     * @param array<mixed> $array
     * @param string              $message
     *
     * @return void
     */
    public static function assertArrayHasKeyWithValue($key, $expectedValue, array $array, string $message = ''): void
    {
        self::assertArrayHasKey($key, $array, $message);
        self::assertSame($expectedValue, $array[$key], $message);
    }

    /**
     * @param array<mixed>|Countable $expected
     * @param array<mixed>|Countable $actual
     */
    public static function assertSameCount($expected, $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['expected' => $expected, 'actual' => $actual]);
        self::assertSame(count($expected), count($actual));
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @param iterable<array<mixed>> $srcDataProvider
     *
     * @return iterable<string, array<mixed>>
     */
    protected static function wrapDataProviderFromKeyValueMapToNamedDataSet(iterable $srcDataProvider): iterable
    {
        $dataSetIndex = 0;
        foreach ($srcDataProvider as $namedValuesMap) {
            $dataSetName = '#' . $dataSetIndex;
            $dataSetName .= ' ' . LoggableToString::convert($namedValuesMap);
            yield $dataSetName => array_values($namedValuesMap);
            ++$dataSetIndex;
        }
    }

    private static function addMessageStackToException(Exception $ex): void
    {
        AssertMessageStackExceptionHelper::setMessage($ex, $ex->getMessage() . "\n" . 'AssertMessageStack:' . "\n" . AssertMessageStack::formatScopesStackAsString());
    }

    /**
     * @inheritDoc
     *
     * @return never-return
     */
    public static function fail(string $message = ''): void
    {
        try {
            Assert::fail($message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $condition
     */
    public static function assertTrue($condition, string $message = ''): void
    {
        try {
            Assert::assertTrue($condition, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed>|Countable $haystack
     */
    public static function assertCount(int $expectedCount, $haystack, string $message = ''): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['expectedCount' => $expectedCount, 'count($haystack)' => count($haystack), 'haystack' => $haystack]);
        try {
            Assert::assertCount($expectedCount, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    public static function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertGreaterThanOrEqual($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $condition
     */
    public static function assertNotFalse($condition, string $message = ''): void
    {
        try {
            Assert::assertNotFalse($condition, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertNotNull($actual, string $message = ''): void
    {
        try {
            Assert::assertNotNull($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertSame($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertSame($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertNotEquals($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertNotEquals($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertNotEmpty($actual, string $message = ''): void
    {
        try {
            Assert::assertNotEmpty($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array-key    $key
     * @param array<mixed> $array
     */
    public static function assertArrayHasKey($key, $array, string $message = ''): void
    {
        try {
            Assert::assertArrayHasKey($key, $array, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    public static function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertGreaterThan($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertEquals($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertEquals($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertNull($actual, string $message = ''): void
    {
        try {
            Assert::assertNull($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertIsString($actual, string $message = ''): void
    {
        try {
            Assert::assertIsString($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $value
     */
    public static function assertThat($value, Constraint $constraint, string $message = ''): void
    {
        try {
            Assert::assertThat($value, $constraint, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertIsInt($actual, string $message = ''): void
    {
        try {
            Assert::assertIsInt($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertIsBool($actual, string $message = ''): void
    {
        try {
            Assert::assertIsBool($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    public static function assertLessThanOrEqual($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertLessThanOrEqual($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertIsArray($actual, string $message = ''): void
    {
        try {
            Assert::assertIsArray($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    public static function assertEmpty($actual, string $message = ''): void
    {
        try {
            Assert::assertEmpty($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed           $needle
     * @param iterable<mixed> $haystack
     */
    public static function assertContains($needle, iterable $haystack, string $message = ''): void
    {
        try {
            Assert::assertContains($needle, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed>|Countable $haystack
     */
    public static function assertNotCount(int $expectedCount, $haystack, string $message = ''): void
    {
        try {
            Assert::assertNotCount($expectedCount, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    public static function assertLessThan($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertLessThan($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $condition
     */
    public static function assertFalse($condition, string $message = ''): void
    {
        try {
            Assert::assertFalse($condition, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed>|Countable $expected
     * @param array<mixed>|Countable $actual
     */
    public static function assertSameSize($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertSameSize($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertEqualsCanonicalizing($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertEqualsCanonicalizing($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @template TExpected of object
     *
     * @param class-string<TExpected> $expected
     * @param mixed                   $actual
     *
     * @phpstan-assert TExpected $actual
     */
    public static function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertInstanceOf($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }
}
