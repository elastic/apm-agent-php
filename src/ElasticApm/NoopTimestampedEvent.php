<?php

declare(strict_types=1);

namespace ElasticApm;

abstract class NoopTimestampedEvent
{
    /** @inheritDoc */
    public function getTimestamp(): int
    {
        return 0;
    }

    /** @inheritDoc */
    public function setTimestamp(int $timestamp): void
    {
    }
}
