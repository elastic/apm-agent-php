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

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;
use ElasticApmTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticApmTests\UnitTests\Util\MockClock;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\SpanCompositeExpectations;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TransactionExpectations;

final class SpanCompressionSharedCode implements LoggableInterface
{
    use LoggableTrait;

    private const IS_SPAN_COMPRESSION_ENABLED_KEY = 'span_compression_enabled';

    private const SHOULD_MOCK_CLOCK_KEY = 'should_mock_clock';
    private const MOCK_CLOCK_INITIAL_TIME = 0;
    private const MOCK_CLOCK_TIME_BETWEEN_SPANS_IN_MILLISECONDS = 10 * TimeUtil::NUMBER_OF_MILLISECONDS_IN_SECOND;

    /**
     * If clock is not mocked then set max duration for large enough value (1 hour) to minimize risk of actual duration being larger
     */
    public const REAL_CLOCK_MAX_SPAN_COMPRESSION_DURATION_IN_MILLISECONDS = self::NUMBER_OF_MILLISECONDS_IN_HOUR;

    private const COMPRESSION_STRATEGY_KEY = 'compression_strategy';
    private const NUMBER_OF_MILLISECONDS_IN_HOUR = TimeUtil::NUMBER_OF_MILLISECONDS_IN_SECOND * TimeUtil::NUMBER_OF_SECONDS_IN_MINUTE * TimeUtil::NUMBER_OF_MINUTES_IN_HOUR;

    private const MOCK_CLOCK_MAX_SPAN_COMPRESSION_DURATION_IN_MILLISECONDS = 1;
    private const SPAN_INDEX_LABEL_KEY = 'span_index';
    public const COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME
        = [
            Constants::COMPRESSION_STRATEGY_EXACT_MATCH => OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION,
            Constants::COMPRESSION_STRATEGY_SAME_KIND   => OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION,
        ];

    private const NOT_COMPRESSIBLE_PROLOG_LENGTH_KEY = 'not_compressible_prolog_length';
    private const NOT_COMPRESSIBLE_EPILOG_LENGTH_KEY = 'not_compressible_epilog_length';
    private const COMPRESSIBLE_SEQUENCE_LENGTH_KEY = 'compressible_sequence_length';

    private const NOT_COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS = [2, 0, 1];
    private const COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS = [2, 3];

    private const SHOULD_WRAP_IN_PARENT_SPAN_KEY = 'should_wrap_in_parent_span';

    /** @var int */
    private $spanCounter;

    /** @var int */
    private $notCompressiblePrologLength;

    /** @var int */
    private $compressibleSequenceLength;

    /** @var int */
    private $notCompressibleEpilogLength;

    /** @var bool */
    private $isSpanCompressionEnabled;

    /** @var string */
    private $compressionStrategy;

    /** @var float */
    private $compressionMaxDuration;

    /** @var ?MockClock */
    public $mockClock;

    /** @var ?float */
    private $mockClockSpanDuration;

    /** @var bool */
    private $shouldWrapInParentSpan;

    /** @var array<string, string> */
    public $agentConfigOptions;

    public function __construct(MixedMap $testArgs)
    {
        $this->isSpanCompressionEnabled = $testArgs->getBool(self::IS_SPAN_COMPRESSION_ENABLED_KEY);
        $this->compressionStrategy = $testArgs->getString(self::COMPRESSION_STRATEGY_KEY);
        $shouldMockClock = $testArgs->getBool(self::SHOULD_MOCK_CLOCK_KEY);
        $this->mockClock = $shouldMockClock ? new MockClock(self::MOCK_CLOCK_INITIAL_TIME) : null;
        $this->mockClockSpanDuration = $shouldMockClock ? floatval(self::MOCK_CLOCK_MAX_SPAN_COMPRESSION_DURATION_IN_MILLISECONDS) : null;

        $this->notCompressiblePrologLength = $testArgs->getInt(self::NOT_COMPRESSIBLE_PROLOG_LENGTH_KEY);
        $this->compressibleSequenceLength = $testArgs->getInt(self::COMPRESSIBLE_SEQUENCE_LENGTH_KEY);
        $this->notCompressibleEpilogLength = $testArgs->getInt(self::NOT_COMPRESSIBLE_EPILOG_LENGTH_KEY);

        $this->shouldWrapInParentSpan = $testArgs->getBool(self::SHOULD_WRAP_IN_PARENT_SPAN_KEY);

        $compressionMaxDurationOptName = self::compressionStrategyToMaxDurationOptName($this->compressionStrategy);
        $this->compressionMaxDuration = floatval($shouldMockClock ? self::MOCK_CLOCK_MAX_SPAN_COMPRESSION_DURATION_IN_MILLISECONDS : self::REAL_CLOCK_MAX_SPAN_COMPRESSION_DURATION_IN_MILLISECONDS);
        if ($this->mockClockSpanDuration !== null) {
            TestCaseBase::assertLessThanOrEqual($this->compressionMaxDuration, $this->mockClockSpanDuration);
        }

        $this->agentConfigOptions = [
            OptionNames::SPAN_COMPRESSION_ENABLED => ConfigUtilForTests::optionValueToString($this->isSpanCompressionEnabled),
            $compressionMaxDurationOptName => $this->compressionMaxDuration . ' ms',
        ];
    }

    /**
     * @return string[]
     */
    public static function compressionStrategies(): array
    {
        return array_keys(self::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestOneCompressedSequence(): iterable
    {
        $maxNotCompressibleLength = max(self::NOT_COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS);

        $compressionStrategies = array_keys(self::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME);

        $enabledCombinations = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::COMPRESSION_STRATEGY_KEY, $compressionStrategies)
            ->addBoolKeyedDimensionAllValuesCombinable(self::SHOULD_MOCK_CLOCK_KEY)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::NOT_COMPRESSIBLE_PROLOG_LENGTH_KEY, self::NOT_COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::COMPRESSIBLE_SEQUENCE_LENGTH_KEY, self::COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::NOT_COMPRESSIBLE_EPILOG_LENGTH_KEY, self::NOT_COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS)
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_WRAP_IN_PARENT_SPAN_KEY)
            ->buildAsMultiUse();

        $disabledCombinations = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::COMPRESSION_STRATEGY_KEY, $compressionStrategies)
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_MOCK_CLOCK_KEY)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::NOT_COMPRESSIBLE_PROLOG_LENGTH_KEY, [$maxNotCompressibleLength])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::COMPRESSIBLE_SEQUENCE_LENGTH_KEY, [max(self::COMPRESSIBLE_SEQUENCE_LENGTH_VARIANTS)])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::NOT_COMPRESSIBLE_EPILOG_LENGTH_KEY, [$maxNotCompressibleLength])
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_WRAP_IN_PARENT_SPAN_KEY)
            ->buildAsMultiUse();

        $result = (new DataProviderForTestBuilder())
            ->addGeneratorAllValuesCombinable(DataProviderForTestBuilder::masterSwitchCombinationsGenerator(self::IS_SPAN_COMPRESSION_ENABLED_KEY, $enabledCombinations, $disabledCombinations))
            ->build();
        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    private static function compressionStrategyToMaxDurationOptName(string $compressionStrategy): string
    {
        TestCaseBase::assertArrayHasKey($compressionStrategy, self::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME);
        return self::COMPRESSION_STRATEGY_TO_MAX_DURATION_OPTION_NAME[$compressionStrategy];
    }

    private static function generateSpanService(string $spanGroup, SpanInterface $span): void
    {
        Span::setServiceFor(
            $span,
            self::generateSpanProperty($spanGroup, 'service_target_type'),
            self::generateSpanProperty($spanGroup, 'service_target_name'),
            self::generateSpanProperty($spanGroup, 'destination_service_name'),
            self::generateSpanProperty($spanGroup, 'destination_service_resource'),
            self::generateSpanProperty($spanGroup, 'destination_service_type')
        );
    }

    private static function generateSpanProperty(string $spanGroup, string $propKind, ?int $uniqueSuffixCount = null): string
    {
        return 'test_span_-_' . $spanGroup . '_-_' . $propKind . ($uniqueSuffixCount === null ? '' : ('_' . $uniqueSuffixCount));
    }

    public static function isExactMatch(string $compressionStrategy): bool
    {
        switch ($compressionStrategy) {
            case Constants::COMPRESSION_STRATEGY_EXACT_MATCH:
                return true;
            case Constants::COMPRESSION_STRATEGY_SAME_KIND:
                return false;
            default:
                TestCaseBase::fail(LoggableToString::convert(['compressionStrategy' => $compressionStrategy]));
        }
    }

    /**
     * @param string   $spanGroup
     * @param bool     $shouldBeCompressible
     * @param ?string &$name
     * @param ?string &$type
     * @param ?string &$subtype
     * @param ?int    &$index
     *
     * @return void
     *
     * @param-out string $name
     * @param-out string $type
     * @param-out string $subtype
     * @param-out int    $index
     */
    private function buildSpanProperties(string $spanGroup, bool $shouldBeCompressible, ?string &$name, ?string &$type, ?string &$subtype, ?int &$index): void
    {
        $index = $this->spanCounter++;
        $isExactMatch = self::isExactMatch($this->compressionStrategy);
        if ($shouldBeCompressible) {
            $name = self::generateSpanProperty($spanGroup, 'name', $isExactMatch ? null : $index);
            $subtype = self::generateSpanProperty($spanGroup, 'subtype');
        } else {
            $name = self::generateSpanProperty($spanGroup, 'name');
            $subtype = self::generateSpanProperty($spanGroup, 'subtype', $index);
        }
        $type = self::generateSpanProperty($spanGroup, 'type');
    }

    private function beginSpan(string $spanGroup, bool $shouldBeCompressible): Span
    {
        $this->buildSpanProperties($spanGroup, $shouldBeCompressible, /* out */ $name, /* out */ $type, /* out */ $subtype, /* out */ $index);
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype);
        $span->context()->setLabel(self::SPAN_INDEX_LABEL_KEY, $index);
        TestCaseBase::assertInstanceOf(Span::class, $span);
        if ($shouldBeCompressible) {
            $span->setCompressible(true);
            if (!self::isExactMatch($this->compressionStrategy)) {
                self::generateSpanService($spanGroup, $span);
            }
        }
        return $span;
    }

    private function beginEndSpan(string $spanGroup, bool $shouldBeCompressible): void
    {
        $span = $this->beginSpan($spanGroup, $shouldBeCompressible);
        $this->fastForwardMockClock($this->mockClockSpanDuration);
        $span->end();
        if ($shouldBeCompressible) {
            if ($this->mockClock === null) {
                TestCaseBase::assertLessThanOrEqual($this->compressionMaxDuration, $span->duration);
            } else {
                TestCaseBase::assertSame($this->mockClockSpanDuration, $span->duration);
            }
        }
    }

    private function buildSpanExpectations(string $spanGroup, bool $shouldBeCompressible, ?float $mockClockDuration): SpanExpectations
    {
        $spanExpectations = new SpanExpectations();

        $this->buildSpanProperties($spanGroup, $shouldBeCompressible, /* out */ $name, /* out */ $type, /* out */ $subtype, /* out */ $index);
        $spanExpectations->name->setValue($name);
        $spanExpectations->type->setValue($type);
        $spanExpectations->subtype->setValue($subtype);
        $spanExpectations->ensureNotNullContext()->labels->setValue([self::SPAN_INDEX_LABEL_KEY => $index]);

        if ($this->mockClock !== null) {
            TestCaseBase::assertNotNull($mockClockDuration);
            $spanExpectations->timestamp->setValue($this->mockClock->getTimestamp());
            $spanExpectations->duration->setValue($mockClockDuration);
            $this->mockClock->fastForwardMilliseconds($mockClockDuration);
            $spanExpectations->timestampAfter = $this->mockClock->getTimestamp();
        }

        if ($shouldBeCompressible && !self::isExactMatch($this->compressionStrategy)) {
            $spanExpectations->setService(
                self::generateSpanProperty($spanGroup, 'service_target_type'),
                self::generateSpanProperty($spanGroup, 'service_target_name'),
                self::generateSpanProperty($spanGroup, 'destination_service_name'),
                self::generateSpanProperty($spanGroup, 'destination_service_resource'),
                self::generateSpanProperty($spanGroup, 'destination_service_type')
            );
        } else {
            $spanExpectations->assumeNotNullContext()->service->setValue(null);
            $spanExpectations->assumeNotNullContext()->destination->setValue(null);
        }

        return $spanExpectations;
    }

    private function beginEndCompressibleSpan(string $spanGroup): void
    {
        $this->beginEndSpan($spanGroup, /* shouldBeCompressible */ true);
    }

    private function beginEndNotCompressibleSpan(string $spanGroup): void
    {
        $this->beginEndSpan($spanGroup, /* shouldBeCompressible */ false);
    }

    private function buildCompositeSpanExpectations(string $spanGroup, SpanCompositeExpectations $compositeSubObject, ?float $mockClockDuration): SpanExpectations
    {
        $spanExpectations = $this->buildSpanExpectations($spanGroup, /* shouldBeCompressible */ true, $mockClockDuration);
        $spanExpectations->composite->setValue($compositeSubObject);
        if (!self::isExactMatch($compositeSubObject->compressionStrategy->getValue())) {
            $serviceTarget = $spanExpectations->assumeNotNullContext()->assumeNotNullService()->target;
            $spanExpectations->name->setValue('Calls to ' . $serviceTarget->type->getValue() . '/' . $serviceTarget->name->getValue());
        }
        $this->spanCounter += $compositeSubObject->count->getValue() - 1;
        return $spanExpectations;
    }

    private function buildRegularSpanExpectations(string $spanGroup): SpanExpectations
    {
        $spanExpectations = $this->buildSpanExpectations($spanGroup, /* shouldBeCompressible */ false, $this->mockClockSpanDuration);
        $spanExpectations->composite->setValue(null);
        return $spanExpectations;
    }

    private function buildCompositeButNotCompressedSpanExpectations(string $spanGroup): SpanExpectations
    {
        $spanExpectations = $this->buildSpanExpectations($spanGroup, /* shouldBeCompressible */ true, $this->mockClockSpanDuration);
        $spanExpectations->composite->setValue(null);
        return $spanExpectations;
    }

    private function fastForwardMockClock(?float $durationInMilliseconds): void
    {
        if ($this->mockClock !== null) {
            TestCaseBase::assertNotNull($durationInMilliseconds);
            $this->mockClock->fastForwardMilliseconds($durationInMilliseconds);
        }
    }

    private function fastForwardMockClockBetweenSpans(): void
    {
        if ($this->mockClock !== null && $this->spanCounter !== 0) {
            $this->mockClock->fastForwardMilliseconds(self::MOCK_CLOCK_TIME_BETWEEN_SPANS_IN_MILLISECONDS);
        }
    }

    public function implTestOneCompressedSequenceAct(): void
    {
        /** @var Transaction $tx */
        $tx = ElasticApm::getCurrentTransaction();
        TestCaseBase::assertSame($this->isSpanCompressionEnabled, $tx->getConfig()->spanCompressionEnabled());

        $this->spanCounter = 0;

        /** @var ?SpanInterface $parentSpan */
        $parentSpan = null;
        if ($this->shouldWrapInParentSpan) {
            $parentSpan = ElasticApm::getCurrentTransaction()->beginCurrentSpan('test_parent_span_name', 'test_parent_span_type');
            if ($this->mockClock !== null) {
                $this->mockClock->fastForwardMilliseconds(self::MOCK_CLOCK_TIME_BETWEEN_SPANS_IN_MILLISECONDS);
            }
        }

        foreach (RangeUtil::generateUpTo($this->notCompressiblePrologLength) as $ignored) {
            $this->fastForwardMockClockBetweenSpans();
            $this->beginEndNotCompressibleSpan('not_compressible_prolog');
        }

        foreach (RangeUtil::generateUpTo($this->compressibleSequenceLength) as $ignored) {
            $this->fastForwardMockClockBetweenSpans();
            $this->beginEndCompressibleSpan('compressible');
        }

        foreach (RangeUtil::generateUpTo($this->notCompressibleEpilogLength) as $ignored) {
            $this->fastForwardMockClockBetweenSpans();
            $this->beginEndNotCompressibleSpan('not_compressible_epilog');
        }

        if ($this->shouldWrapInParentSpan) {
            TestCaseBase::assertNotNull($parentSpan);
            TestCaseBase::assertSame($parentSpan, ElasticApm::getCurrentTransaction()->getCurrentSpan());
            $parentSpan->end();
        }
    }

    /**
     * @return SpanExpectations[]
     */
    public function expectedSpansForTestOneCompressedSequence(): array
    {
        /** @var SpanExpectations[] $expectedSpans */
        $expectedSpans = [];

        $this->spanCounter = 0;
        $this->mockClock = $this->mockClock === null ? null : new MockClock(self::MOCK_CLOCK_INITIAL_TIME);

        if ($this->shouldWrapInParentSpan) {
            $parentSpanExpectations = new SpanExpectations();
            $parentSpanExpectations->name->setValue('test_parent_span_name');
            $parentSpanExpectations->type->setValue('test_parent_span_type');

            if ($this->mockClock !== null) {
                $parentSpanExpectations->timestamp->setValue($this->mockClock->getTimestamp());
                $childrenSpansCount = $this->notCompressiblePrologLength + $this->compressibleSequenceLength + $this->notCompressibleEpilogLength;
                $mockClockDuration = $childrenSpansCount * (self::MOCK_CLOCK_TIME_BETWEEN_SPANS_IN_MILLISECONDS + $this->mockClockSpanDuration);
                $parentSpanExpectations->duration->setValue($mockClockDuration);
                $parentSpanExpectations->timestampAfter = $this->mockClock->getTimestamp() + TimeUtil::millisecondsToMicroseconds($mockClockDuration);
            }

            $expectedSpans[] = $parentSpanExpectations;
            if ($this->mockClock !== null) {
                $this->mockClock->fastForwardMilliseconds(self::MOCK_CLOCK_TIME_BETWEEN_SPANS_IN_MILLISECONDS);
            }
        }

        foreach (RangeUtil::generateUpTo($this->notCompressiblePrologLength) as $ignored) {
            $this->fastForwardMockClockBetweenSpans();
            $expectedSpans[] = $this->buildRegularSpanExpectations('not_compressible_prolog');
        }

        if ($this->isSpanCompressionEnabled) {
            $this->fastForwardMockClockBetweenSpans();
            $compressedSpanComposite = new SpanCompositeExpectations();
            $compressedSpanComposite->compressionStrategy->setValue($this->compressionStrategy);
            $compressedSpanComposite->count->setValue($this->compressibleSequenceLength);
            $duration = null;
            if ($this->mockClock !== null) {
                $compressedSpanComposite->durationsSum->setValue($this->compressibleSequenceLength * $this->mockClockSpanDuration);
                $duration = $this->compressibleSequenceLength * $this->mockClockSpanDuration + ($this->compressibleSequenceLength - 1) * self::MOCK_CLOCK_TIME_BETWEEN_SPANS_IN_MILLISECONDS;
            }
            $expectedSpans[] = $this->buildCompositeSpanExpectations('compressible', $compressedSpanComposite, $duration);
        } else {
            foreach (RangeUtil::generateUpTo($this->compressibleSequenceLength) as $ignored) {
                $this->fastForwardMockClockBetweenSpans();
                $expectedSpans[] = $this->buildCompositeButNotCompressedSpanExpectations('compressible');
            }
        }

        foreach (RangeUtil::generateUpTo($this->notCompressibleEpilogLength) as $ignored) {
            $this->fastForwardMockClockBetweenSpans();
            $expectedSpans[] = $this->buildRegularSpanExpectations('not_compressible_epilog');
        }

        return $expectedSpans;
    }

    public function implTestOneCompressedSequenceAssert(DataFromAgent $dataFromAgent): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));

        $expectedTx = new TransactionExpectations();

        /**
         * When a span is compressed into a composite, span_count.reported should ONLY count the compressed composite as a single span.
         * Spans that have been compressed into the composite should not be counted.
         *
         * @link https://github.com/elastic/apm/blob/5e1bfbc95fa0358ef195cedba8cb1be281988227/specs/agents/handling-huge-traces/tracing-spans-compress.md#effects-on-span-count
         */
        $reportedSpansCount = $this->shouldWrapInParentSpan ? 1 : 0;
        $reportedSpansCount += $this->notCompressiblePrologLength + $this->notCompressibleEpilogLength;
        $reportedSpansCount += $this->isSpanCompressionEnabled ? 1 : $this->compressibleSequenceLength;
        TestCaseBase::assertCount($reportedSpansCount, $dataFromAgent->idToSpan);
        $expectedTx->startedSpansCount->setValue($reportedSpansCount);

        $receivedTx = $dataFromAgent->singleTransaction();
        $receivedTx->assertMatches($expectedTx);

        $expectedSpans = $this->expectedSpansForTestOneCompressedSequence();

        foreach ($expectedSpans as $expectedSpan) {
            $expectedSpan->transactionId->setValue($receivedTx->id);
            $expectedSpan->traceId->setValue($receivedTx->traceId);
        }

        $receivedSpans = array_values($dataFromAgent->idToSpan);

        if ($this->shouldWrapInParentSpan) {
            $expectedChildrenSpans = $expectedSpans;
            /** @var ?SpanExpectations $expectedParentSpan */
            $expectedParentSpan = null;
            foreach ($expectedChildrenSpans as $key => $expectedSpan) {
                if ($expectedSpan->name->getValue() === 'test_parent_span_name') {
                    $expectedParentSpan = $expectedSpan;
                    unset($expectedChildrenSpans[$key]);
                    break;
                }
            }
            TestCaseBase::assertNotNull($expectedParentSpan);

            $receivedChildrenSpans = $receivedSpans;
            /** @var SpanDto $receivedParentSpan */
            $receivedParentSpan = null;
            foreach ($receivedChildrenSpans as $key => $receivedSpan) {
                if ($receivedSpan->name === 'test_parent_span_name') {
                    $receivedParentSpan = $receivedSpan;
                    unset($receivedChildrenSpans[$key]);
                    break;
                }
            }
            TestCaseBase::assertNotNull($receivedParentSpan);

            foreach ($expectedChildrenSpans as $expectedSpan) {
                $expectedSpan->parentId->setValue($receivedParentSpan->id);
            }
            $expectedParentSpan->parentId->setValue($receivedTx->id);

            SpanSequenceValidator::assertSequenceAsExpected([$expectedParentSpan], [$receivedParentSpan]);
            SpanSequenceValidator::assertSequenceAsExpected($expectedChildrenSpans, $receivedChildrenSpans);
        } else {
            foreach ($expectedSpans as $expectedSpan) {
                $expectedSpan->parentId->setValue($receivedTx->id);
            }
            SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, $receivedSpans);
        }
    }
}
