<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\ClockInterface;
use Elastic\Apm\Impl\Util\TimeUtil;

class MockClock implements ClockInterface
{
    /** @var float */
    private $systemClockCurrentTime;

    /** @var float */
    private $monotonicClockCurrentTime;

    public function __construct()
    {
        $this->systemClockCurrentTime = Clock::instance()->getSystemClockCurrentTime();
        $this->monotonicClockCurrentTime = 1000000000;
    }

    public function getTimestamp(): float
    {
        return $this->getSystemClockCurrentTime();
    }

    public function fastForwardMicroseconds(float $numberOfMicroseconds): void
    {
        $this->systemClockCurrentTime += $numberOfMicroseconds;
        $this->monotonicClockCurrentTime += $numberOfMicroseconds;
    }

    public function fastForwardMilliseconds(float $durationInMilliseconds): void
    {
        $this->fastForwardMicroseconds(TimeUtil::millisecondsToMicroseconds($durationInMilliseconds));
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
