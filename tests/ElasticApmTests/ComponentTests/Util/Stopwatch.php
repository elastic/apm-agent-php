<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Util\TimeUtil;

final class Stopwatch
{
    /** @var float */
    private $timeStarted;

    public function __construct()
    {
        $this->timeStarted = Clock::singletonInstance()->getMonotonicClockCurrentTime();
    }

    public function elapsedInMicroseconds(): float
    {
        $now = Clock::singletonInstance()->getMonotonicClockCurrentTime();
        return TimeUtil::calcDurationInMicroseconds($this->timeStarted, $now);
    }

    public function restart(): void
    {
        $this->timeStarted = Clock::singletonInstance()->getMonotonicClockCurrentTime();
    }
}
