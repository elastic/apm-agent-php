<?php

declare(strict_types=1);

namespace ElasticApm\Report;

interface TimedEventDtoInterface extends TimestampedEventDtoInterface
{
    /**
     * How long the event took to complete.
     * In milliseconds with 3 decimal points.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/common_transaction.json#L11
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/common_span.json#L55
     */
    public function getDuration(): float;

    /**
     * @param float $duration
     *
     * @see getDuration() For the description
     */
    public function setDuration(float $duration): void;
}
