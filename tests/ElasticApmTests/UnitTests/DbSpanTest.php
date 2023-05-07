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

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\Util\DbAutoInstrumentationUtil;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Constants;
use ElasticApmTests\ComponentTests\PDOAutoInstrumentationTest;
use ElasticApmTests\UnitTests\Util\MockClockTracerUnitTestCaseBase;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\DbSpanExpectationsBuilder;
use ElasticApmTests\Util\SpanCompositeExpectations;
use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use ElasticApmTests\Util\TracerBuilderForTests;

class DbSpanTest extends MockClockTracerUnitTestCaseBase
{
    /**
     * @return iterable<string, array<string, bool>>
     */
    public function dataProviderForTestDbSpanSerialization(): iterable
    {
        return DataProviderForTestBuilder::keyEachDataSetWithDbgDesc(
            [
                ['isSpanCompressionEnabled' => false],
                ['isSpanCompressionEnabled' => true],
            ]
        );
    }

    private const SPAN_DURATION_IN_MILLISECONDS = 1;
    /**
     * @dataProvider dataProviderForTestDbSpanSerialization
     */
    public function testDbSpanSerialization(bool $isSpanCompressionEnabled): void
    {
        if (!$isSpanCompressionEnabled) {
            SpanExpectations::$assumeSpanCompressionDisabled = true;
        }

        $beginEndDbSpanFromClassMethodNames = function (string $className, string $funcName, ?string $statement = null): void {
            $this->mockClock->fastForwardMilliseconds(100);
            $span = DbAutoInstrumentationUtil::beginDbSpan($className, $funcName, 'test_DB_type', 'test_DB_name', $statement);
            $this->mockClock->fastForwardMilliseconds(self::SPAN_DURATION_IN_MILLISECONDS);
            $span->end();
        };
        $beginEndDbSpanFromStatement = function (?string $statement = null): void {
            $this->mockClock->fastForwardMilliseconds(100);
            $span = DbAutoInstrumentationUtil::beginDbSpan(/* className */ 'DummyClass', /* funcName */ 'dummyMethod', 'test_DB_type', 'test_DB_name', $statement);
            $this->mockClock->fastForwardMilliseconds(self::SPAN_DURATION_IN_MILLISECONDS);
            $span->end();
        };

        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($isSpanCompressionEnabled): void {
                $builder->withBoolConfig(OptionNames::SPAN_COMPRESSION_ENABLED, $isSpanCompressionEnabled);
                $builder->withConfig(OptionNames::SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION, self::SPAN_DURATION_IN_MILLISECONDS . 'ms');
                // Effectively disable (since span duration greater than 0) same kind compression strategy to simplify expected results
                $builder->withConfig(OptionNames::SPAN_COMPRESSION_SAME_KIND_MAX_DURATION, '0');
            }
        );

        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $beginEndDbSpanFromClassMethodNames('PDO', 'beginTransaction');
        $beginEndDbSpanFromStatement(PDOAutoInstrumentationTest::CREATE_TABLE_SQL);
        foreach (PDOAutoInstrumentationTest::MESSAGES as $ignored) {
            $beginEndDbSpanFromStatement(PDOAutoInstrumentationTest::INSERT_SQL);
        }
        $beginEndDbSpanFromStatement(PDOAutoInstrumentationTest::SELECT_SQL);
        $beginEndDbSpanFromClassMethodNames('PDO', 'commit');
        $tx->end();

        $expectationsBuilder = new DbSpanExpectationsBuilder('test_DB_type', 'test_DB_name');
        /** @var SpanExpectations[] $expectedSpans */
        $expectedSpans = [];
        $expectedSpans[] = $expectationsBuilder->fromClassMethodNames('PDO', 'beginTransaction');
        $expectedSpans[] = $expectationsBuilder->fromStatement(PDOAutoInstrumentationTest::CREATE_TABLE_SQL);
        if ($isSpanCompressionEnabled) {
            $compressedSpan = $expectationsBuilder->fromStatement(PDOAutoInstrumentationTest::INSERT_SQL);
            $spanComposite = new SpanCompositeExpectations();
            $spanComposite->compressionStrategy->setValue(Constants::COMPRESSION_STRATEGY_EXACT_MATCH);
            $spanComposite->count->setValue(count(PDOAutoInstrumentationTest::MESSAGES));
            $spanComposite->durationsSum->setValue(floatval(self::SPAN_DURATION_IN_MILLISECONDS * count(PDOAutoInstrumentationTest::MESSAGES)));
            $compressedSpan->composite->setValue($spanComposite);
            $expectedSpans[] = $compressedSpan;
        } else {
            foreach (PDOAutoInstrumentationTest::MESSAGES as $ignored) {
                $expectedSpans[] = $expectationsBuilder->fromStatement(PDOAutoInstrumentationTest::INSERT_SQL);
            }
        }
        $expectedSpans[] = $expectationsBuilder->fromStatement(PDOAutoInstrumentationTest::SELECT_SQL);
        $expectedSpans[] = $expectationsBuilder->fromClassMethodNames('PDO', 'commit');

        SpanSequenceValidator::updateExpectationsEndTime($expectedSpans);
        SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, array_values($this->mockEventSink->idToSpan()));
    }
}
