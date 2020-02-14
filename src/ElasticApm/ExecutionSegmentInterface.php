<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace ElasticApm;

use DateTimeInterface;
use ElasticApm\Report\ExecutionSegmentDtoInterface;

interface ExecutionSegmentInterface extends ExecutionSegmentDtoInterface
{
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface;

    /**
     * Sets the end timestamp and finalizes this object's state.
     *
     * With the exception of calls to getContext() (which are always allowed),
     * end() must be the last call made to any object of this type,
     * and to do otherwise leads to undefined behavior but not throwing an exception.
     *
     * If end() was already called for this object then a warning should be logged.
     *
     * @param float|int|DateTimeInterface|null $endTime if passing float or int
     *                                                  it should represent the timestamp (including as many decimal
     *                                                  places as you need)
     */
    public function end($endTime = null): void;

    public function isNoop(): bool;
}
