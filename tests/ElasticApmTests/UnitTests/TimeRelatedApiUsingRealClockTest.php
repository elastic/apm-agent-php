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

use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class TimeRelatedApiUsingRealClockTest extends TracerUnitTestCaseBase
{
    public function testTransactionBeginEnd(): void
    {
        // Act
        $beforeBegin = self::getCurrentTimestamp();
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        // In milliseconds with 3 decimal points
        $beforeSleep = self::getCurrentTimestamp();
        self::sleepDuration(456.789);
        $afterSleep = self::getCurrentTimestamp();
        $tx->end();
        $afterEnd = self::getCurrentTimestamp();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertGreaterThanOrEqual($beforeBegin, $reportedTx->timestamp);
        $this->assertGreaterThanOrEqual(
            self::calcDuration($beforeSleep, $afterSleep),
            self::calcDuration($beforeBegin, $afterEnd)
        );
        $this->assertGreaterThanOrEqual(self::calcDuration($beforeSleep, $afterSleep), $reportedTx->duration);
        $this->assertLessThanOrEqual(self::calcDuration($beforeBegin, $afterEnd), $reportedTx->duration);
    }

    public function testTransactionBeginEndWithDuration(): void
    {
        // Act
        $beforeBegin = self::getCurrentTimestamp();
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        // In milliseconds with 3 decimal points
        $expectedDuration = 322.556;
        $beforeSleep = self::getCurrentTimestamp();
        self::sleepDuration($expectedDuration + 456.789);
        $afterSleep = self::getCurrentTimestamp();
        $tx->end($expectedDuration);
        $afterEnd = self::getCurrentTimestamp();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertGreaterThanOrEqual(
            self::calcDuration($beforeSleep, $afterSleep),
            self::calcDuration($beforeBegin, $afterEnd)
        );
        $this->assertGreaterThan($expectedDuration, self::calcDuration($beforeSleep, $afterSleep));
        $this->assertSame($expectedDuration, $reportedTx->duration);
    }

    public function testSpanBeginEnd(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        self::sleepDuration(158.432);
        $beforeBeginSpan = self::getCurrentTimestamp();
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $afterBeginSpan = self::getCurrentTimestamp();
        self::sleepDuration(456.789);
        $beforeEnd = self::getCurrentTimestamp();
        $span->end();
        $tx->end();
        $afterEnd = self::getCurrentTimestamp();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertGreaterThanOrEqual($beforeBeginSpan, $reportedSpan->timestamp);
        $this->assertGreaterThanOrEqual(
            self::calcDuration($afterBeginSpan, $beforeEnd),
            $reportedSpan->duration
        );
        $this->assertLessThanOrEqual(
            self::calcDuration($beforeBeginSpan, $afterEnd),
            $reportedSpan->duration
        );
    }

    public function testSpanBeginEndWithDuration(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $afterBeginSpan = self::getCurrentTimestamp();
        // In milliseconds with 3 decimal points
        $expectedSpanDuration = 322.556;
        self::sleepDuration($expectedSpanDuration + 456.789);
        $beforeEnd = self::getCurrentTimestamp();
        $span->end($expectedSpanDuration);
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame($expectedSpanDuration, $reportedSpan->duration);
        $this->assertGreaterThan($expectedSpanDuration, self::calcDuration($afterBeginSpan, $beforeEnd));
    }

    /**
     * @return float float UTC based and in microseconds since Unix epoch
     */
    private static function getCurrentTimestamp(): float
    {
        // microtime(/* get_as_float: */ true) returns in seconds with microseconds being the fractional part
        return round(microtime(/* get_as_float: */ true) * 1000000.0);
    }

    private static function sleepDuration(float $milliseconds): void
    {
        // usleep - Delay execution in microseconds
        usleep((int)(ceil($milliseconds * 1000)));
    }

    /**
     * @param float $beginTimestamp Begin time in microseconds
     * @param float $endTimestamp   End time in microseconds
     *
     * @return float Duration in milliseconds
     */
    private static function calcDuration(float $beginTimestamp, float $endTimestamp): float
    {
        return ($endTimestamp - $beginTimestamp) / 1000.0;
    }
}
