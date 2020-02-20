<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\ExecutionSegmentInterface;
use ElasticApm\Impl\Util\IdGenerator;
use ElasticApm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegment implements ExecutionSegmentInterface
{
    /** @var Tracer */
    private $tracer;

    /** @var float UTC based and in microseconds since Unix epoch */
    private $timestamp;

    /** @var float Monotonic time since some unspecified starting point, in microseconds */
    private $monotonicBeginTime;

    /** @var float */
    private $duration;

    /** @var string */
    private $id;

    /** @var string */
    private $traceId;

    /** @var string */
    private $type;

    /** @var array<string, string|bool|int|float|null> */
    private $tags = [];

    protected function __construct(Tracer $tracer, string $traceId, string $type)
    {
        $this->timestamp = $tracer->getClock()->getSystemClockCurrentTime();
        $this->monotonicBeginTime = $tracer->getClock()->getMonotonicClockCurrentTime();
        $this->tracer = $tracer;
        $this->traceId = $traceId;
        $this->id = IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->setType($type);
    }

    public function end(?float $duration = null): void
    {
        if ($duration === null) {
            $monotonicEndTime = $this->tracer->getClock()->getMonotonicClockCurrentTime();
            $this->duration = TimeUtil::calcDuration($this->monotonicBeginTime, $monotonicEndTime);
        } else {
            $this->duration = $duration;
        }
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setLabel(string $key, $value): void
    {
        $this->tags[$key] = $value;
    }

    public function getLabel(string $key, $default = null)
    {
        return $this->tags[$key] ?? $default;
    }
}
