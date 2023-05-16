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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\UnitTests\Util\MockClock;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

class BreakdownMetricsTest extends TracerUnitTestCaseBase
{
    private const TEST_INPUT_DATA_BREAKDOWN_METRICS_CONFIG = 'TEST_INPUT_DATA_BREAKDOWN_METRICS_CONFIG';

    private const EXPECTED_TEST_INPUT_DATA = 'EXPECTED_TEST_INPUT_DATA';
    private const EXPECTED_TRANSACTION_TIMESTAMP = 'EXPECTED_TRANSACTION_TIMESTAMP';
    private const EXPECTED_TRANSACTION_NAME = 'EXPECTED_TRANSACTION_NAME';
    private const EXPECTED_TRANSACTION_TYPE = 'EXPECTED_TRANSACTION_TYPE';
    private const EXPECTED_TRANSACTION_DURATION = 'EXPECTED_TRANSACTION_DURATION';
    private const EXPECTED_SPAN_SELF_TIMES = 'EXPECTED_SPAN_SELF_TIMES';
    private const EXPECTED_SPAN_SELF_TIME_SUM = 'EXPECTED_SPAN_SELF_TIME_SUM';
    private const EXPECTED_SPAN_SELF_TIME_COUNT = 'EXPECTED_SPAN_SELF_TIME_COUNT';
    private const EXPECTED_SPAN_TYPE = 'EXPECTED_SPAN_TYPE';
    private const EXPECTED_SPAN_SUBTYPE = 'EXPECTED_SPAN_SUBTYPE';

    private const EXPECTED_TRANSACTION_BREAKDOWN_COUNT_SAMPLE_KEY = 'transaction.breakdown.count';
    private const EXPECTED_SPAN_SELF_TIME_COUNT_SAMPLE_KEY = 'span.self_time.count';
    private const EXPECTED_SPAN_SELF_TIME_SUM_US_SAMPLE_KEY = 'span.self_time.sum.us';
    private const EXPECTED_TRANSACTION_SPAN_TYPE = 'app';

    /** @var MockClock */
    protected $mockClock;

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();

        $this->mockClock = new MockClock(/* initial */ 1000 * 1000 * 1000);
    }

    /**
     * @param array<string, mixed> $expectedData
     */
    public function assertExpectedBreakdownMetrics(array $expectedData): void
    {
        $testInputData = $expectedData[self::EXPECTED_TEST_INPUT_DATA];
        $this->assertIsArray($testInputData);
        $breakdownMetricsConfig = $testInputData[self::TEST_INPUT_DATA_BREAKDOWN_METRICS_CONFIG];
        $this->assertIsBool($breakdownMetricsConfig);

        $expectedTxTimestamp = $expectedData[self::EXPECTED_TRANSACTION_TIMESTAMP];
        $this->assertTrue(is_int($expectedTxTimestamp) || is_float($expectedTxTimestamp));
        $expectedTxName = $expectedData[self::EXPECTED_TRANSACTION_NAME];
        $this->assertIsString($expectedTxName);
        $expectedTxType = $expectedData[self::EXPECTED_TRANSACTION_TYPE];
        $this->assertIsString($expectedTxType);
        $expectedTxDuration = $expectedData[self::EXPECTED_TRANSACTION_DURATION];
        $this->assertTrue(is_int($expectedTxDuration) || is_float($expectedTxDuration));
        $expectedSelfTimes = $expectedData[self::EXPECTED_SPAN_SELF_TIMES];
        $this->assertIsArray($expectedSelfTimes);

        $tx = $this->mockEventSink->singleTransaction();
        $this->assertSame($expectedTxTimestamp, $tx->timestamp);
        $this->assertSame($expectedTxName, $tx->name);
        $this->assertSame($expectedTxType, $tx->type);
        $this->assertSame(floatval($expectedTxDuration), TimeUtil::millisecondsToMicroseconds($tx->duration));

        $metricSets = $this->mockEventSink->dataFromAgent->metricSets;
        // +1 is for the metric-set with "transaction.breakdown.count"
        $this->assertCount($breakdownMetricsConfig ? count($expectedSelfTimes) + 1 : 0, $metricSets);

        $metricSetWithTxBreakdownCountFound = false;
        foreach ($metricSets as $metricSet) {
            $this->assertSame($expectedTxTimestamp, $metricSet->timestamp);
            $this->assertSame($expectedTxName, $metricSet->transactionName);
            $this->assertSame($expectedTxType, $metricSet->transactionType);

            $txBreakdownCountVal = $metricSet->getSample(self::EXPECTED_TRANSACTION_BREAKDOWN_COUNT_SAMPLE_KEY);
            // if it's the metric-set with "transaction.breakdown.count"
            if ($txBreakdownCountVal !== null) {
                // There should be exactly one metric-set with "transaction.breakdown.count"
                $this->assertFalse($metricSetWithTxBreakdownCountFound);
                $metricSetWithTxBreakdownCountFound = true;

                // PHP Agent sends metric sets for Breakdown Metrics feature after each transaction
                // so "transaction.breakdown.count" should 1
                $this->assertSame(1, $txBreakdownCountVal);

                $this->assertNull($metricSet->spanType);
                $this->assertNull($metricSet->spanSubtype);

                // span self-time metric-set should contain exactly 2 samples:
                // "span.self_time.count" and "span.self_time.sum.us"
                $this->assertSame(1, $metricSet->samplesCount());

                continue;
            }

            // span self-time metric-set should contain exactly 2 samples:
            // "span.self_time.count" and "span.self_time.sum.us"
            $this->assertSame(2, $metricSet->samplesCount());

            $this->assertNotNull($metricSet->spanType);
            $found = false;
            foreach ($expectedSelfTimes as $expectedSelfTime) {
                if ($expectedSelfTime[self::EXPECTED_SPAN_TYPE] !== $metricSet->spanType) {
                    continue;
                }

                if (array_key_exists(self::EXPECTED_SPAN_SUBTYPE, $expectedSelfTime)) {
                    if ($expectedSelfTime[self::EXPECTED_SPAN_SUBTYPE] !== $metricSet->spanSubtype) {
                        continue;
                    }
                } else {
                    if ($metricSet->spanSubtype !== null) {
                        continue;
                    }
                }

                $actualSpanCount = $metricSet->getSample(self::EXPECTED_SPAN_SELF_TIME_COUNT_SAMPLE_KEY);
                $actualSpanDurationSum = $metricSet->getSample(self::EXPECTED_SPAN_SELF_TIME_SUM_US_SAMPLE_KEY);
                $expectedSpanCount = $expectedSelfTime[self::EXPECTED_SPAN_SELF_TIME_COUNT];
                $expectedSpanDurationSum = $expectedSelfTime[self::EXPECTED_SPAN_SELF_TIME_SUM];
                $this->assertSame($expectedSpanCount, $actualSpanCount);
                $this->assertSame(floatval($expectedSpanDurationSum), floatval($actualSpanDurationSum));

                $found = true;
                break;
            }
            $this->assertTrue($found, LoggableToString::convert($metricSet));
        }

        // There should be exactly one metric-set with "transaction.breakdown.count" if and only if
         // breakdown_metrics config is true
        $this->assertSame($breakdownMetricsConfig, $metricSetWithTxBreakdownCountFound);
    }

    /**
     * @return iterable<array<array<string, mixed>>>
     */
    public function dataProviderForTestsInput(): iterable
    {
        $testInputData = [];
        foreach ([true, false] as $breakdownMetricsConfig) {
            $testInputData[self::TEST_INPUT_DATA_BREAKDOWN_METRICS_CONFIG] = $breakdownMetricsConfig;
            yield [$testInputData];
        }
    }

    /**
     * @param array<string, mixed> $testInputData
     */
    private function setUpTestEnvEx(array $testInputData): void
    {
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($testInputData): void {
                $breakdownMetricsConfig = $testInputData[self::TEST_INPUT_DATA_BREAKDOWN_METRICS_CONFIG];
                $this->assertIsBool($breakdownMetricsConfig);
                $builder->withClock($this->mockClock)
                        ->withBoolConfig(OptionNames::BREAKDOWN_METRICS, $breakdownMetricsConfig);
            }
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test1TxWithoutSpans(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###################################|       30      30    app (automatically assigned to transaction)
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $this->mockClock->fastForwardMicroseconds(987654321);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds($txDurationInMicroseconds);
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => 1,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $txDurationInMicroseconds,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test2MySqlSpan(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |#####|:::::::::::|#################|       30      20    app (automatically assigned to transaction)
        // +~~~~>|###########|                         10      10    DB_span_type/MySQL_span_subtype
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $txSelfTimeSum = 20;
        $dbSpanSelfTimeSum = 10;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);

        $dbSpanType = 'DB_span_type_' . __FUNCTION__;
        $dbSpanSubtype = 'MySQL_span_subtype_' . __FUNCTION__;
        $dbSpan = $tx->beginCurrentSpan('SELECT ...', $dbSpanType, $dbSpanSubtype);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ $dbSpanSelfTimeSum);
        $dbSpan->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 15);
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => 1,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $txSelfTimeSum,
                    ],
                    [
                        self::EXPECTED_SPAN_TYPE            => $dbSpanType,
                        self::EXPECTED_SPAN_SUBTYPE         => $dbSpanSubtype,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => 1,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $dbSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test3ExplicitAppSpan(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |#####|:::::::::::|#################|       30      20    app (automatically assigned to transaction)
        // +~~~~>|###########|                         10      10    app (used explicitly)
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $appSpanSelfTimeSum = 30;
        $appSpanCount = 2;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);

        $explicitAppSpan = $tx->beginCurrentSpan('span_name_' . __FUNCTION__, self::EXPECTED_TRANSACTION_SPAN_TYPE);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        $explicitAppSpan->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 15);
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test4FullyOverlappingL1Spans(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |#################|:::::::::::|#####|       30      20    app (automatically assigned to transaction)
        // +~~~~~~~~~~~~~~~~>|###########|             10      10    db/mysql
        // +~~~~~~~~~~~~~~~~>|###########|             10      10    db/mysql
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $appSpanSelfTimeSum = 20;
        $appSpanCount = 1;
        $dbSpanSelfTimeSum = 20;
        $dbSpanCount = 2;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 15);

        $dbSpan1 = $tx->beginChildSpan('SELECT 1 ...', 'db', 'mysql');
        $dbSpan2 = $tx->beginChildSpan('SELECT 2 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        $dbSpan1->end();
        $dbSpan2->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => 'db',
                        self::EXPECTED_SPAN_SUBTYPE         => 'mysql',
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $dbSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $dbSpanSelfTimeSum,
                    ],
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test5PartiallyOverlappingL1Spans(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###########|:::::::::::::::::|#####|       30      15    app (automatically assigned to transaction)
        // +~~~~~~~~~~>|###########|                   10      10    db/mysql
        // +~~~~~~~~~~~~~~~~>|###########|             10      10    db/mysql
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $appSpanSelfTimeSum = 15;
        $appSpanCount = 1;
        $dbSpanSelfTimeSum = 20;
        $dbSpanCount = 2;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 10 microseconds
        $dbSpan1 = $tx->beginChildSpan('SELECT 1 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 15 microseconds
        $dbSpan2 = $tx->beginChildSpan('SELECT 2 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 20 microseconds
        $dbSpan1->end();
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 25 microseconds
        $dbSpan2->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 30 microseconds
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => 'db',
                        self::EXPECTED_SPAN_SUBTYPE         => 'mysql',
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $dbSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $dbSpanSelfTimeSum,
                    ],
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test6NonOverlappingL1SpansWithoutGap(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |#####|:::::::::::::::::::::::|#####|       30      10    app (automatically assigned to transaction)
        // +~~~~>|###########|                         10      10    db/mysql
        // +~~~~~~~~~~~~~~~~>|###########|             10      10    db/mysql
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $appSpanSelfTimeSum = 10;
        $appSpanCount = 1;
        $dbSpanSelfTimeSum = 20;
        $dbSpanCount = 2;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 5 microseconds

        $dbSpan1 = $tx->beginChildSpan('SELECT 1 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 15 microseconds
        $dbSpan1->end();

        $dbSpan2 = $tx->beginChildSpan('SELECT 2 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 25 microseconds
        $dbSpan2->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 30 microseconds
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => 'db',
                        self::EXPECTED_SPAN_SUBTYPE         => 'mysql',
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $dbSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $dbSpanSelfTimeSum,
                    ],
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test7NonOverlappingSpansWithGap(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###########|:::::|#####|:::::|#####|       30      20    app (automatically assigned to transaction)
        // +~~~~~~~~~~>|#####|                          5       5    db/mysql
        // +~~~~~~~~~~~~~~~~~~~~~~>|#####|              5       5    db/mysql
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $appSpanSelfTimeSum = 20;
        $appSpanCount = 1;
        $dbSpanSelfTimeSum = 10;
        $dbSpanCount = 2;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 10 microseconds

        $dbSpan1 = $tx->beginChildSpan('SELECT 1 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 15 microseconds
        $dbSpan1->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 20 microseconds

        $dbSpan2 = $tx->beginChildSpan('SELECT 2 ...', 'db', 'mysql');
        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 25 microseconds
        $dbSpan2->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 30 microseconds
        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => 'db',
                        self::EXPECTED_SPAN_SUBTYPE         => 'mysql',
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $dbSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $dbSpanSelfTimeSum,
                    ],
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test8L1SpanEndsBeforeChildL2Span(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###########|:::::::::::|###########|       30      20    app (automatically assigned to transaction)
        // +~~~~~~~~~~>|#####|:::::|                   10       5    app (used explicitly)
        //             +~~~~>|###########|             10      10    db/mysql
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30

        $appSpanSelfTimeSum = 25;
        $appSpanCount = 2;
        $dbSpanSelfTimeSum = 10;
        $dbSpanCount = 1;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $txDurationInMicroseconds = 30;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 10 microseconds

        $l1AppSpan = $tx->beginChildSpan('level 1 explicit app span', self::EXPECTED_TRANSACTION_SPAN_TYPE);

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 15 microseconds

        $l2DbSpan = $l1AppSpan->beginChildSpan('level 2 (l1AppSpan child) DB span', 'db', 'mysql');

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 20 microseconds

        $l1AppSpan->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 25 microseconds

        $l2DbSpan->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 5);
        // t = 30 microseconds

        $tx->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => 'db',
                        self::EXPECTED_SPAN_SUBTYPE         => 'mysql',
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $dbSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $dbSpanSelfTimeSum,
                    ],
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test9TransactionEndsBeforeL1AndL2SpansEnd(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###########|:::::::::::|                   20      10    app (automatically assigned to transaction)
        // +~~~~~~~~~~>|###########|:::::::::::|       20      10    app (used explicitly) - not included (see below)
        //             +~~~~~~~~~~>|###########|       10      10    db/mysql - not included (see below)
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30
        //
        // Level 1 (app - used explicitly) and level 2 (db/mysql) spans are not included
        // in the reported metrics because transaction ends before these spans end

        $txDurationInMicroseconds = 20;
        $appSpanSelfTimeSum = 10;
        $appSpanCount = 1;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 10 microseconds

        $l1AppSpan = $tx->beginChildSpan('level 1 explicit app span', self::EXPECTED_TRANSACTION_SPAN_TYPE);

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 20 microseconds

        $l2DbSpan = $l1AppSpan->beginChildSpan('level 2 (l1AppSpan child) DB span', 'db', 'mysql');
        $tx->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 30 microseconds

        $l2DbSpan->end();
        $l1AppSpan->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test10TransactionEndsBeforeL1SpanEnds(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###########|:::::::::::|                   20      10    app (automatically assigned to transaction)
        // +~~~~~~~~~~>|#######################|       10      10    db/mysql - not included (see below)
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30
        //
        // Level 1 (db/mysql) spans is not included in the reported metrics
        // because transaction ended before these spans ended

        $txDurationInMicroseconds = 20;
        $appSpanSelfTimeSum = 10;
        $appSpanCount = 1;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 10 microseconds

        $l1DbSpan = $tx->beginChildSpan('SELECT ...', 'db', 'mysql');

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 20 microseconds

        $tx->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 30 microseconds

        $l1DbSpan->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestsInput
     *
     * @param array<string, mixed> $testInputData
     */
    public function test11L1SpanStartsAfterTransactionAlreadyEnded(array $testInputData): void
    {
        //                                          total    self    type/subtype
        // |###########|                               10      10    app (automatically assigned to transaction)
        // +~~~~~~~~~~~~~~~~~~~~~~>|###########|       10      10    db/mysql - not included (see below)
        //
        // |-----|-----|-----|-----|-----|-----|---------------------> t (microseconds)
        // 0     5     10    15    20    25    30
        //
        // Level 1 (db/mysql) spans is not included in the reported metrics
        // because transaction ends before this span ends

        $txDurationInMicroseconds = 10;
        $appSpanSelfTimeSum = 10;
        $appSpanCount = 1;

        // Arrange

        $this->setUpTestEnvEx($testInputData);
        $txTimestamp = $this->mockClock->getTimestamp();

        // Act

        $txName = 'test_TX_name_' . __FUNCTION__;
        $txType = 'test_TX_type_' . __FUNCTION__;
        $tx = $this->tracer->beginCurrentTransaction($txName, $txType);

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 10 microseconds

        $tx->end();

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 20 microseconds

        $l1DbSpan = $tx->beginChildSpan('SELECT ...', 'db', 'mysql');

        $this->mockClock->fastForwardMicroseconds(/* microseconds: */ 10);
        // t = 30 microseconds

        $l1DbSpan->end();

        // Assert

        $this->assertExpectedBreakdownMetrics(
            [
                self::EXPECTED_TEST_INPUT_DATA       => $testInputData,
                self::EXPECTED_TRANSACTION_TIMESTAMP => $txTimestamp,
                self::EXPECTED_TRANSACTION_NAME      => $txName,
                self::EXPECTED_TRANSACTION_TYPE      => $txType,
                self::EXPECTED_TRANSACTION_DURATION  => $txDurationInMicroseconds,
                self::EXPECTED_SPAN_SELF_TIMES       => [
                    [
                        self::EXPECTED_SPAN_TYPE            => self::EXPECTED_TRANSACTION_SPAN_TYPE,
                        self::EXPECTED_SPAN_SELF_TIME_COUNT => $appSpanCount,
                        self::EXPECTED_SPAN_SELF_TIME_SUM   => $appSpanSelfTimeSum,
                    ],
                ],
            ]
        );
    }
}
