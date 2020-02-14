<?php

declare(strict_types=1);

namespace ElasticApm\Report;

interface TimestampedEventDtoInterface
{
    /**
     * Recorded time of the event.
     * For events that have non-zero duration this time corresponds to the start of the event.
     * UTC based and in microseconds since Unix epoch.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/timestamp_epoch.json#L7
     */
    public function getTimestamp(): int;

    /**
     * @param int $timestamp
     *
     * @see getTimestamp() For the description
     */
    public function setTimestamp(int $timestamp): void;
}
