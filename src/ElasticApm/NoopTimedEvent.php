<?php

declare(strict_types=1);

namespace ElasticApm;

abstract class NoopTimedEvent extends NoopTimestampedEvent
{
    /** @inheritDoc */
    public function getDuration(): float
    {
        return 0.0;
    }

    public function setDuration(float $duration): void
    {
    }
}
