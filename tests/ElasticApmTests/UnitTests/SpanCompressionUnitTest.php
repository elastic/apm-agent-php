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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\DurationOptionMetadata;
use Elastic\Apm\Impl\Config\DurationOptionParser;
use Elastic\Apm\Impl\Config\DurationUnits;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;
use ElasticApmTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticApmTests\TestsSharedCode\SpanCompressionSharedCode;
use ElasticApmTests\UnitTests\Util\MockClock;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\SpanCompositeExpectations;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

class SpanCompressionUnitTest extends TracerUnitTestCaseBase
{
    public function testDefaultValues(): void
    {
        $tracer = $this->tracer;
        self::assertInstanceOf(Tracer::class, $tracer);
        self::assertTrue($tracer->getConfig()->spanCompressionEnabled());
        self::assertSame(50.0, $tracer->getConfig()->spanCompressionExactMatchMaxDuration());
        self::assertSame(0.0, $tracer->getConfig()->spanCompressionSameKindMaxDuration());

        foreach (SpanCompressionSharedCode::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME as $optName) {
            /** @var DurationOptionMetadata $optMeta */
            $optMeta = AllOptionsMetadata::get()[$optName];
            self::assertInstanceOf(DurationOptionMetadata::class, $optMeta);
            $parser = $optMeta->parser();
            self::assertInstanceOf(DurationOptionParser::class, $parser);
            self::assertSame(DurationUnits::MILLISECONDS, $parser->defaultUnits());
        }
    }

    /**
     * @param array<string, string|int|float|bool> $options
     *
     * @phpstan-assert !null $this->mockClock
     */
    private function rebuildTracerWithMockClock(array $options): MockClock
    {
        $mockClock = new MockClock();
        $this->rebuildTracer($mockClock, $options);
        return $mockClock;
    }

    public function beginCompressibleSpanAsCurrent(string $name, string $type, ?string $subtype = null, ?string $action = null): SpanInterface
    {
        $span = $this->tracer->getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);
        if ($span instanceof Span) {
            $span->setCompressible(true);
        }
        return $span;
    }

    public function testTwoSpansSequenceExactMatch(): void
    {
        $mockClock = $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => '1s']);
        $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $this->beginCompressibleSpanAsCurrent('test_span_name', 'test_span_type', 'test_span_subtype');
        $mockClock->fastForwardMilliseconds(123);
        $this->tracer->getCurrentExecutionSegment()->end();
        $mockClock->fastForwardMilliseconds(456);
        $this->beginCompressibleSpanAsCurrent('test_span_name', 'test_span_type', 'test_span_subtype');
        $mockClock->fastForwardMilliseconds(789);
        $this->tracer->getCurrentExecutionSegment()->end();
        $this->tracer->getCurrentExecutionSegment()->end();

        $actualSpan = $this->mockEventSink->singleSpan();
        self::assertSame('test_span_name', $actualSpan->name);
        self::assertSame('test_span_type', $actualSpan->type);
        self::assertSame('test_span_subtype', $actualSpan->subtype);
        self::assertNotNull($actualSpan->composite);
        self::assertSame(Constants::COMPRESSION_STRATEGY_EXACT_MATCH, $actualSpan->composite->compressionStrategy);
        self::assertSame(2, $actualSpan->composite->count);
        self::assertSame(floatval(123 + 789), $actualSpan->composite->durationsSum);
        self::assertSame(floatval(123 + 456 + 789), $actualSpan->duration);
    }

    /**
     * @return iterable<array{string, ?string, bool}>
     */
    public function dataProviderForTestTwoSpansSequenceSameKind(): iterable
    {
        yield ['test_span_type', 'test_span_subtype', /* expectedToBeCompressed */ true];
        yield ['test_span_type_2', 'test_span_subtype', /* expectedToBeCompressed */ false];
        yield ['test_span_type', 'test_span_subtype_2', /* expectedToBeCompressed */ false];
        yield ['test_span_type_2', 'test_span_subtype_2', /* expectedToBeCompressed */ false];
        yield ['test_span_type', /* span2Subtype */ null, /* expectedToBeCompressed */ false];
    }

    /**
     * @dataProvider dataProviderForTestTwoSpansSequenceSameKind
     */
    public function testTwoSpansSequenceSameKind(string $span2Type, ?string $span2Subtype, bool $expectedToBeCompressed): void
    {
        $mockClock = $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => '1s', OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => '1s']);

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $span1 =  $this->beginCompressibleSpanAsCurrent('test_span_name_1', 'test_span_type', 'test_span_subtype');
        Span::setServiceFor($span1, 'test_span_service_target_type', 'test_span_service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
        $mockClock->fastForwardMilliseconds(987);
        $span1->end();
        $mockClock->fastForwardMilliseconds(654);
        $span2 =  $this->beginCompressibleSpanAsCurrent('test_span_name_2', $span2Type, $span2Subtype);
        Span::setServiceFor($span2, 'test_span_service_target_type', 'test_span_service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
        $mockClock->fastForwardMilliseconds(321);
        $span2->end();
        $tx->end();

        if ($expectedToBeCompressed) {
            $actualSpan = $this->mockEventSink->singleSpan();
            self::assertSame('Calls to test_span_service_target_type/test_span_service_target_name', $actualSpan->name);
            self::assertSame('test_span_type', $actualSpan->type);
            self::assertSame('test_span_subtype', $actualSpan->subtype);
            self::assertNotNull($actualSpan->composite);
            self::assertSame(Constants::COMPRESSION_STRATEGY_SAME_KIND, $actualSpan->composite->compressionStrategy);
            self::assertSame(2, $actualSpan->composite->count);
            self::assertSame(floatval(987 + 321), $actualSpan->composite->durationsSum);
            self::assertSame(floatval(987 + 654 + 321), $actualSpan->duration);
        } else {
            self::assertCount(2, $this->mockEventSink->idToSpan());
            $expectedSpans = [];
            $expectedSpan = new SpanExpectations();
            $expectedSpan->name->setValue('test_span_name_1');
            $expectedSpan->type->setValue('test_span_type');
            $expectedSpan->subtype->setValue('test_span_subtype');
            $expectedSpan->duration->setValue(floatval(987));
            $expectedSpans[] = $expectedSpan;
            $expectedSpan = new SpanExpectations();
            $expectedSpan->name->setValue('test_span_name_2');
            $expectedSpan->type->setValue($span2Type);
            $expectedSpan->subtype->setValue($span2Subtype);
            $expectedSpan->duration->setValue(floatval(321));
            $expectedSpans[] = $expectedSpan;
            SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, array_values($this->mockEventSink->idToSpan()));
        }
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public function dataProviderForTestTwoSpansSequenceDifferentServiceTarget(): iterable
    {
        yield ['test_span_service_target_type', 'test_span_service_target_name', /* expectedToBeCompressed */ true];
        yield ['test_span_service_target_type_2', 'test_span_service_target_name', /* expectedToBeCompressed */ false];
        yield ['test_span_service_target_type', 'test_span_service_target_name_2', /* expectedToBeCompressed */ false];
        yield ['test_span_service_target_type_2', 'test_span_service_target_name_2', /* expectedToBeCompressed */ false];
    }

    /**
     * @dataProvider dataProviderForTestTwoSpansSequenceDifferentServiceTarget
     */
    public function testTwoSpansSequenceDifferentServiceTarget(string $span2ServiceTargetType, string $span2ServiceTargetName, bool $expectedToBeCompressed): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(['span2ServiceTargetType' => $span2ServiceTargetType, 'span2ServiceTargetName' => $span2ServiceTargetName, 'expectedToBeCompressed' => $expectedToBeCompressed]);

        $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => '1s', OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => '1s']);

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $span1 =  $this->beginCompressibleSpanAsCurrent('test_span_name_1', 'test_span_type', 'test_span_subtype');
        Span::setServiceFor($span1, 'test_span_service_target_type', 'test_span_service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
        $span1->end();
        $span2 =  $this->beginCompressibleSpanAsCurrent('test_span_name_2', 'test_span_type', 'test_span_subtype');
        Span::setServiceFor($span2, $span2ServiceTargetType, $span2ServiceTargetName, 'destination_service_name', 'destination_service_resource', 'destination_service_type');
        $span2->end();
        $tx->end();

        if ($expectedToBeCompressed) {
            $actualSpan = $this->mockEventSink->singleSpan();
            self::assertSame('Calls to test_span_service_target_type/test_span_service_target_name', $actualSpan->name);
            self::assertSame('test_span_type', $actualSpan->type);
            self::assertSame('test_span_subtype', $actualSpan->subtype);
            self::assertNotNull($actualSpan->composite);
            self::assertSame(Constants::COMPRESSION_STRATEGY_SAME_KIND, $actualSpan->composite->compressionStrategy);
            self::assertSame(2, $actualSpan->composite->count);
            self::assertSame(0.0, $actualSpan->composite->durationsSum);
            self::assertSame(0.0, $actualSpan->duration);
        } else {
            self::assertCount(2, $this->mockEventSink->idToSpan());
            $expectedSpans = [];
            $expectedSpan = new SpanExpectations();
            $expectedSpan->name->setValue('test_span_name_1');
            $expectedSpan->setService('test_span_service_target_type', 'test_span_service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            $expectedSpan->duration->setValue(0.0);
            $expectedSpans[] = $expectedSpan;
            $expectedSpan = new SpanExpectations();
            $expectedSpan->name->setValue('test_span_name_2');
            $expectedSpan->setService($span2ServiceTargetType, $span2ServiceTargetName, 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            $expectedSpan->duration->setValue(0.0);
            $expectedSpans[] = $expectedSpan;
            SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, array_values($this->mockEventSink->idToSpan()));
        }
    }

    public function testCompositeSpansAreNotCompressible(): void
    {
        $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => '1s', OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => '1s']);

        $beginEndCompressibleSpansSequence = function (string $spanName): void {
            $this->beginCompressibleSpanAsCurrent($spanName, 'test_span_type', 'test_span_subtype');
            $this->tracer->getCurrentExecutionSegment()->end();
            $this->beginCompressibleSpanAsCurrent($spanName, 'test_span_type', 'test_span_subtype');
            $this->tracer->getCurrentExecutionSegment()->end();
        };

        $spanNames = ['test_span_name_1', 'test_span_name_2'];

        $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        foreach ($spanNames as $spanName) {
            $beginEndCompressibleSpansSequence($spanName);
        }
        $this->tracer->getCurrentExecutionSegment()->end();

        self::assertCount(2, $this->mockEventSink->idToSpan());
        $expectedSpans = [];
        foreach ($spanNames as $spanName) {
            $expectedSpan = new SpanExpectations();
            $expectedSpan->name->setValue($spanName);
            $expectedSpan->type->setValue('test_span_type');
            $expectedSpan->subtype->setValue('test_span_subtype');
            $expectedSpanComposite = new SpanCompositeExpectations();
            $expectedSpanComposite->compressionStrategy->setValue(Constants::COMPRESSION_STRATEGY_EXACT_MATCH);
            $expectedSpanComposite->count->setValue(2);
            $expectedSpanComposite->durationsSum->setValue(0.0);
            $expectedSpan->composite->setValue($expectedSpanComposite);
            $expectedSpan->duration->setValue(0.0);
            $expectedSpans[] = $expectedSpan;
        }
        SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, array_values($this->mockEventSink->idToSpan()));
    }

    /**
     * @return iterable<array{int, bool}>
     */
    public function dataProviderForTestCompressibleChildSpansEndsAfterParent(): iterable
    {
        foreach (RangeUtil::generateUpTo(3) as $childrenCoundThatEndsAfterParent) {
            yield [$childrenCoundThatEndsAfterParent, /* expectedToBeCompressed */ $childrenCoundThatEndsAfterParent === 0];
        }
    }

    /**
     * When a span ends, if it is not compression-eligible or if its parent has already ended, it may be reported immediately.
     *
     * @link https://github.com/elastic/apm/blob/760e34f1428a3e5a17768dae67ba2ecdefb4026d/specs/agents/handling-huge-traces/tracing-spans-compress.md#span-buffering
     *
     * @dataProvider dataProviderForTestCompressibleChildSpansEndsAfterParent
     */
    public function testCompressibleChildSpanEndsAfterParent(int $childrenCoundThatEndsAfterParent, bool $expectedToBeCompressed): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(['childrenCoundThatEndsAfterParent' => $childrenCoundThatEndsAfterParent, 'expectedToBeCompressed' => $expectedToBeCompressed]);
        self::assertLessThanOrEqual(2, $childrenCoundThatEndsAfterParent);
        $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => '1s', OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => '1s']);

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        $parentSpan = $tx->beginCurrentSpan('test_parent_span_name', 'test_parent_span_type');

        $firstChildSpan = $this->beginCompressibleSpanAsCurrent('test_child_span_name', 'test_child_span_type');
        $secondChildSpan = null;
        if ($childrenCoundThatEndsAfterParent < 2) {
            $firstChildSpan->end();
            $secondChildSpan = $this->beginCompressibleSpanAsCurrent('test_child_span_name', 'test_child_span_type');
            if ($childrenCoundThatEndsAfterParent < 1) {
                $secondChildSpan->end();
            }
        }

        $parentSpan->end();

        if ($childrenCoundThatEndsAfterParent >= 1) {
            if ($childrenCoundThatEndsAfterParent >= 2) {
                self::assertFalse($firstChildSpan->hasEnded());
                $firstChildSpan->end();
                self::assertNull($secondChildSpan);
                $secondChildSpan = $this->beginCompressibleSpanAsCurrent('test_child_span_name', 'test_child_span_type');
            }
            self::assertNotNull($secondChildSpan);
            $secondChildSpan->end();
        }

        $tx->end();

        $reportedParentSpan = $this->mockEventSink->singleSpanByName('test_parent_span_name');
        $reportedChildSpans = $this->mockEventSink->findSpansByName('test_child_span_name');
        foreach ($reportedChildSpans as $reportedChildSpan) {
            self::assertSame($reportedParentSpan->id, $reportedChildSpan->parentId);
            self::assertSame('test_child_span_type', $reportedChildSpan->type);
        }

        if ($expectedToBeCompressed) {
            $reportedChildSpan = ArrayUtilForTests::getSingleValue($reportedChildSpans);
            self::assertNotNull($reportedChildSpan->composite);
            self::assertSame(Constants::COMPRESSION_STRATEGY_EXACT_MATCH, $reportedChildSpan->composite->compressionStrategy);
            self::assertSame(2, $reportedChildSpan->composite->count);
        } else {
            self::assertCount(2, $reportedChildSpans);
            foreach ($reportedChildSpans as $reportedChildSpan) {
                self::assertNull($reportedChildSpan->composite);
            }
        }
    }

    /**
     * @return iterable<array{string, ?int, bool}>
     */
    public function dataProviderForTestNotCompressedBecauseOfDuration(): iterable
    {
        foreach (SpanCompressionSharedCode::compressionStrategies() as $compressionStrategy) {
            yield [$compressionStrategy, null, /* expectedToBeCompressed */ true];
            foreach (RangeUtil::generateUpTo(3) as $longerSpanIndex) {
                yield [$compressionStrategy, $longerSpanIndex, /* expectedToBeCompressed */ false];
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestNotCompressedBecauseOfDuration
     */
    public function testNotCompressedBecauseOfDuration(string $compressionStrategy, ?int $longerSpanIndex, bool $expectedAllToBeCompressed): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(['compressionStrategy' => $compressionStrategy, 'longerSpanIndex' => $longerSpanIndex, 'expectedAllToBeCompressed' => $expectedAllToBeCompressed]);
        $maxDuration = 1;
        $mockClock = $this->rebuildTracerWithMockClock([SpanCompressionSharedCode::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME[$compressionStrategy] => $maxDuration . 'ms']);

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        $spanCount = 3;
        foreach (RangeUtil::generateUpTo($spanCount) as $index) {
            $span = $this->beginCompressibleSpanAsCurrent('test_span_name' . (SpanCompressionSharedCode::isExactMatch($compressionStrategy) ? '' : ('_' . $index)), 'test_span_type');
            Span::setServiceFor($span, 'service_target_type', 'service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            $mockClock->fastForwardMilliseconds($maxDuration * ($index === $longerSpanIndex ? 2 : 1));
            $span->end();
        }

        $tx->end();

        $reportedSpans = array_values($this->mockEventSink->idToSpan());

        if ($expectedAllToBeCompressed) {
            self::assertCount(1, $reportedSpans);
            $reportedSpan = ArrayUtilForTests::getSingleValue($reportedSpans) ;
            self::assertNotNull($reportedSpan->composite);
            self::assertSame($compressionStrategy, $reportedSpan->composite->compressionStrategy);
            self::assertSame($spanCount, $reportedSpan->composite->count);
            self::assertSame(floatval($spanCount * $maxDuration), $reportedSpan->composite->durationsSum);
            self::assertSame($reportedSpan->composite->durationsSum, $reportedSpan->duration);
        } else {
            self::assertNotNull($longerSpanIndex);
            $nextReportedSpanToCheckIndex = 0;
            $checkSpanRange = function (int $beginIndex, int $endIndex) use ($maxDuration, $reportedSpans, &$nextReportedSpanToCheckIndex): void {
                self::assertLessThanOrEqual($endIndex, $beginIndex);
                if ($endIndex === $beginIndex) {
                    return;
                }
                $reportedSpan = $reportedSpans[$nextReportedSpanToCheckIndex];
                ++$nextReportedSpanToCheckIndex;
                if ($endIndex - $beginIndex === 1) {
                    self::assertNull($reportedSpan->composite);
                } else {
                    self::assertNotNull($reportedSpan->composite);
                    self::assertSame($endIndex - $beginIndex, $reportedSpan->composite->count);
                    self::assertSame(floatval($reportedSpan->composite->count * $maxDuration), $reportedSpan->composite->durationsSum);
                    self::assertSame($reportedSpan->composite->durationsSum, $reportedSpan->duration);
                }
            };
            $checkSpanRange(0, $longerSpanIndex);
            $checkSpanRange($longerSpanIndex, $longerSpanIndex + 1);
            $checkSpanRange($longerSpanIndex + 1, $spanCount);
        }
    }

    /**
     * @return iterable<array{bool, bool, ?string}>
     */
    public function dataProviderForTestNoFallbackToLessStrictStrategyBecauseOfDuration(): iterable
    {
        yield [/* shouldSpandDurationBeAboveExactMatchMax */ false, /* shouldSpansHaveSameName */ true, /* expectedCompressionStrategy */ Constants::COMPRESSION_STRATEGY_EXACT_MATCH];
        yield [/* shouldSpandDurationBeAboveExactMatchMax */ true, /* shouldSpansHaveSameName */ true, /* expectedCompressionStrategy */ null];
        yield [/* shouldSpandDurationBeAboveExactMatchMax */ true, /* shouldSpansHaveSameName */ false, /* expectedCompressionStrategy */ Constants::COMPRESSION_STRATEGY_SAME_KIND];
    }

    /**
     * Note that if the spans are exact match but duration threshold requirement is not satisfied we just stop compression sequence.
     * In particular it means that the implementation should not proceed to try same kind strategy.
     * Otherwise user would have to lower both span_compression_exact_match_max_duration and span_compression_same_kind_max_duration to prevent longer exact match spans from being compressed.
     *
     * @link https://github.com/elastic/apm/blob/e528576a5b0f3e95fe3c1da493466882fa7d8329/specs/agents/handling-huge-traces/tracing-spans-compress.md?plain=1#L200
     *
     * @dataProvider dataProviderForTestNoFallbackToLessStrictStrategyBecauseOfDuration
     */
    public function testNoFallbackToLessStrictStrategyBecauseOfDuration(bool $shouldSpandDurationBeAboveExactMatchMax, bool $shouldSpansHaveSameName, ?string $expectedCompressionStrategy): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(
            [
                'shouldSpandDurationBeAboveExactMatchMax' => $shouldSpandDurationBeAboveExactMatchMax,
                'shouldSpansHaveSameName'                 => $shouldSpansHaveSameName,
                'expectedCompressionStrategy'             => $expectedCompressionStrategy,
            ]
        );
        $mockClock = $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => '1s', OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => '10s']);

        /** @var Tracer $tracer */
        $tracer = $this->tracer;
        $tx = $tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        $durationBeforeSpan = floatval(100);
        $spanCount = 2;
        $spanDuration = floatval($shouldSpandDurationBeAboveExactMatchMax ? ($tracer->getConfig()->spanCompressionExactMatchMaxDuration() + 0.1) : 1);
        foreach (RangeUtil::generateUpTo($spanCount) as $index) {
            $mockClock->fastForwardMilliseconds($durationBeforeSpan);
            /** @var Span $span */
            $span = $this->beginCompressibleSpanAsCurrent('test_span_name' . ($shouldSpansHaveSameName ? '' : ('_' . $index)), 'test_span_type');
            Span::setServiceFor($span, 'service_target_type', 'service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            $mockClock->fastForwardMilliseconds($spanDuration);
            $span->end();
            if ($shouldSpandDurationBeAboveExactMatchMax) {
                self::assertGreaterThan($tracer->getConfig()->spanCompressionExactMatchMaxDuration(), $span->duration);
            } else {
                self::assertLessThanOrEqual($tracer->getConfig()->spanCompressionExactMatchMaxDuration(), $span->duration);
            }
            self::assertLessThanOrEqual($tracer->getConfig()->spanCompressionSameKindMaxDuration(), $span->duration);
        }

        $tx->end();

        $reportedSpans = $this->mockEventSink->idToSpan();

        if ($expectedCompressionStrategy === null) {
            self::assertCount(2, $reportedSpans);
            foreach ($reportedSpans as $reportedSpan) {
                self::assertNull($reportedSpan->composite);
            }
        } else {
            self::assertCount(1, $reportedSpans);
            $reportedSpan = ArrayUtilForTests::getSingleValue($reportedSpans) ;
            self::assertNotNull($reportedSpan->composite);
            self::assertSame($expectedCompressionStrategy, $reportedSpan->composite->compressionStrategy);
            self::assertSame($spanCount, $reportedSpan->composite->count);
            self::assertSame($spanCount * $spanDuration, $reportedSpan->composite->durationsSum);
            self::assertSame(($spanCount - 1) * $durationBeforeSpan + $spanCount * $spanDuration, $reportedSpan->duration);
        }
    }

    private static function veryShortSpanDurationInMilliseconds(): float
    {
        return TimeUtil::microsecondsToMilliseconds(1);
    }

    /**
     * @return iterable<array{bool, float, bool}>
     */
    public function dataProviderForTestZeroMaxDurationDisablesStrategy(): iterable
    {
        foreach ([true, false] as $isExactMatchStrategy) {
            yield [$isExactMatchStrategy, /* configMaxSpanDuration */ self::veryShortSpanDurationInMilliseconds(), /* expectedToBeCompressed */ true];
            yield [$isExactMatchStrategy, /* configMaxSpanDuration */ 0, /* expectedToBeCompressed */ false];
        }
    }

    /**
     * @dataProvider dataProviderForTestZeroMaxDurationDisablesStrategy
     */
    public function testZeroMaxDurationDisablesStrategy(bool $isExactMatchStrategy, float $configMaxSpanDuration, bool $expectedToBeCompressed): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(['isExactMatchStrategy' => $isExactMatchStrategy, 'configMaxSpanDuration' => $configMaxSpanDuration, 'expectedToBeCompressed' => $expectedToBeCompressed]);
        $otherConfigMaxSpanDurationInSeconds = 10;
        $otherConfigMaxSpanDurationInMilliseconds = $otherConfigMaxSpanDurationInSeconds * TimeUtil::NUMBER_OF_MILLISECONDS_IN_SECOND;
        $options = $isExactMatchStrategy
            ? [OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => $configMaxSpanDuration, OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => $otherConfigMaxSpanDurationInSeconds . 's']
            : [OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION => $otherConfigMaxSpanDurationInSeconds . 's', OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => $configMaxSpanDuration];
        $mockClock = $this->rebuildTracerWithMockClock($options);
        /** @var Tracer $tracer */
        $tracer = $this->tracer;
        self::assertSame(floatval($isExactMatchStrategy ? $configMaxSpanDuration : $otherConfigMaxSpanDurationInMilliseconds), $tracer->getConfig()->spanCompressionExactMatchMaxDuration());

        $tx = $tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        $spanCount = 2;
        foreach (RangeUtil::generateUpTo($spanCount) as $index) {
            /** @var Span $span */
            $span = $this->beginCompressibleSpanAsCurrent('test_span_name' . ($isExactMatchStrategy ? '' : ('_' . $index)), 'test_span_type');
            Span::setServiceFor($span, 'service_target_type', 'service_target_name', 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            $mockClock->fastForwardMilliseconds(self::veryShortSpanDurationInMilliseconds());
            $span->end();
        }

        $tx->end();

        $reportedSpans = $this->mockEventSink->idToSpan();

        if ($expectedToBeCompressed) {
            self::assertCount(1, $reportedSpans);
            $reportedSpan = ArrayUtilForTests::getSingleValue($reportedSpans) ;
            self::assertNotNull($reportedSpan->composite);
            self::assertSame($isExactMatchStrategy ? Constants::COMPRESSION_STRATEGY_EXACT_MATCH : Constants::COMPRESSION_STRATEGY_SAME_KIND, $reportedSpan->composite->compressionStrategy);
            self::assertSame($spanCount, $reportedSpan->composite->count);
            self::assertSame($spanCount * self::veryShortSpanDurationInMilliseconds(), $reportedSpan->composite->durationsSum);
            self::assertSame($reportedSpan->composite->durationsSum, $reportedSpan->duration);
        } else {
            self::assertCount(2, $reportedSpans);
            foreach ($reportedSpans as $reportedSpan) {
                self::assertNull($reportedSpan->composite);
            }
        }
    }

    /**
     * @link https://github.com/elastic/apm/blob/4a5e72b3cee430a839c0adda645c71d4eb0a66bb/specs/agents/handling-huge-traces/tracing-spans-compress.md#consecutive-same-kind-compression-strategy
     *
     * @return iterable<array{?string, ?string, string}>
     */
    public function dataProviderForTestSameKindCompositeSpanName(): iterable
    {
        yield ['test_span_service_target_type', 'test_span_service_target_name', /* expectedSuffix */ 'test_span_service_target_type/test_span_service_target_name'];
        yield ['test_span_service_target_type', /* serviceTargetName */ null, /* expectedSuffix */ 'test_span_service_target_type'];
        yield [/* serviceTargetType */ null, 'test_span_service_target_name', /* expectedSuffix */ 'test_span_service_target_name'];
        yield [/* serviceTargetType */ null, /* serviceTargetName */ null, /* expectedSuffix */ 'unknown'];
    }

    /**
     * @dataProvider dataProviderForTestSameKindCompositeSpanName
     */
    public function testSameKindCompositeSpanName(?string $serviceTargetType, ?string $serviceTargetName, string $expectedSuffix): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(['serviceTargetType' => $serviceTargetType, 'serviceTargetName' => $serviceTargetName, 'expectedSuffix' => $expectedSuffix]);
        $this->rebuildTracerWithMockClock([OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION => '1s']);

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        $spanCount = 2;
        foreach (RangeUtil::generateUpTo($spanCount) as $index) {
            $span = $this->beginCompressibleSpanAsCurrent('test_span_name_' . $index, 'test_span_type');
            $span->context()->service()->target()->setType($serviceTargetType);
            $span->context()->service()->target()->setName($serviceTargetName);
            $span->end();
        }

        $tx->end();

        $reportedSpan = $this->mockEventSink->singleSpan();
        self::assertNotNull($reportedSpan->composite);
        self::assertSame(Constants::COMPRESSION_STRATEGY_SAME_KIND, $reportedSpan->composite->compressionStrategy);
        self::assertSame('test_span_type', $reportedSpan->type);
        self::assertSame('Calls to ' . $expectedSuffix, $reportedSpan->name);
    }

    /**
     * @param ?MockClock                           $mockClock
     * @param array<string, string|int|float|bool> $options
     */
    private function rebuildTracer(?MockClock $mockClock, array $options): void
    {
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($mockClock, $options): void {
                foreach ($options as $optName => $optVal) {
                    $builder->withConfig($optName, ConfigUtilForTests::optionValueToString($optVal));
                }
                if ($mockClock !== null) {
                    $builder->withClock($mockClock);
                }
            }
        );
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestOneCompressedSequence(): iterable
    {
        return SpanCompressionSharedCode::dataProviderForTestOneCompressedSequence();
    }

    /**
     * @dataProvider dataProviderForTestOneCompressedSequence
     */
    public function testOneCompressedSequence(MixedMap $testArgs): void
    {
        $sharedCode = new SpanCompressionSharedCode($testArgs);
        $this->rebuildTracer($sharedCode->mockClock, $sharedCode->agentConfigOptions);

        $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $sharedCode->implTestOneCompressedSequenceAct();
        $this->tracer->getCurrentExecutionSegment()->end();

        $sharedCode->implTestOneCompressedSequenceAssert($this->mockEventSink->dataFromAgent);
    }

    private const REASONS_COMPRESSION_STOPS_SEQUENCE_LENGTH = 4;
    private const WRAP_IN_PARENT_SPAN_KEY = 'wrap_in_parent_span';
    private const STOPPING_SPAN_INDEX_KEY = 'stopping_span_index';
    private const REASON_COMPRESSION_STOPS_KEY = 'reason_compression_stops';
    private const REASON_COMPRESSION_STOPS_MAX_DURATION = 'REASON_COMPRESSION_STOPS_MAX_DURATION';
    private const REASON_COMPRESSION_STOPS_DIFFERENT_NAME = 'REASON_COMPRESSION_STOPS_DIFFERENT_NAME';
    private const REASON_COMPRESSION_STOPS_DIFFERENT_TYPE = 'REASON_COMPRESSION_STOPS_DIFFERENT_TYPE';
    private const REASON_COMPRESSION_STOPS_DIFFERENT_SUBTYPE = 'REASON_COMPRESSION_STOPS_DIFFERENT_SUBTYPE';
    private const REASON_COMPRESSION_STOPS_NOT_COMPRESSIBLE = 'REASON_COMPRESSION_STOPS_NOT_COMPRESSIBLE';
    private const REASON_COMPRESSION_STOPS_OUTCOME = 'REASON_COMPRESSION_STOPS_OUTCOME';
    private const REASON_COMPRESSION_STOPS_DISTRIBUTED_TRACING_CONTEXT = 'REASON_COMPRESSION_STOPS_DISTRIBUTED_TRACING_CONTEXT';
    private const REASON_COMPRESSION_STOPS_DIFFERENT_SERVICE_TARGET = 'REASON_COMPRESSION_STOPS_DIFFERENT_SERVICE_TARGET';
    private const COMPRESSION_STRATEGY_KEY = 'compression_strategy';
    private const ADD_DBG_LABEL_WITH_SPAN_INDEX_KEY = 'add_dbg_label_with_span_index';
    private const DBG_LABEL_WITH_SPAN_INDEX_KEY = 'dbg_label_with_span_index';
    private const COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY = 'compressible_span_has_service_target';
    private const COMPRESSIBLE_SPAN_SERVICE_TARGET_TYPE_KEY = 'compressible_span_service_target_type';
    private const COMPRESSIBLE_SPAN_SERVICE_TARGET_NAME_KEY = 'compressible_span_service_target_name';
    private const STOPPING_SPAN_HAS_SERVICE_TARGET_KEY = 'stopping_span_has_service_target';
    private const STOPPING_SPAN_SERVICE_TARGET_TYPE_KEY = 'stopping_span_service_target_type';
    private const STOPPING_SPAN_SERVICE_TARGET_NAME_KEY = 'stopping_span_service_target_name';
    private const COMPRESSIBLE_SPAN_OUTCOME_KEY = 'compressible_span_outcome';
    private const STOPPING_SPAN_OUTCOME_KEY = 'stopping_span_outcome';

    private static function simulateSendingOutDistributedTracingContext(SpanInterface $span): void
    {
        $headersInjectorCallsCount = 0;
        $span->injectDistributedTracingHeaders(
            function (string $headerName, string $headerValue) use (&$headersInjectorCallsCount): void {
                ++$headersInjectorCallsCount;
                self::assertNotEmpty($headerName);
                self::assertNotEmpty($headerValue);
            }
        );
        self::assertGreaterThan(0, $headersInjectorCallsCount);
    }

    private static function buildSameKindCompressedCompositeName(SpanDto $span): string
    {
        return ($serviceTarget = $span->getServiceTarget()) === null
            ? Span::buildSameKindCompressedCompositeName(null, null)
            : Span::buildSameKindCompressedCompositeName($serviceTarget->type, $serviceTarget->name);
    }

    /**
     * @param array<mixed> $resultSoFar
     *
     * @return iterable<array<mixed>>
     */
    private static function genServiceTargetRelatedDimensions(array $resultSoFar): iterable
    {
        if ($resultSoFar[self::REASON_COMPRESSION_STOPS_KEY] !== self::REASON_COMPRESSION_STOPS_DIFFERENT_SERVICE_TARGET) {
            yield array_merge(
                [
                    self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY => false,
                    self::COMPRESSIBLE_SPAN_SERVICE_TARGET_TYPE_KEY => null,
                    self::COMPRESSIBLE_SPAN_SERVICE_TARGET_NAME_KEY => null,
                    self::STOPPING_SPAN_HAS_SERVICE_TARGET_KEY => false,
                    self::STOPPING_SPAN_SERVICE_TARGET_TYPE_KEY => null,
                    self::STOPPING_SPAN_SERVICE_TARGET_NAME_KEY => null,
                ],
                $resultSoFar
            );
            return;
        }

        $serviceTargetRelatedDimensions = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY, [false, true])
            ->addConditionalKeyedDimensionAllValueCombinable(
                self::COMPRESSIBLE_SPAN_SERVICE_TARGET_TYPE_KEY /* <- new dimension key */,
                self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY /* <- depends on dimension key */,
                true /* <- depends on dimension true value */,
                ['test_span_service_target_type', null] /* <- new dimension variants for true case */,
                [null] /* <- new dimension variants for false case */
            )
            ->addConditionalKeyedDimensionAllValueCombinable(
                self::COMPRESSIBLE_SPAN_SERVICE_TARGET_NAME_KEY /* <- new dimension key */,
                self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY /* <- depends on dimension key */,
                true /* <- depends on dimension true value */,
                ['test_span_service_target_name', null] /* <- new dimension variants for true case */,
                [null] /* <- new dimension variants for false case */
            )
            ->addGeneratorAllValuesCombinable(
                /**
                 * @param array<mixed> $resultSoFar
                 *
                 * @return iterable<array<mixed>>
                 */
                function (array $resultSoFar): iterable {
                    $compressibleSpanHasServiceTarget = MixedMap::getBoolFrom(self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY, $resultSoFar);
                    $compressibleSpanServiceTargetType = MixedMap::getNullableStringFrom(self::COMPRESSIBLE_SPAN_SERVICE_TARGET_TYPE_KEY, $resultSoFar);
                    $compressibleSpanServiceTargetName = MixedMap::getNullableStringFrom(self::COMPRESSIBLE_SPAN_SERVICE_TARGET_NAME_KEY, $resultSoFar);

                    // If all service target properties are null that effectively means that there is no service target
                    if ($compressibleSpanServiceTargetType === null && $compressibleSpanServiceTargetName === null) {
                        $compressibleSpanHasServiceTarget = false;
                    }

                    if ($compressibleSpanHasServiceTarget) {
                        // If compressible spans have service target then stopping span can be different either by having service target with different type/name
                        // or by not having service target
                        $stoppingSpanHasServiceTargetVariants = [true, false];
                        $canBothPropsBeNull = true;
                    } else {
                        // If compressible spans do not have service target then the only way for stopping span to be different is to have service target
                        $stoppingSpanHasServiceTargetVariants = [true];
                        // If all service target properties are null that effectively means that there is no service target
                        $canBothPropsBeNull = false;
                    }

                    /**
                     * @return array<?string>
                     */
                    $genTypeVariants = function (bool $hasServiceTarget): array {
                        if (!$hasServiceTarget) {
                            return [null];
                        }
                        return ['test_span_service_DIFFERENT_target_type', null];
                    };

                    /**
                     * @return array<?string>
                     */
                    $genNameVariants = function (bool $hasServiceTarget, ?string $serviceTargetType) use ($canBothPropsBeNull): array {
                        if (!$hasServiceTarget) {
                            return [null];
                        }
                        $variants = ['test_span_service_DIFFERENT_target_name'];
                        if ($serviceTargetType !== null || $canBothPropsBeNull) {
                            $variants[] = null;
                        }
                        return $variants;
                    };

                    $result = $resultSoFar;
                    foreach ($stoppingSpanHasServiceTargetVariants as $stoppingSpanHasServiceTarget) {
                        $result = array_merge([self::STOPPING_SPAN_HAS_SERVICE_TARGET_KEY => $stoppingSpanHasServiceTarget], $result);
                        foreach ($genTypeVariants($stoppingSpanHasServiceTarget) as $stoppingSpanServiceTargetType) {
                            $result = array_merge([self::STOPPING_SPAN_SERVICE_TARGET_TYPE_KEY => $stoppingSpanServiceTargetType], $result);
                            foreach ($genNameVariants($stoppingSpanHasServiceTarget, $stoppingSpanServiceTargetType) as $stoppingSpanServiceTargetName) {
                                yield array_merge([self::STOPPING_SPAN_SERVICE_TARGET_NAME_KEY => $stoppingSpanServiceTargetName], $result);
                            }
                        }
                    }
                }
            )
            ->buildWithoutDataSetName();

        foreach ($serviceTargetRelatedDimensions as $serviceTargetRelatedValues) {
            yield array_merge($serviceTargetRelatedValues, $resultSoFar);
        }
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestReasonsCompressionStops(): iterable
    {
        $compressionStrategies = array_keys(SpanCompressionSharedCode::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME);

        $reasonsForSameKindStrategy = [
            self::REASON_COMPRESSION_STOPS_MAX_DURATION,
            self::REASON_COMPRESSION_STOPS_DIFFERENT_TYPE,
            self::REASON_COMPRESSION_STOPS_DIFFERENT_SUBTYPE,
            self::REASON_COMPRESSION_STOPS_NOT_COMPRESSIBLE,
            self::REASON_COMPRESSION_STOPS_OUTCOME,
            self::REASON_COMPRESSION_STOPS_DISTRIBUTED_TRACING_CONTEXT,
            self::REASON_COMPRESSION_STOPS_DIFFERENT_SERVICE_TARGET
        ];
        $reasonsForExactMatchStrategy = array_merge([self::REASON_COMPRESSION_STOPS_DIFFERENT_NAME], $reasonsForSameKindStrategy);

        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::WRAP_IN_PARENT_SPAN_KEY)
            ->addKeyedDimensionAllValuesCombinable(self::STOPPING_SPAN_INDEX_KEY, DataProviderForTestBuilder::rangeUpTo(self::REASONS_COMPRESSION_STOPS_SEQUENCE_LENGTH + 1))
            ->addKeyedDimensionAllValuesCombinable(self::COMPRESSION_STRATEGY_KEY, $compressionStrategies)
            ->addConditionalKeyedDimensionAllValueCombinable(
                self::REASON_COMPRESSION_STOPS_KEY /* <- new dimension key */,
                self::COMPRESSION_STRATEGY_KEY /* <- depends on dimension key */,
                Constants::COMPRESSION_STRATEGY_EXACT_MATCH /* <- depends on dimension true value */,
                $reasonsForExactMatchStrategy /* <- new dimension variants for true case */,
                $reasonsForSameKindStrategy /* <- new dimension variants for false case */
            )
            ->addConditionalKeyedDimensionAllValueCombinable(
                self::ADD_DBG_LABEL_WITH_SPAN_INDEX_KEY /* <- new dimension key */,
                self::COMPRESSION_STRATEGY_KEY /* <- depends on dimension key */,
                Constants::COMPRESSION_STRATEGY_EXACT_MATCH /* <- depends on dimension true value */,
                [true, false] /* <- new dimension variants for true case */,
                [false] /* <- new dimension variants for false case */
            )
            ->addGeneratorAllValuesCombinable(
                /**
                 * @param array<mixed> $resultSoFar
                 *
                 * @return iterable<array<mixed>>
                 */
                function (array $resultSoFar): iterable {
                    return self::genServiceTargetRelatedDimensions($resultSoFar);
                }
            )
            // genServiceTargetRelatedDimensions must be before outcome related generattor
            // because outcome related generattor depends on value for COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY
            ->addGeneratorAllValuesCombinable(
                /**
                 * @param array<mixed> $resultSoFar
                 *
                 * @return iterable<array<mixed>>
                 */
                function (array $resultSoFar): iterable {
                    if ($resultSoFar[self::REASON_COMPRESSION_STOPS_KEY] === self::REASON_COMPRESSION_STOPS_OUTCOME) {
                        $compressibleSpanOutcomeVariants = [null, Constants::OUTCOME_SUCCESS];
                        $stoppingSpanOutcomeVariants = [Constants::OUTCOME_FAILURE, Constants::OUTCOME_UNKNOWN];
                    } else {
                        $compressibleSpanOutcomeVariants = [MixedMap::getBoolFrom(self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY, $resultSoFar) ? Constants::OUTCOME_SUCCESS : null];
                        $stoppingSpanOutcomeVariants = $compressibleSpanOutcomeVariants;
                    }
                    $result = $resultSoFar;
                    foreach ($compressibleSpanOutcomeVariants as $compressibleSpanOutcome) {
                        $result = array_merge([self::COMPRESSIBLE_SPAN_OUTCOME_KEY => $compressibleSpanOutcome], $result);
                        foreach ($stoppingSpanOutcomeVariants as $stoppingSpanOutcome) {
                            $result = array_merge([self::STOPPING_SPAN_OUTCOME_KEY => $stoppingSpanOutcome], $result);
                            yield $result;
                        }
                    }
                }
            )
            // Uncomment to limit generated data to the data set with the index below
            // ->emitOnlyDataSetWithIndex(???)
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @dataProvider dataProviderForTestReasonsCompressionStops
     */
    public function testReasonsCompressionStops(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope($dbgCtx);
        $dbgCtx->add(['testArgs' => $testArgs]);
        $reason = $testArgs->getString(self::REASON_COMPRESSION_STOPS_KEY);
        $compressionStrategy = $testArgs->getString(self::COMPRESSION_STRATEGY_KEY);
        $stoppingSpanIndex = $testArgs->getInt(self::STOPPING_SPAN_INDEX_KEY);
        $shouldWrapInParentSpan = $testArgs->getBool(self::WRAP_IN_PARENT_SPAN_KEY);
        $shouldAddDbgLabelWithSpanIndex = $testArgs->getBool(self::ADD_DBG_LABEL_WITH_SPAN_INDEX_KEY);

        $mockClock = null;
        if ($reason === self::REASON_COMPRESSION_STOPS_MAX_DURATION) {
            $mockClock = new MockClock();
            $maxDuration = 1;
        } else {
            $maxDuration = SpanCompressionSharedCode::REAL_CLOCK_MAX_SPAN_COMPRESSION_DURATION_IN_MILLISECONDS;
        }
        $this->rebuildTracer($mockClock, [SpanCompressionSharedCode::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME[$compressionStrategy] => $maxDuration . 'ms']);

        $compressibleSpanNameExactMatch = 'test_span_name';
        $compressibleSpanNameSameKind = function (int $spanIndex): string {
            return 'test_span_name_' . $spanIndex;
        };
        $compressibleSpanType = 'test_span_type';
        $compressibleSpanSubtype = 'test_span_subtype';
        $compressibleSpanOutcome = $testArgs->getNullableString(self::COMPRESSIBLE_SPAN_OUTCOME_KEY);
        $compressibleSpanHasServiceTarget = $testArgs->getBool(self::COMPRESSIBLE_SPAN_HAS_SERVICE_TARGET_KEY);
        $compressibleSpanServiceTargetType = $testArgs->getNullableString(self::COMPRESSIBLE_SPAN_SERVICE_TARGET_TYPE_KEY);
        $compressibleSpanServiceTargetName = $testArgs->getNullableString(self::COMPRESSIBLE_SPAN_SERVICE_TARGET_NAME_KEY);
        $compressibleSpanDuration = $maxDuration;

        $stoppingSpanName = $reason === self::REASON_COMPRESSION_STOPS_DIFFERENT_NAME ? 'test_span_DIFFERENT_name' : $compressibleSpanNameExactMatch;
        $stoppingSpanType = $reason === self::REASON_COMPRESSION_STOPS_DIFFERENT_TYPE ? 'test_span_DIFFERENT_type' : $compressibleSpanType;
        $stoppingSpanSubtype = $reason === self::REASON_COMPRESSION_STOPS_DIFFERENT_SUBTYPE ? 'test_span_DIFFERENT_subtype' : $compressibleSpanSubtype;
        $stoppingSpanOutcome = $testArgs->getNullableString(self::STOPPING_SPAN_OUTCOME_KEY);
        $stoppingSpanHasServiceTarget = $testArgs->getBool(self::STOPPING_SPAN_HAS_SERVICE_TARGET_KEY);
        $stoppingSpanServiceTargetType = $testArgs->getNullableString(self::STOPPING_SPAN_SERVICE_TARGET_TYPE_KEY);
        $stoppingSpanServiceTargetName = $testArgs->getNullableString(self::STOPPING_SPAN_SERVICE_TARGET_NAME_KEY);
        $stoppingSpanDuration = $reason === self::REASON_COMPRESSION_STOPS_MAX_DURATION ? $maxDuration + 1000 : $compressibleSpanDuration;

        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');

        /** @var ?SpanInterface $parentSpan */
        $parentSpan = null;
        if ($shouldWrapInParentSpan) {
            $parentSpan = ElasticApm::getCurrentTransaction()->beginCurrentSpan('test_parent_span_name', 'test_parent_span_type');
        }

        foreach (RangeUtil::generateUpTo(self::REASONS_COMPRESSION_STOPS_SEQUENCE_LENGTH) as $currentSpanIndex) {
            if ($currentSpanIndex === $stoppingSpanIndex) {
                $spanName = $stoppingSpanName;
                $spanType = $stoppingSpanType;
                $spanSubtype = $stoppingSpanSubtype;
                $spanOutcome = $stoppingSpanOutcome;
                $spanIsCompressible = !($reason === self::REASON_COMPRESSION_STOPS_NOT_COMPRESSIBLE);
                $spanIdIsUsedInDistributedTracingContext = ($reason === self::REASON_COMPRESSION_STOPS_DISTRIBUTED_TRACING_CONTEXT);
                $spanHasServiceTargetType = $stoppingSpanHasServiceTarget;
                $spanServiceTargetType = $stoppingSpanServiceTargetType;
                $spanServiceTargetName = $stoppingSpanServiceTargetName;
            } else {
                $spanName = SpanCompressionSharedCode::isExactMatch($compressionStrategy) ? $compressibleSpanNameExactMatch : $compressibleSpanNameSameKind($currentSpanIndex);
                $spanType = $compressibleSpanType;
                $spanSubtype = $compressibleSpanSubtype;
                $spanOutcome = $compressibleSpanOutcome;
                $spanIsCompressible = true;
                $spanIdIsUsedInDistributedTracingContext = false;
                $spanHasServiceTargetType = $compressibleSpanHasServiceTarget;
                $spanServiceTargetType = $compressibleSpanServiceTargetType;
                $spanServiceTargetName = $compressibleSpanServiceTargetName;
            }

            $span = $this->tracer->getCurrentTransaction()->beginCurrentSpan($spanName, $spanType, $spanSubtype);

            if ($shouldAddDbgLabelWithSpanIndex) {
                $span->context()->setLabel(self::DBG_LABEL_WITH_SPAN_INDEX_KEY, $currentSpanIndex);
            }

            self::assertInstanceOf(Span::class, $span);
            $span->setCompressible($spanIsCompressible);

            $span->setOutcome($spanOutcome);

            if ($spanIdIsUsedInDistributedTracingContext) {
                self::simulateSendingOutDistributedTracingContext($span);
            }

            if ($spanHasServiceTargetType) {
                Span::setServiceFor($span, $spanServiceTargetType, $spanServiceTargetName, 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            }

            if ($mockClock !== null) {
                $mockClock->fastForwardMilliseconds(
                    ($currentSpanIndex === $stoppingSpanIndex && $reason === self::REASON_COMPRESSION_STOPS_MAX_DURATION) ? $stoppingSpanDuration : $compressibleSpanDuration
                );
            }

            $span->end();

            if ($mockClock === null) {
                // Even though it's very unlikely for span to have duration longer than max configured above under real clock
                // we still would prefer to detect it and fail early
                TestCaseBase::assertLessThanOrEqual($maxDuration, $span->duration);
            }
        }

        if ($shouldWrapInParentSpan) {
            TestCaseBase::assertNotNull($parentSpan);
            TestCaseBase::assertSame($parentSpan, ElasticApm::getCurrentTransaction()->getCurrentSpan());
            $parentSpan->end();
        }

        $tx->end();

        $reportedTx = $this->mockEventSink->singleTransaction();

        $reportedSpans = array_values($this->mockEventSink->idToSpan());
        $dbgCtx->add(['count($reportedSpans)' => count($reportedSpans), 'reportedSpans' => $reportedSpans]);

        $reportedSequenceSpans = $reportedSpans;
        $reportedSequenceSpansParentId = $reportedTx->id;
        if ($shouldWrapInParentSpan) {
            foreach ($reportedSequenceSpans as $key => $receivedSpan) {
                if ($receivedSpan->name === 'test_parent_span_name') {
                    $reportedSequenceSpansParentId = $receivedSpan->id;
                    unset($reportedSequenceSpans[$key]);
                    break;
                }
            }
        }
        $dbgCtx->add(['count($reportedSequenceSpans)' => count($reportedSequenceSpans), 'reportedSequenceSpans' => $reportedSequenceSpans]);

        /** @var array<array{int, int}> $indexRangesToCheck */
        $indexRangesToCheck = [];
        $addIndexRangeToCheck = function (int $beginIndex, int $endIndex) use (&$indexRangesToCheck): void {
            self::assertLessThanOrEqual($endIndex, $beginIndex);
            if ($endIndex === $beginIndex) {
                return;
            }
            $indexRangesToCheck[] = [$beginIndex, $endIndex];
        };
        if ($stoppingSpanIndex === self::REASONS_COMPRESSION_STOPS_SEQUENCE_LENGTH) {
            self::assertCount(1, $reportedSequenceSpans);
            $addIndexRangeToCheck(0, self::REASONS_COMPRESSION_STOPS_SEQUENCE_LENGTH);
        } else {
            $addIndexRangeToCheck(0, $stoppingSpanIndex);
            $addIndexRangeToCheck($stoppingSpanIndex, $stoppingSpanIndex + 1);
            $addIndexRangeToCheck($stoppingSpanIndex + 1, self::REASONS_COMPRESSION_STOPS_SEQUENCE_LENGTH);
        }
        $dbgCtx->add(['indexRangesToCheck' => $indexRangesToCheck]);

        $assertService = function (SpanDto $span, bool $hasServiceTarget, ?string $serviceTargetType, ?string $serviceTargetName) use ($shouldAddDbgLabelWithSpanIndex): void {
            if ($hasServiceTarget) {
                $span->assertService($serviceTargetType, $serviceTargetName, 'destination_service_name', 'destination_service_resource', 'destination_service_type');
            } else {
                if ($span->context !== null && $span->context->service !== null) {
                    self::assertNull($span->context->service->target);
                }
                if (!$shouldAddDbgLabelWithSpanIndex) {
                    self::assertNull($span->context);
                }
            }
        };

        $nextReportedSequenceSpanToCheckIndex = 0;
        $dbgCtx->add(['nextReportedSequenceSpanToCheckIndex' => &$nextReportedSequenceSpanToCheckIndex]);
        foreach ($indexRangesToCheck as [$beginIndex, $endIndex]) {
            AssertMessageStack::newSubScope(/* ref */ $dbgCtx);
            $dbgCtx->add(['beginIndex' => $beginIndex, 'endIndex' => $endIndex]);

            self::assertLessThan($endIndex, $beginIndex);
            $reportedSpan = $reportedSequenceSpans[$nextReportedSequenceSpanToCheckIndex];
            ++$nextReportedSequenceSpanToCheckIndex;
            $dbgCtx->add(['reportedSpan' => $reportedSpan]);

            self::assertSame($reportedTx->traceId, $reportedSpan->traceId);
            self::assertSame($reportedTx->id, $reportedSpan->transactionId);
            self::assertSame($reportedSequenceSpansParentId, $reportedSpan->parentId);
            if ($shouldAddDbgLabelWithSpanIndex) {
                self::assertSame($beginIndex, self::getLabel($reportedSpan, self::DBG_LABEL_WITH_SPAN_INDEX_KEY));
            } else {
                self::assertNotHasLabel($reportedSpan, self::DBG_LABEL_WITH_SPAN_INDEX_KEY);
            }

            if ($endIndex - $beginIndex === 1) {
                self::assertNull($reportedSpan->composite);

                if ($beginIndex === $stoppingSpanIndex) {
                    self::assertSame($stoppingSpanName, $reportedSpan->name);
                    self::assertSame($stoppingSpanType, $reportedSpan->type);
                    self::assertSame($stoppingSpanSubtype, $reportedSpan->subtype);
                    self::assertSame($stoppingSpanOutcome, $reportedSpan->outcome);
                    $assertService($reportedSpan, $stoppingSpanHasServiceTarget, $stoppingSpanServiceTargetType, $stoppingSpanServiceTargetName);
                    if ($mockClock !== null) {
                        self::assertSame(floatval($stoppingSpanDuration), $reportedSpan->duration);
                    }
                } else {
                    $expectedSpanName = SpanCompressionSharedCode::isExactMatch($compressionStrategy) ? $compressibleSpanNameExactMatch : $compressibleSpanNameSameKind($beginIndex);
                    self::assertSame($expectedSpanName, $reportedSpan->name);
                    self::assertSame($compressibleSpanType, $reportedSpan->type);
                    self::assertSame($compressibleSpanSubtype, $reportedSpan->subtype);
                    self::assertSame($compressibleSpanOutcome, $reportedSpan->outcome);
                    $assertService($reportedSpan, $compressibleSpanHasServiceTarget, $compressibleSpanServiceTargetType, $compressibleSpanServiceTargetName);
                    if ($mockClock !== null) {
                        self::assertSame(floatval($compressibleSpanDuration), $reportedSpan->duration);
                    }
                }
                AssertMessageStack::popSubScope(/* ref */ $dbgCtx);
                continue;
            }

            if (SpanCompressionSharedCode::isExactMatch($compressionStrategy)) {
                $compositeSpanName = $compressibleSpanNameExactMatch;
            } else {
                $compositeSpanName = self::buildSameKindCompressedCompositeName($reportedSpan);
            }
            self::assertSame($compositeSpanName, $reportedSpan->name);
            self::assertSame($compressibleSpanType, $reportedSpan->type);
            self::assertSame($compressibleSpanSubtype, $reportedSpan->subtype);
            self::assertSame($compressibleSpanOutcome, $reportedSpan->outcome);
            $assertService($reportedSpan, $compressibleSpanHasServiceTarget, $compressibleSpanServiceTargetType, $compressibleSpanServiceTargetName);
            self::assertNotNull($reportedSpan->composite);
            self::assertSame($endIndex - $beginIndex, $reportedSpan->composite->count);
            self::assertSame($compressionStrategy, $reportedSpan->composite->compressionStrategy);
            if ($mockClock !== null) {
                self::assertSame(floatval($reportedSpan->composite->count * $compressibleSpanDuration), $reportedSpan->composite->durationsSum);
                self::assertSame($reportedSpan->composite->durationsSum, $reportedSpan->duration);
            }
            AssertMessageStack::popSubScope(/* ref */ $dbgCtx);
        }
        self::assertSame($nextReportedSequenceSpanToCheckIndex, count($reportedSequenceSpans));
    }
}
