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

use ElasticApmTests\UnitTests\Util\MockClockTracerUnitTestCaseBase;

class TimeRelatedApiUsingMockClockTest extends MockClockTracerUnitTestCaseBase
{
    /** @inheritDoc */
    protected function mockClockInitialTimestamp(): float
    {
        return 1000 * 1000 * 1000;
    }

    public function testTransactionBeginEnd(): void
    {
        // Act

        $this->mockClock->fastForwardMicroseconds(987654321);
        $expectedTimestamp = $this->mockClock->getTimestamp();
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedDuration);
        $tx->end();

        // Assert

        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame($expectedTimestamp, $reportedTx->timestamp);
        $this->assertSame($expectedDuration, $reportedTx->duration);
    }

    public function testTransactionBeginEndWithDuration(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $expectedDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedDuration + 123456789);
        $tx->end($expectedDuration);

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame($expectedDuration, $reportedTx->duration);
    }

    public function testSpanBeginEnd(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->mockClock->fastForwardMilliseconds(112233.445);
        $expectedSpanTimestamp = $this->mockClock->getTimestamp();
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedSpanDuration);
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame($expectedSpanTimestamp, $reportedSpan->timestamp);
        $this->assertSame($expectedSpanDuration, $reportedSpan->duration);
    }

    public function testSpanBeginEndWithDuration(): void
    {
        // Act
        $this->mockClock->fastForwardMicroseconds(987654321);
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->mockClock->fastForwardMicroseconds(987654321);
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $expectedSpanDuration = 12345.678;
        $this->mockClock->fastForwardMilliseconds($expectedSpanDuration + 123456789);
        $span->end($expectedSpanDuration);
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame($expectedSpanDuration, $reportedSpan->duration);
    }
}
