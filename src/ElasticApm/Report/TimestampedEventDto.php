<?php

declare(strict_types=1);

namespace ElasticApm\Report;

abstract class TimestampedEventDto implements TimestampedEventDtoInterface
{
    /** @var int */
    private $timestamp;

    /** @inheritDoc */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /** @inheritDoc */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
