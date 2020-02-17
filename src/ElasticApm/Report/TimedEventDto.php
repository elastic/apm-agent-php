<?php

declare(strict_types=1);

namespace ElasticApm\Report;

abstract class TimedEventDto extends TimestampedEventDto implements TimedEventDtoInterface
{
    /** @var float */
    private $duration;

    /** @inheritDoc */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /** @inheritDoc */
    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }
}
