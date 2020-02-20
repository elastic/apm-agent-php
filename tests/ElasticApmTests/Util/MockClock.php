<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use ElasticApm\Impl\Clock;
use ElasticApm\Impl\ClockInterface;
use ElasticApm\Impl\Util\TimeUtil;

class MockClock implements ClockInterface
{
    /** @var float */
    private $systemClockCurrentTime;

    /** @var float */
    private $monotonicClockCurrentTime;

    public function __construct()
    {
        $this->systemClockCurrentTime = Clock::create()->getSystemClockCurrentTime();
        $this->monotonicClockCurrentTime = 1000000000;
    }

    public function getTimestamp(): float
    {
        return $this->getSystemClockCurrentTime();
    }

    public function fastForward(float $numberOfMicroseconds): void
    {
        $this->systemClockCurrentTime += $numberOfMicroseconds;
        $this->monotonicClockCurrentTime += $numberOfMicroseconds;
    }

    public function fastForwardDuration(float $durationInMilliseconds): void
    {
        $this->fastForward(TimeUtil::millisecondsToMicroseconds($durationInMilliseconds));
    }

    public function getSystemClockCurrentTime(): float
    {
        return $this->systemClockCurrentTime;
    }

    public function getMonotonicClockCurrentTime(): float
    {
        return $this->monotonicClockCurrentTime;
    }
}
