<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface ClockInterface
{
    /**
     * @return float UTC based and in microseconds since Unix epoch
     *
     * @see ExecutionSegmentInterface::getTimestamp() For the description
     */
    public function getSystemClockCurrentTime(): float;

    /**
     * Clock that cannot be set and represents monotonic time since some unspecified starting point.
     * In microseconds.
     * Used to measure duration.
     *
     * @return float Monotonic time since some unspecified starting point in microseconds
     *
     * @see ExecutionSegmentInterface::getDuration() For example
     */
    public function getMonotonicClockCurrentTime(): float;
}
