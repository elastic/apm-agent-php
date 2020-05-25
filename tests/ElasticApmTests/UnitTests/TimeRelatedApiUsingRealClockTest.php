<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;

class TimeRelatedApiUsingRealClockTest extends UnitTestCaseBase
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
        $reportedTx = $this->mockEventSink->getSingleTransaction();
        $this->assertGreaterThanOrEqual($beforeBegin, $reportedTx->getTimestamp());
        $this->assertGreaterThanOrEqual(
            self::calcDuration($beforeSleep, $afterSleep),
            self::calcDuration($beforeBegin, $afterEnd)
        );
        $this->assertGreaterThanOrEqual(self::calcDuration($beforeSleep, $afterSleep), $reportedTx->getDuration());
        $this->assertLessThanOrEqual(self::calcDuration($beforeBegin, $afterEnd), $reportedTx->getDuration());
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
        $reportedTx = $this->mockEventSink->getSingleTransaction();
        $this->assertGreaterThanOrEqual(
            self::calcDuration($beforeSleep, $afterSleep),
            self::calcDuration($beforeBegin, $afterEnd)
        );
        $this->assertGreaterThan($expectedDuration, self::calcDuration($beforeSleep, $afterSleep));
        $this->assertSame($expectedDuration, $reportedTx->getDuration());
    }

    public function testSpanBeginEnd(): void
    {
        // Act
        $beforeBeginTransaction = self::getCurrentTimestamp();
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $afterBeginTransaction = self::getCurrentTimestamp();
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
        $reportedSpan = $this->mockEventSink->getSingleSpan();
        $this->assertGreaterThanOrEqual($beforeBeginSpan, $reportedSpan->getTimestamp());
        $this->assertGreaterThanOrEqual(
            self::calcDuration($afterBeginTransaction, $beforeBeginSpan),
            $reportedSpan->getStart()
        );
        $this->assertLessThanOrEqual(
            self::calcDuration($beforeBeginTransaction, $afterBeginSpan),
            $reportedSpan->getStart()
        );
        $this->assertGreaterThanOrEqual(
            self::calcDuration($afterBeginSpan, $beforeEnd),
            $reportedSpan->getDuration()
        );
        $this->assertLessThanOrEqual(
            self::calcDuration($beforeBeginSpan, $afterEnd),
            $reportedSpan->getDuration()
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
        $reportedSpan = $this->mockEventSink->getSingleSpan();
        $this->assertSame($expectedSpanDuration, $reportedSpan->getDuration());
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
