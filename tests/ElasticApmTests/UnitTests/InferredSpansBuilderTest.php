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

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\InferredSpansBuilder;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\TracerInterface;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\TestsSharedCode\StackTraceLimitTestSharedCode;
use ElasticApmTests\UnitTests\Util\MockClockTracerUnitTestCaseBase;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\InferredSpanExpectationsBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\StackTraceExpectations;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TimeUtilForTests;
use ElasticApmTests\Util\TraceActual;
use ElasticApmTests\Util\TracerBuilderForTests;
use ElasticApmTests\Util\TraceValidator;
use ElasticApmTests\Util\TransactionDto;

class InferredSpansBuilderTest extends MockClockTracerUnitTestCaseBase
{
    private const DEFAULT_MIN_DURATION = 0.0;

    private const PARENT_INDEX_KEY = 'PARENT_INDEX';
    private const NAME_KEY = 'NAME';
    private const START_TIME_KEY = 'START_TIME';
    private const END_TIME_KEY = 'END_TIME';
    private const CHILDREN_INDEXES_KEY = 'CHILDREN_INDEXES';
    private const STACK_TRACE_KEY = 'STACK_TRACE';

    private const INPUT_STACK_TRACES_KEY = 'INPUT_STACK_TRACES';
    private const EXPECTED_SPANS_KEY = 'EXPECTED_SPANS';
    private const EXPECTED_STACK_TRACES_KEY = 'EXPECTED_STACK_TRACES';
    private const INPUT_OPTIONS_KEY = 'INPUT_OPTIONS';

    private const UNKNOWN_SPAN_NAME = 'unknown inferred span';

    /**
     * Tests in this class specifiy expected spans individually
     * so Span Compression feature should be disabled.
     *
     * @inheritDoc
     */
    protected function isSpanCompressionCompatible(): bool
    {
        return false;
    }

    private static function newInferredSpansBuilder(TracerInterface $tracer): InferredSpansBuilder
    {
        self::assertInstanceOf(Tracer::class, $tracer);
        self::assertInstanceOf(Transaction::class, $tracer->getCurrentTransaction());
        self::assertTrue($tracer->getCurrentTransaction()->isSampled());
        return new InferredSpansBuilder($tracer);
    }

    /**
     * @param callable(InferredSpansBuilder): void $callWithInferredSpansBuilder
     *
     * @return void
     */
    private function withInferredSpansBuilderDuringTransaction(callable $callWithInferredSpansBuilder): void
    {
        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $inferredSpansBuilder = self::newInferredSpansBuilder($this->tracer);

        $callWithInferredSpansBuilder($inferredSpansBuilder);

        $inferredSpansBuilder->close();
        $tx->end();
    }

    public function testNoStackTraces(): void
    {
        // Act
        $this->withInferredSpansBuilderDuringTransaction(
            function (InferredSpansBuilder $builder): void {
            }
        );

        // Assert
        self::assertCount(1, $this->mockEventSink->idToTransaction());
        self::assertCount(0, $this->mockEventSink->idToSpan());
    }

    public function testOneEmptyStackTrace(): void
    {
        // Act
        $this->withInferredSpansBuilderDuringTransaction(
            function (InferredSpansBuilder $builder): void {
                $builder->addStackTrace([]);
            }
        );

        // Assert
        self::assertCount(1, $this->mockEventSink->idToTransaction());
        self::assertCount(0, $this->mockEventSink->idToSpan());
    }

    /**
     * @param InferredSpansBuilder $builder
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    private function helperForTestOneStackTrace(InferredSpansBuilder $builder): array
    {
        $stackTrace = $builder->captureStackTrace(/* offset */ 0);
        $builder->addStackTrace($stackTrace);
        return $stackTrace;
    }

    public function testOneStackTrace(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        $expectedTimestampMicroseconds = 123.0;
        $expectedDurationMilliseconds = self::DEFAULT_MIN_DURATION + 45.0;

        /** @var null|ClassicFormatStackTraceFrame[] $expectedStackTrace */
        $expectedStackTrace = null;
        //
        // Act
        //
        $this->withInferredSpansBuilderDuringTransaction(
            function (InferredSpansBuilder $builder) use (&$expectedStackTrace, $expectedTimestampMicroseconds, $expectedDurationMilliseconds): void {
                $this->mockClock->fastForwardMicroseconds($expectedTimestampMicroseconds);
                $expectedStackTrace = $this->helperForTestOneStackTrace($builder);
                $this->mockClock->fastForwardMilliseconds($expectedDurationMilliseconds);
            }
        );
        $dbgCtx->add(['expectedStackTrace' => $expectedStackTrace]);
        self::assertNotNull($expectedStackTrace);

        //
        // Assert
        //
        self::assertCount(1, $this->mockEventSink->idToTransaction());
        $span = $this->mockEventSink->singleSpan();
        $dbgCtx->add(['span' => $span]);

        // Top 3 frames are from this class:
        //      testOneStackTrace >>> withBuilderDuringTransaction >>> {closure} >>> helperForTestOneStackTrace
        foreach (RangeUtil::generateUpTo(4) as $i) {
            self::assertSame(__FILE__, $expectedStackTrace[$i]->file);
            self::assertSame(__CLASS__, $expectedStackTrace[$i]->class);
        }
        self::assertNotEqualsEx(__FILE__, $expectedStackTrace[4]->file);
        self::assertNotEqualsEx(__CLASS__, $expectedStackTrace[4]->class);

        self::assertSame('InferredSpansBuilderTest', ClassNameUtil::fqToShort(__CLASS__));
        self::assertSame('helperForTestOneStackTrace', $expectedStackTrace[0]->function);
        self::assertSame('InferredSpansBuilderTest::helperForTestOneStackTrace', $span->name);
        self::assertSame(InferredSpanExpectationsBuilder::DEFAULT_SPAN_TYPE, $span->type);
        self::assertSame($expectedTimestampMicroseconds, $span->timestamp);
        self::assertSame($expectedDurationMilliseconds, $span->duration);
        TraceValidator::validate(new TraceActual($this->mockEventSink->idToTransaction(), $this->mockEventSink->idToSpan()));

        $expectedStackTraceConvertedToApm = StackTraceUtil::convertClassicToApmFormat($expectedStackTrace, /* maxNumberOfFrames */ null);
        self::assertNotNull($span->stackTrace);
        StackTraceExpectations::fromFrames($expectedStackTraceConvertedToApm)->assertMatches($span->stackTrace);
    }

    private function charDiagramFuncNameToStackTraceFrame(string $funcName): ClassicFormatStackTraceFrame
    {
        $result = new ClassicFormatStackTraceFrame();
        $result->function = $funcName;
        $result->file = $funcName . '.php';
        $result->line = ord($funcName) - ord('a') + 1;
        return $result;
    }

    /**
     * @param string[] $inputStackTracesLines
     *
     * @return ClassicFormatStackTraceFrame[][]
     */
    private function charDiagramProcessInputStackTraces(array $inputStackTracesLines): array
    {
        //  bb bb
        // aaaaaa

        if (ArrayUtil::isEmpty($inputStackTracesLines)) {
            return [];
        }

        $result = [];
        $stackTracesCount = strlen(ArrayUtilForTests::getLastValue($inputStackTracesLines));
        foreach ($inputStackTracesLines as $line) {
            self::assertLessThanOrEqual($stackTracesCount, strlen($line));
        }

        for ($columnIndex = 0; $columnIndex < $stackTracesCount; ++$columnIndex) {
            /** @var ClassicFormatStackTraceFrame[] $newStackTrace */
            $newStackTrace = [];
            $hasReachedTopOfStackTrace = false;
            foreach (ArrayUtilForTests::iterateListInReverse($inputStackTracesLines) as $line) {
                if (strlen($line) >= ($columnIndex + 1) && !TextUtil::isEmptyString(trim($line[$columnIndex]))) {
                    self::assertFalse($hasReachedTopOfStackTrace);
                    $newStackTrace[] = self::charDiagramFuncNameToStackTraceFrame(/* funcName */ $line[$columnIndex]);
                } else {
                    $hasReachedTopOfStackTrace = true;
                }
            }
            $result[] = array_reverse($newStackTrace);
        }

        return $result;
    }

    /**
     * @param string[] $expectedSpansLines
     *
     * @return array<string, mixed>[]
     */
    private function charDiagramProcessExpectedSpans(array $expectedSpansLines): array
    {
        // aaaaaa
        //  bb
        //     bb

        $result = [];

        $findLetterPos = function (string $text, bool $isFirstPos): ?int {
            $range = $isFirstPos ? RangeUtil::generateUpTo(strlen($text)) : RangeUtil::generateDownFrom(strlen($text));
            foreach ($range as $i) {
                if (ctype_alnum($text[$i])) {
                        return $i;
                }
            }
            return null;
        };

        $findParentLineIndex = function (int $childLineIndex, int $childStartColumn) use ($expectedSpansLines): ?int {
            foreach (RangeUtil::generateDownFrom($childLineIndex) as $i) {
                $line = $expectedSpansLines[$i];
                if (strlen($line) > $childStartColumn && ctype_alnum($line[$childStartColumn])) {
                    return $i;
                }
            }
            return null;
        };

        foreach (RangeUtil::generateUpTo(count($expectedSpansLines)) as $lineIndex) {
            $line = $expectedSpansLines[$lineIndex];
            $firstLetterPos = $findLetterPos($line, /* isFirstPos */ true);
            self::assertNotNull($firstLetterPos);
            $lastLetterPos = $findLetterPos($line, /* isFirstPos */ false);
            self::assertNotNull($lastLetterPos);
            $newSpan = [];
            $funcName = $line[$firstLetterPos];
            $newSpan[self::NAME_KEY] = $funcName;
            $newSpan[self::START_TIME_KEY] = floatval($firstLetterPos);
            $newSpan[self::END_TIME_KEY] = $lastLetterPos + 1;
            $parentLineIndex = $findParentLineIndex($lineIndex, $firstLetterPos);
            if ($parentLineIndex !== null) {
                $newSpan[self::PARENT_INDEX_KEY] = $parentLineIndex;
                $parentSpan = &$result[$parentLineIndex];
                $newSpan[self::STACK_TRACE_KEY]
                    = array_merge([$funcName], $parentSpan[self::STACK_TRACE_KEY]);
                if (!array_key_exists(self::CHILDREN_INDEXES_KEY, $parentSpan)) { // @phpstan-ignore-line
                    $parentSpan[self::CHILDREN_INDEXES_KEY] = [];
                }
                $parentSpan[self::CHILDREN_INDEXES_KEY][] = $lineIndex;
                unset($parentSpan);
            } else {
                $newSpan[self::STACK_TRACE_KEY] = [$funcName];
            }
            self::assertCount($lineIndex, $result);
            $result[] = $newSpan;
        }

        return $result;
    }

    /**
     * @param SpanDto                $span
     * @param TransactionDto         $tx
     * @param array<string, SpanDto> $idToSpan
     *
     * @return int
     */
    private static function calcSpanDistanceToTransaction(SpanDto $span, TransactionDto $tx, array $idToSpan): int
    {
        if ($span->parentId === $tx->id) {
            return 1;
        }
        self::assertArrayHasKey($span->parentId, $idToSpan);
        return self::calcSpanDistanceToTransaction($idToSpan[$span->parentId], $tx, $idToSpan) + 1;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return void
     */
    private function charDiagramTestImpl(array $args): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        /** @var array<string, mixed> $inputOptions */
        $inputOptions = ArrayUtil::getValueIfKeyExistsElse(self::INPUT_OPTIONS_KEY, $args, []);
        $this->setUpTestEnv(
            function (TracerBuilderForTests $tracerBuilder) use ($inputOptions): void {
                // Enable span stack trace collection for span with any duration
                $tracerBuilder->withConfig(OptionNames::SPAN_STACK_TRACE_MIN_DURATION, '0');
                foreach ($inputOptions as $optName => $optVal) {
                    /** @phpstan-ignore-next-line */
                    $tracerBuilder->withConfig($optName, strval($optVal));
                }
            }
        );

        /** @var string[] $inputStackTracesLines */
        $inputStackTracesLines = $args[self::INPUT_STACK_TRACES_KEY];
        /** @var string[] $expectedSpansLines */
        $expectedSpansLines = $args[self::EXPECTED_SPANS_KEY];

        $inputStackTraces = self::charDiagramProcessInputStackTraces($inputStackTracesLines);

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $inferredSpansBuilder = self::newInferredSpansBuilder($this->tracer);
        foreach ($inputStackTraces as $inputStackTrace) {
            $inferredSpansBuilder->addStackTrace($inputStackTrace);
            $this->mockClock->fastForwardMicroseconds(1);
        }
        $inferredSpansBuilder->close();
        $tx->end();

        TraceValidator::validate(new TraceActual($this->mockEventSink->idToTransaction(), $this->mockEventSink->idToSpan()));
        $actualTx = $this->mockEventSink->singleTransaction();
        $actualIdToSpan = $this->mockEventSink->idToSpan();

        $expectedSpans = self::charDiagramProcessExpectedSpans($expectedSpansLines);
        self::assertCount(1, $this->mockEventSink->idToTransaction());
        $dbgCtx->add(['expectedSpans' => $expectedSpans, 'actualIdToSpan' => $actualIdToSpan]);
        self::assertSame(count($expectedSpans), count($actualIdToSpan));

        /** @var array<string, int> $actualSpanIdToDistanceToTransaction */
        $actualSpanIdToDistanceToTransaction = [];
        foreach ($actualIdToSpan as $id => $span) {
            $actualSpanIdToDistanceToTransaction[$id] = self::calcSpanDistanceToTransaction($span, $actualTx, $actualIdToSpan);
        }

        $actualSpansSortedAsExpected = array_values($actualIdToSpan);
        usort(
            $actualSpansSortedAsExpected /* <- ref */,
            function (SpanDto $spanA, SpanDto $spanB) use ($actualSpanIdToDistanceToTransaction): int {
                $startTimeCmp = TimeUtilForTests::compareTimestamps($spanA->timestamp, $spanB->timestamp);
                if ($startTimeCmp !== 0) {
                    return $startTimeCmp;
                }
                return $actualSpanIdToDistanceToTransaction[$spanA->id]
                       - $actualSpanIdToDistanceToTransaction[$spanB->id];
            }
        );
        $dbgCtx->add(['actualSpansSortedAsExpected' => $actualSpansSortedAsExpected]);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($expectedSpans)) as $i) {
            $dbgCtx->clearCurrentSubScope(['i' => $i]);
            /** @var array<string, mixed> $expectedSpan */
            $expectedSpan = $expectedSpans[$i];
            $dbgCtx->add(['expectedSpan' => $expectedSpan]);
            /** @var SpanDto $actualSpan */
            $actualSpan = $actualSpansSortedAsExpected[$i];
            $dbgCtx->add(['actualSpan' => $actualSpan]);
            self::assertSame($expectedSpan[self::NAME_KEY], $actualSpan->name);
            self::assertSame(InferredSpanExpectationsBuilder::DEFAULT_SPAN_TYPE, $actualSpan->type);
            if (array_key_exists(self::PARENT_INDEX_KEY, $expectedSpan)) {
                $expectedParentSpanId = $actualSpansSortedAsExpected[$expectedSpan[self::PARENT_INDEX_KEY]]->id;
                self::assertSame($expectedParentSpanId, $actualSpan->parentId);
            } else {
                self::assertSame($actualTx->id, $actualSpan->parentId);
            }
            self::assertSame($expectedSpan[self::START_TIME_KEY], $actualSpan->timestamp);
            $durationInMicroSeconds = $expectedSpan[self::END_TIME_KEY] - $expectedSpan[self::START_TIME_KEY];
            self::assertSame(TimeUtil::microsecondsToMilliseconds($durationInMicroSeconds), $actualSpan->duration);

            /** @var string[] $expectedStackTraceAsLetterList */
            $expectedStackTraceAsLetterList = $expectedSpan[self::STACK_TRACE_KEY];

            if (array_key_exists(self::EXPECTED_STACK_TRACES_KEY, $args)) {
                /** @var string[][] $expectedSpansStackTraces */
                $expectedSpansStackTraces = $args[self::EXPECTED_STACK_TRACES_KEY];
                $expectedStackTraceAsLetterList = $expectedSpansStackTraces[$i];
            }
            /** @var ClassicFormatStackTraceFrame[] $expectedStackTraceClassicFormat */
            $expectedStackTraceClassicFormat = [];
            foreach ($expectedStackTraceAsLetterList as $funcName) {
                $expectedStackTraceClassicFormat[] = self::charDiagramFuncNameToStackTraceFrame($funcName);
            }
            self::assertNotNull($actualSpan->stackTrace);
            StackTraceExpectations::fromFrames(StackTraceUtil::convertClassicToApmFormat($expectedStackTraceClassicFormat, /* maxNumberOfFrames */ null))->assertMatches($actualSpan->stackTrace);
        }
        $dbgCtx->popSubScope();
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testBasicCallTree(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L150
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' cc ',
                    ' bbb',
                    'aaaa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'aaaa',
                    ' bbb',
                    ' cc',
                ],
            ]
        );
    }

    public function testCharDiagramEmptyStackTrace(): void
    {
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [''],
                self::EXPECTED_SPANS_KEY => [],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testTwoDistinctInvocationsOfMethodShouldNotBeFoldedIntoOne(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L138
        // testTwoDistinctInvocationsOfMethodBShouldNotBeFoldedIntoOne
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' bb bb',
                    'aaaaaa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'aaaaaa',
                    ' bb',
                    '    bb',
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testShouldNotCreateInferredSpansForPillars(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L167
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' dd',
                    ' cc',
                    ' bb',
                    'aaaa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'aaaa',
                    ' dd',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['a'],
                    ['d', 'c', 'b', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testSpanWithDurationLessThanMin(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L185
        // testRemoveNodesWithCountOne
        $this->charDiagramTestImpl(
            [
                self::INPUT_OPTIONS_KEY => [
                    OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION => TimeUtil::microsecondsToMilliseconds(2),
                ],
                self::INPUT_STACK_TRACES_KEY => [
                    'b ',
                    'aa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'aa',
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testSameTopOfStackDifferentBottom(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L197
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    'cccc',
                    'aabb',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'cc',
                    '  cc',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['c', 'a'],
                    ['c', 'b'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testStackTraceWithRecursion(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L210
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    'bbccbbcc',
                    'bbbbbbbb',
                    'aaaaaaaa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'bbbbbbbb',
                    'bb',
                    '  cc',
                    '    bb',
                    '      cc',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'b', 'a'],
                    ['b', 'b', 'a'],
                    ['c', 'b', 'a'],
                    ['b', 'b', 'a'],
                    ['c', 'b', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans1(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L239
        // testCallTreeWithSpanActivations
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '    cc ee   ',
                    '   bbb dd   ',
                    ' a aaaaaa a ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' a',
                    '   aaaaaa   ',
                    '   bbb      ',
                    '    cc      ',
                    '       ee   ',
                    '          a ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [          'a'],
                    [          'a'],
                    [     'b', 'a'],
                    ['c', 'b', 'a'],
                    ['e', 'd', 'a'],
                    [          'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans2(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L270
        // testDeactivationBeforeEnd
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '   dd      ',
                    '   cccc c  ',
                    '   bbbb bb ',
                    ' a aaaa aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' a          ',
                    '   cccc     ',
                    '   dd       ',
                    '        bb  ',
                    '        c   ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [               'a'],
                    [     'c', 'b', 'a'],
                    ['d', 'c', 'b', 'a'],
                    [          'b', 'a'],
                    [     'c', 'b', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans3(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L299
        // testDectivationBeforeEnd2
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '   bbbb b     ',
                    ' a aaaa a a a ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' a            ',
                    '   bbbb       ',
                    '        b     ',
                    '          a   ',
                    '            a ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['b', 'a'],
                    ['b', 'a'],
                    [     'a'],
                    [     'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans4(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L324
        // testDectivationBeforeEnd_DontStealChildIdsOfUnrelatedActivations
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '      c c ',
                    '      b b ',
                    'a   a a aa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'a         ',
                    '    a     ',
                    '      c   ',
                    '        aa',
                    '        c ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [          'a'],
                    [          'a'],
                    ['c', 'b', 'a'],
                    [          'a'],
                    ['c', 'b', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans5(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L352
        // testDectivationBeforeEnd_DontStealChildIdsOfUnrelatedActivations_Nested
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '       c  c ',
                    '       b  b ',
                    'a   a  a  aa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'a           ',
                    '    a       ',
                    '       c    ',
                    '          aa',
                    '          c ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [          'a'],
                    [          'a'],
                    ['c', 'b', 'a'],
                    [          'a'],
                    ['c', 'b', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans6(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L379
        // testActivationAfterMethodEnds
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    'bb   ',
                    'aa a ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'bb   ',
                    '   a ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'b', 'a'],
                    [          'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans7(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L399
        // testActivationBetweenMethods
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    'bb   ',
                    'aa  a',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'bb   ',
                    '    a',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['b', 'a'],
                    [     'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans8(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L420
        // testActivationBetweenMethods_AfterFastMethod
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' c   ',
                    'bb   ',
                    'aa  a',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'bb   ',
                    ' c   ',
                    '    a',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'b', 'a'],
                    ['c', 'b', 'a'],
                    [          'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans9(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L442
        // testActivationBetweenFastMethods
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    'c  d   ',
                    'b  b   ',
                    'a  a  a',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'c      ',
                    '   d   ',
                    '      a',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['c', 'b', 'a'],
                    ['d', 'b', 'a'],
                    [          'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans10(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L464
        // testActivationBetweenMethods_WithCommonAncestor
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '  c     f  g ',
                    'bbb  e  d  dd',
                    'aaa  a  a  aa',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'bbb          ',
                    '  c          ',
                    '     e       ',
                    '        f    ',
                    '           dd',
                    '           g ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'b', 'a'],
                    ['c', 'b', 'a'],
                    [     'e', 'a'],
                    ['f', 'd', 'a'],
                    [     'd', 'a'],
                    ['g', 'd', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans11(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L490
        // testNestedActivation
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    'a  a  a',
                ],
                self::EXPECTED_SPANS_KEY => [
                    'a      ',
                    '   a   ',
                    '      a',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['a'],
                    ['a'],
                    ['a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans12(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L510
        // testNestedActivationAfterMethodEnds_RootChangesToC
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' bbb        ',
                    ' aaa  ccc   ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' bbb        ',
                    '      ccc   ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['b', 'a'],
                    [     'c'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans13(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L536
        // testRegularActivationFollowedByNestedActivationAfterMethodEnds
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '   d          ',
                    ' b b b        ',
                    ' a a a  ccc   ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' b            ',
                    '   d          ',
                    '     b        ',
                    '        ccc   ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'b', 'a'],
                    ['d', 'b', 'a'],
                    [     'b', 'a'],
                    [          'c'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans14(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L564
        // testNestedActivationAfterMethodEnds_CommonAncestorA
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '  b b b  ccc    ',
                    ' aa a a  aaa  a ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aa             ',
                    '  b             ',
                    '    b           ',
                    '      b         ',
                    '         ccc    ',
                    '              a ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['b', 'a'],
                    ['b', 'a'],
                    ['b', 'a'],
                    ['c', 'a'],
                    [     'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans15(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L597
        // testActivationAfterMethodEnds_RootChangesToB
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '     ccc  ',
                    ' aaa bbb  ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aaa      ',
                    '     ccc  ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['c', 'b'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans16(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L622
        // testActivationAfterMethodEnds_SameRootDeeperStack
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '     ccc  ',
                    ' aaa aaa  ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aaa      ',
                    '     ccc  ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['c', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans17(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L645
        // testActivationBeforeMethodStarts
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '   bbb   ',
                    ' a aaa a ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' a       ',
                    '   bbb   ',
                    '       a ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['b', 'a'],
                    [     'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans18(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L670
        // testDectivationAfterEnd
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '     dd     ',
                    '   c ccc    ',
                    '  bb bbb    ',
                    ' aaa aaa aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aaa        ',
                    '  bb        ',
                    '   c        ',
                    '     ccc    ',
                    '     dd     ',
                    '         aa ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [               'a'],
                    [          'b', 'a'],
                    [     'c', 'b', 'a'],
                    [     'c', 'b', 'a'],
                    ['d', 'c', 'b', 'a'],
                    [               'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans19(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L693
        // testCallTreeActivationAsParentOfFastSpan
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '    b    ',
                    ' aa a aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aa      ',
                    '    b    ',
                    '      aa ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['b', 'a'],
                    [     'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans20(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L708
        // testCallTreeActivationAsChildOfFastSpan
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '   c  c   ',
                    '   b  b   ',
                    ' aaa  aaa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aaa      ',
                    '   c      ',
                    '      aaa ',
                    '      c   ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [          'a'],
                    ['c', 'b', 'a'],
                    [          'a'],
                    ['c', 'b', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans21(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L725
        // testCallTreeActivationAsLeaf
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' aa  aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aa     ',
                    '     aa ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['a'],
                    ['a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans22(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L740
        // testCallTreeMultipleActivationsAsLeaf
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    ' aa  aaa  aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aa          ',
                    '     aaa     ',
                    '          aa ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['a'],
                    ['a'],
                    ['a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans23(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L755
        // testCallTreeMultipleActivationsAsLeafWithExcludedParent
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '  b  b c  c  ',
                    ' aa  aaa  aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aa          ',
                    '  b          ',
                    '     aaa     ',
                    '     b       ',
                    '       c     ',
                    '          aa ',
                    '          c  ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    ['b', 'a'],
                    [     'a'],
                    ['b', 'a'],
                    ['c', 'a'],
                    [     'a'],
                    ['c', 'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans24(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L773
        // testCallTreeMultipleActivationsWithOneChild
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '         bb    ',
                    ' aa  aaa aa aa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    ' aa            ',
                    '     aaa       ',
                    '         bb    ',
                    '            aa ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    [     'a'],
                    [     'a'],
                    ['b', 'a'],
                    [     'a'],
                ],
            ]
        );
    }

    /** @noinspection SpellCheckingInspection, RedundantSuppression */
    public function testWithRegularSpans25(): void
    {
        // https://github.com/elastic/apm-agent-java/blob/v1.34.1/apm-agent-plugins/apm-profiling-plugin/src/test/java/co/elastic/apm/agent/profiler/CallTreeTest.java#L799
        // testNestedActivationBeforeCallTree
        $this->charDiagramTestImpl(
            [
                self::INPUT_STACK_TRACES_KEY => [
                    '  aaa ',
                ],
                self::EXPECTED_SPANS_KEY => [
                    '  aaa ',
                ],
                self::EXPECTED_STACK_TRACES_KEY => [
                    ['a'],
                ],
            ]
        );
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestStackTraceLimit(): iterable
    {
        return StackTraceLimitTestSharedCode::dataProviderForTestVariousConfigValues();
    }

    private function profilingInferredSpansMinDurationInMillisecondsConfig(): float
    {
        self::assertInstanceOf(Tracer::class, $this->tracer);
        /** @var Tracer $tracer */
        $tracer = $this->tracer;
        return $tracer->getConfig()->profilingInferredSpansMinDurationInMilliseconds();
    }

    private function spanStackTraceMinDurationConfig(): float
    {
        self::assertInstanceOf(Tracer::class, $this->tracer);
        /** @var Tracer $tracer */
        $tracer = $this->tracer;
        return $tracer->getConfig()->spanStackTraceMinDuration();
    }

    private static function genFrameForSpanWithStackDepth(int $spanIndex, int $stackFrameIndex, int $stackDepth): ClassicFormatStackTraceFrame
    {
        $result = new ClassicFormatStackTraceFrame('file_for_span_' . $spanIndex . '.php', $stackFrameIndex + 1);

        // Bottom frame is location only
        if ($stackFrameIndex === ($stackDepth - 1)) {
            return $result;
        }

        $result->class = ($stackFrameIndex % 2) === 0 ? ('class_for_span_' . $spanIndex) : null;
        $result->isStaticMethod = $result->class === null ? null : ($stackFrameIndex % 3 === 0);
        $result->function = 'function_for_span_' . $stackFrameIndex . ($stackFrameIndex === 0 ? '' : '_frame_' . $stackFrameIndex);
        return $result;
    }

    private static function expectedSpanNameWithStack(int $spanIndex, int $stackDepth): string
    {
        $topFrame = self::genFrameForSpanWithStackDepth($spanIndex, /* stackFrameIndex */ 0, $stackDepth);
        /** @var class-string $className */
        $className = $topFrame->class;
        $shortClassName = $topFrame->class === null ? null : ClassNameUtil::fqToShort($className);
        $spanName = StackTraceUtil::buildApmFormatFunctionForClassMethod($shortClassName, $topFrame->isStaticMethod, $topFrame->function);
        if ($spanName === null) {
            $spanName = ($topFrame->file !== null && $topFrame->line !== null) ? ($topFrame->file . ':' . $topFrame->line) : self::UNKNOWN_SPAN_NAME;
        }

        self::assertNotNull($spanName);
        return $spanName;
    }

    private function createSpanWithStackDepth(InferredSpansBuilder $builder, int $spanIndex, int $stackDepth): void
    {
        $stackTrace = [];
        foreach (RangeUtil::generateUpTo($stackDepth) as $stackFrameIndex) {
            $stackTrace[] = $this->genFrameForSpanWithStackDepth($spanIndex, $stackFrameIndex, $stackDepth);
        }

        $builder->addStackTrace($stackTrace);
        $this->mockClock->fastForwardMilliseconds(max($this->profilingInferredSpansMinDurationInMillisecondsConfig(), $this->spanStackTraceMinDurationConfig()));
        $builder->addStackTrace($stackTrace);
    }

    /**
     * @dataProvider dataProviderForTestStackTraceLimit
     */
    public function testStackTraceLimit(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        //
        // Arrange
        //
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($testArgs): void {
                self::setConfigIfNotNull($testArgs, OptionNames::STACK_TRACE_LIMIT, $builder);
            }
        );

        $expectedMaxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(StackTraceLimitTestSharedCode::EXPECTED_MAX_NUMBER_OF_FRAMES_KEY);
        $stackDepthVariants = StackTraceLimitTestSharedCode::additionalStackDepthVariants($expectedMaxNumberOfFrames, /* baseStackTraceDepth */ 0);

        //
        // Act
        //
        $this->withInferredSpansBuilderDuringTransaction(
            function (InferredSpansBuilder $builder) use ($stackDepthVariants): void {
                foreach (RangeUtil::generateUpTo(count($stackDepthVariants)) as $spanIndex) {
                    $this->createSpanWithStackDepth($builder, $spanIndex, $stackDepthVariants[$spanIndex]);
                }
            }
        );

        //
        // Assert
        //
        $dbgCtx->add(['dataFromAgent' => $this->mockEventSink->dataFromAgent]);
        self::assertCount(1, $this->mockEventSink->idToTransaction());
        TestCaseBase::assertCount(count($stackDepthVariants), $this->mockEventSink->idToSpan());
        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($stackDepthVariants)) as $spanIndex) {
            $dbgCtx->add(['spanIndex' => $spanIndex]);
            $stackDepth = $stackDepthVariants[$spanIndex];
            $dbgCtx->add(['stackDepth' => $stackDepth]);
            $span = $this->mockEventSink->singleSpanByName(self::expectedSpanNameWithStack($spanIndex, $stackDepth));
            $dbgCtx->add(['span' => $span]);
            TestCaseBase::assertSame(InferredSpanExpectationsBuilder::DEFAULT_SPAN_TYPE, $span->type);
            if ($expectedMaxNumberOfFrames === 0) {
                TestCaseBase::assertNull($span->stackTrace);
                continue;
            }
            TestCaseBase::assertNotNull($span->stackTrace);

            if ($expectedMaxNumberOfFrames !== null) {
                TestCaseBase::assertLessThanOrEqual($expectedMaxNumberOfFrames, count($span->stackTrace));
            }

            $dbgCtx->pushSubScope();
            foreach (RangeUtil::generateUpTo(count($span->stackTrace)) as $stackFrameIndex) {
                $dbgCtx->add(['stackFrameIndex' => $stackFrameIndex]);
                $currentExpectedClassicFormatFrame = self::genFrameForSpanWithStackDepth($spanIndex, $stackFrameIndex, $stackDepth);
                $dbgCtx->add(['currentExpectedClassicFormatFrame' => $currentExpectedClassicFormatFrame]);
                $prevExpectedClassicFormatFrame = $stackFrameIndex === 0 ? null : self::genFrameForSpanWithStackDepth($spanIndex, $stackFrameIndex - 1, $stackDepth);
                $dbgCtx->add(['prevExpectedClassicFormatFrame' => $prevExpectedClassicFormatFrame]);
                StackTraceFrameExpectations::fromClassicFormat($currentExpectedClassicFormatFrame, $prevExpectedClassicFormatFrame)->assertMatches($span->stackTrace[$stackFrameIndex]);
            }
            $dbgCtx->popSubScope();
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestSpanStackTraceMinDuration(): iterable
    {
        return SpanStackTraceMinDurationUnitTest::dataProviderForTestVariousConfigValues();
    }

    /**
     * @dataProvider dataProviderForTestSpanStackTraceMinDuration
     */
    public function testSpanStackTraceMinDuration(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        /**
         * Arrange
         */

        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($testArgs): void {
                $builder->withConfig(OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION, '0');
                self::setConfigIfNotNull($testArgs, OptionNames::SPAN_STACK_TRACE_MIN_DURATION, $builder);
            }
        );

        $expectedSpanStackTraceMinDuration = $testArgs->getFloat(SpanStackTraceMinDurationUnitTest::EXPECTED_SPAN_STACK_TRACE_MIN_DURATION_KEY);
        $spanDurations = SpanStackTraceMinDurationUnitTest::genSpanDurations($expectedSpanStackTraceMinDuration);
        $dbgCtx->add(['spanDurations' => $spanDurations]);

        /**
         * Act
         */

        $this->withInferredSpansBuilderDuringTransaction(
            function (InferredSpansBuilder $builder) use ($spanDurations): void {
                foreach (RangeUtil::generateUpTo(count($spanDurations)) as $spanIndex) {
                    $stackFrame = new ClassicFormatStackTraceFrame('file_for_span_' . $spanIndex . '.php', $spanIndex, /* class */ null, /* isStaticMethod */ null, 'test_span_' . $spanIndex);
                    $builder->addStackTrace([$stackFrame]);
                    $this->mockClock->fastForwardMilliseconds($spanDurations[$spanIndex]);
                    $builder->addStackTrace([$stackFrame]);
                }
            }
        );

        /**
         * Assert
         */

        $dbgCtx->add(['dataFromAgent' => $this->mockEventSink->dataFromAgent]);
        TestCaseBase::assertCount(count($spanDurations), $this->mockEventSink->dataFromAgent->idToSpan);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($spanDurations)) as $spanIndex) {
            $dbgCtx->add(['spanIndex' => $spanIndex, '$spanDurations[$spanIndex]' => $spanDurations[$spanIndex]]);
            $span = $this->mockEventSink->dataFromAgent->singleSpanByName('test_span_' . $spanIndex);
            self::assertEquals($spanDurations[$spanIndex], $span->duration);
            /**
             * span_stack_trace_min_duration
             *      0 - collect stack traces for spans with any duration
             *      any positive value - it limits stack trace collection to spans with duration equal to or greater than
             *      any negative value - it disable stack trace collection for spans completely
             */
            if ($expectedSpanStackTraceMinDuration < 0) {
                self::assertNull($span->stackTrace);
            } elseif ($expectedSpanStackTraceMinDuration === 0.0) {
                self::assertNotNull($span->stackTrace);
            } elseif ($expectedSpanStackTraceMinDuration <= $span->duration) {
                self::assertNotNull($span->stackTrace);
            } else {
                self::assertNull($span->stackTrace);
            }
        }
        $dbgCtx->popSubScope();
    }
}
